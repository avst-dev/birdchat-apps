import Anthropic from "@anthropic-ai/sdk";
import Database from "better-sqlite3";
import bcrypt from "bcryptjs";
import dotenv from "dotenv";
import path from "path";
import { fileURLToPath } from "url";

dotenv.config();

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const DB_PATH = path.join(__dirname, "..", "birdchat.db");

// Initialize SQLite Database
export const db = new Database(DB_PATH);

// Enable foreign keys
db.pragma("foreign_keys = ON");

export function initializeDatabase() {
  // Create tables if they don't exist
  db.exec(`
    CREATE TABLE IF NOT EXISTS users (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      username TEXT NOT NULL UNIQUE,
      password TEXT NOT NULL,
      fullname TEXT,
      email TEXT,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
      failed_attempts INTEGER DEFAULT 0,
      locked_until DATETIME
    );

    CREATE TABLE IF NOT EXISTS messages (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id INTEGER NOT NULL,
      receiver_id INTEGER,
      message TEXT NOT NULL,
      reply_to INTEGER,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
      FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
      FOREIGN KEY (reply_to) REFERENCES messages(id) ON DELETE SET NULL
    );

    CREATE TABLE IF NOT EXISTS sessions (
      session_id TEXT NOT NULL PRIMARY KEY,
      user_id INTEGER,
      ip_address TEXT,
      user_agent_hash TEXT,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      expires_at DATETIME,
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS security_logs (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      event_type TEXT NOT NULL,
      event_description TEXT,
      ip_address TEXT,
      user_id INTEGER,
      user_agent TEXT,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE INDEX IF NOT EXISTS idx_messages_user_id ON messages(user_id);
    CREATE INDEX IF NOT EXISTS idx_messages_receiver_id ON messages(receiver_id);
    CREATE INDEX IF NOT EXISTS idx_messages_created_at ON messages(created_at);
    CREATE INDEX IF NOT EXISTS idx_sessions_user_id ON sessions(user_id);
    CREATE INDEX IF NOT EXISTS idx_security_logs_event_type ON security_logs(event_type);
    CREATE INDEX IF NOT EXISTS idx_security_logs_user_id ON security_logs(user_id);
  `);

  console.log("✅ Database initialized successfully");
}

export function logSecurityEvent(type, event, ip = null, uid = null) {
  const insertLog = db.prepare(`
    INSERT INTO security_logs (event_type, event_description, ip_address, user_id, user_agent)
    VALUES (?, ?, ?, ?, ?)
  `);

  insertLog.run(type, event, ip || "UNKNOWN", uid || null, "NodeJS");
}

export function createUser(username, password, fullname = "") {
  const hashedPassword = bcrypt.hashSync(password, 10);

  const insertUser = db.prepare(`
    INSERT INTO users (username, password, fullname, failed_attempts, locked_until)
    VALUES (?, ?, ?, 0, NULL)
  `);

  try {
    const result = insertUser.run(username, hashedPassword, fullname);
    return { id: result.lastInsertRowid, username, fullname };
  } catch (error) {
    if (error.message.includes("UNIQUE constraint failed")) {
      throw new Error("Username already exists");
    }
    throw error;
  }
}

export function getUserByUsername(username) {
  const getUser = db.prepare(`
    SELECT id, username, password, locked_until, failed_attempts
    FROM users
    WHERE username = ?
    LIMIT 1
  `);

  return getUser.get(username);
}

export function getUserById(userId) {
  const getUser = db.prepare(`
    SELECT id, username, fullname, email, created_at
    FROM users
    WHERE id = ?
    LIMIT 1
  `);

  return getUser.get(userId);
}

export function getAllUsers() {
  const getUsers = db.prepare(`
    SELECT id, username,
           (julianday('now') - julianday(last_seen)) * 24 * 60 < 2 AS is_online
    FROM users
    ORDER BY is_online DESC, username ASC
  `);

  return getUsers.all();
}

export function insertMessage(userId, message, receiverId = null, replyTo = null) {
  const insertMsg = db.prepare(`
    INSERT INTO messages (user_id, message, receiver_id, reply_to, created_at)
    VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
  `);

  const result = insertMsg.run(userId, message, receiverId, replyTo);
  return result.lastInsertRowid;
}

export function getMessages(userId, targetId = null) {
  let query, params;

  if (targetId) {
    // Private messages
    query = `
      SELECT m.id, m.user_id, m.receiver_id, m.message, m.created_at,
             u.username,
             r.id AS reply_id,
             r.message AS reply_text,
             ru.username AS reply_user
      FROM messages m
      JOIN users u ON m.user_id = u.id
      LEFT JOIN messages r ON m.reply_to = r.id
      LEFT JOIN users ru ON r.user_id = ru.id
      WHERE (m.user_id = ? AND m.receiver_id = ?)
         OR (m.user_id = ? AND m.receiver_id = ?)
      ORDER BY m.created_at ASC
      LIMIT 200
    `;
    params = [userId, targetId, targetId, userId];
  } else {
    // Public messages
    query = `
      SELECT m.id, m.user_id, m.receiver_id, m.message, m.created_at,
             u.username,
             r.id AS reply_id,
             r.message AS reply_text,
             ru.username AS reply_user
      FROM messages m
      JOIN users u ON m.user_id = u.id
      LEFT JOIN messages r ON m.reply_to = r.id
      LEFT JOIN users ru ON r.user_id = ru.id
      WHERE m.receiver_id IS NULL
      ORDER BY m.created_at ASC
      LIMIT 200
    `;
    params = [];
  }

  const getMsg = db.prepare(query);
  return getMsg.all(...params);
}

export function deleteMessage(messageId, userId) {
  const deleteMsg = db.prepare(`
    DELETE FROM messages
    WHERE id = ? AND user_id = ?
    LIMIT 1
  `);

  const result = deleteMsg.run(messageId, userId);
  return result.changes > 0;
}

export function isAccountLocked(userId) {
  const checkLocked = db.prepare(`
    SELECT locked_until FROM users
    WHERE id = ? AND locked_until > datetime('now')
    LIMIT 1
  `);

  return checkLocked.get(userId) !== undefined;
}

export function lockAccount(userId, minutes = 15) {
  const lockUser = db.prepare(`
    UPDATE users
    SET locked_until = datetime('now', '+' || ? || ' minutes'),
        failed_attempts = COALESCE(failed_attempts, 0) + 1
    WHERE id = ?
  `);

  lockUser.run(minutes, userId);
  logSecurityEvent("ACCOUNT_LOCKED", `UID: ${userId} | Duration: ${minutes} minutes`);
}

export function resetFailedAttempts(userId) {
  const resetAttempts = db.prepare(`
    UPDATE users SET failed_attempts = 0 WHERE id = ?
  `);

  resetAttempts.run(userId);
}

export function updateLastSeen(userId) {
  const updateSeen = db.prepare(`
    UPDATE users SET last_seen = CURRENT_TIMESTAMP WHERE id = ?
  `);

  updateSeen.run(userId);
}
