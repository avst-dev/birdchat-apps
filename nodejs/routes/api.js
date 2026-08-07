import express from 'express';
import { db } from '../utils/database.js';

const router = express.Router();

// GET /api/users - Get all users
router.get('/users', (req, res) => {
  try {
    const users = db.prepare(`
      SELECT id, username,
             CASE WHEN (julianday('now') - julianday(last_seen)) * 24 * 60 < 2 THEN 1 ELSE 0 END AS is_online
      FROM users
      ORDER BY is_online DESC, username ASC
    `).all();
    
    res.json(users);
  } catch (error) {
    console.error('Error fetching users:', error);
    res.status(500).json({ error: 'Server error' });
  }
});

// GET /api/messages - Get messages (public or with specific user)
router.get('/messages', (req, res) => {
  try {
    const userId = req.session?.user_id;
    const targetId = req.query.with ? parseInt(req.query.with) : null;
    
    if (!userId) {
      return res.status(401).json({ error: 'Unauthorized' });
    }
    
    let query, params;
    
    if (targetId) {
      // Private messages with specific user
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
    
    const messages = db.prepare(query).all(...params);
    res.json(messages);
  } catch (error) {
    console.error('Error fetching messages:', error);
    res.status(500).json({ error: 'Server error' });
  }
});

export default router;
