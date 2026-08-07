import express from 'express';
import bcrypt from 'bcryptjs';
import { 
  getUserForLogin, 
  createUser as dbCreateUser, 
  resetFailedAttempts, 
  lockAccount, 
  updateLastSeen,
  checkUserExists,
  isAccountLocked,
  logSecurityEvent 
} from '../utils/database.js';
import { 
  generateCsrfToken, 
  verifyCsrfToken, 
  sanitizeString, 
  getClientIp,
  rateLimitCheck,
  getRateLimitStatus
} from '../utils/helpers.js';

const router = express.Router();

// GET /login - Show login page
router.get('/login', (req, res) => {
  if (req.session?.user_id) {
    return res.redirect('/chat');
  }
  
  const csrfToken = generateCsrfToken();
  req.session.csrfToken = csrfToken;
  
  res.render('login', { 
    title: 'BIRDCHAT — Login',
    csrfToken,
    error: null
  });
});

// POST /login - Handle login
router.post('/login', (req, res) => {
  const ip = getClientIp(req);
  const userAgent = req.headers['user-agent'] || '';
  
  // Rate limiting
  const rlResult = rateLimitCheck('login', 5, 300, req);
  if (rlResult.blocked) {
    logSecurityEvent('RATE_LIMIT_EXCEEDED', `Login | IP: ${ip}`);
    return res.status(429).json({ 
      error: `Terlalu banyak percobaan. Coba lagi dalam ${rlResult.wait} detik.` 
    });
  }
  
  const submittedToken = req.body.csrf_token;
  if (!verifyCsrfToken(req.session.csrfToken, submittedToken)) {
    logSecurityEvent('LOGIN_CSRF_FAILED', 'Token validation failed');
    return res.status(403).json({ error: 'Permintaan tidak valid. Muat ulang halaman.' });
  }
  
  const username = sanitizeString(req.body.username, 50);
  const password = req.body.password || '';
  
  if (username.length < 3 || password.length < 1) {
    logSecurityEvent('LOGIN_INVALID_FORMAT', 'Invalid username/password format');
    return res.status(400).json({ error: 'Kredensial tidak valid.' });
  }
  
  try {
    const user = getUserForLogin(username);
    
    // Use dummy hash for timing attack mitigation
    const dummyHash = '$2y$12$invalidhashfortimingatk12345678901234567890123456789';
    const hashToVerify = user ? user.password : dummyHash;
    
    // Verify password with constant-time comparison
    const valid = bcrypt.compareSync(password, hashToVerify);
    
    if (user && valid) {
      // Check if account is locked
      if (user.locked_until) {
        const lockedUntil = new Date(user.locked_until);
        if (lockedUntil > new Date()) {
          logSecurityEvent('LOGIN_LOCKED_ACCOUNT', `UID: ${user.id} | Locked until ${user.locked_until}`);
          // Variable timing delay
          setTimeout(() => {}, Math.floor(Math.random() * 200) + 200);
          return res.status(403).json({ error: 'Akses tidak dapat diberikan. Coba lagi nanti.' });
        }
      }
      
      // Successful login
      req.session.regenerate((err) => {
        if (err) {
          console.error('Session regeneration error:', err);
        }
        
        req.session.user_id = user.id;
        req.session.username = user.username;
        req.session.ip = ip;
        req.session.uaHash = require('crypto').createHash('sha256').update(userAgent).digest('hex');
        req.session.created = Date.now();
        req.session.lastActivity = Date.now();
        req.session.csrfToken = generateCsrfToken();
        
        // Reset failed attempts
        resetFailedAttempts(user.id);
        
        // Update last seen
        updateLastSeen(user.id);
        
        logSecurityEvent('LOGIN_SUCCESS', `UID: ${user.id} | IP: ${ip}`, ip, user.id, userAgent);
        
        res.json({ success: true, redirect: '/chat' });
      });
    } else {
      // Failed login
      if (user) {
        const failedAttempts = (user.failed_attempts || 0) + 1;
        
        if (failedAttempts >= 5) {
          lockAccount(user.id, 15);
          logSecurityEvent('LOGIN_LOCKOUT', `UID: ${user.id} | Failed attempts: ${failedAttempts}`);
        } else {
          // Update failed attempts
          try {
            const { db } = await import('../utils/database.js');
            db.prepare('UPDATE users SET failed_attempts = ? WHERE id = ?').run(failedAttempts, user.id);
          } catch (e) {
            console.error('Failed to update attempts:', e);
          }
          logSecurityEvent('LOGIN_FAILED', `UID: ${user.id} | Failed attempts: ${failedAttempts}`);
        }
      } else {
        logSecurityEvent('LOGIN_FAILED', `Unknown username: ${username}`);
      }
      
      // Variable timing delay
      setTimeout(() => {}, Math.floor(Math.random() * 200) + 200);
      
      // Generic error message
      res.status(401).json({ error: 'Kredensial tidak valid.' });
    }
  } catch (error) {
    console.error('Login DB error:', error);
    res.status(500).json({ error: 'Database error' });
  }
});

// GET /register - Show registration page
router.get('/register', (req, res) => {
  if (req.session?.user_id) {
    return res.redirect('/chat');
  }
  
  const csrfToken = generateCsrfToken();
  req.session.csrfToken = csrfToken;
  
  const rlInfo = getRateLimitStatus('register', 3, 3600, req);
  
  res.render('register', {
    title: 'BIRDCHAT — Daftar',
    csrfToken,
    rlInfo,
    message: null,
    msgType: null
  });
});

// POST /register - Handle registration
router.post('/register', async (req, res) => {
  const ip = getClientIp(req);
  const userAgent = req.headers['user-agent'] || '';
  
  const rlInfo = getRateLimitStatus('register', 3, 3600, req);
  
  if (rlInfo.blocked) {
    logSecurityEvent('REGISTER_RATE_LIMITED', `IP: ${ip}`);
    return res.status(429).json({ 
      error: `Terlalu banyak percobaan. Tunggu ${rlInfo.wait} detik.` 
    });
  }
  
  rateLimitCheck('register', 3, 3600, req);
  
  if (!verifyCsrfToken(req.session.csrfToken, req.body.csrf_token)) {
    logSecurityEvent('REGISTER_CSRF_FAILED', `IP: ${ip}`);
    return res.status(403).json({ error: 'Permintaan tidak valid. Muat ulang halaman.' });
  }
  
  const username = sanitizeString(req.body.username, 50);
  const password = req.body.password || '';
  const fullname = sanitizeString(req.body.fullname, 100);
  
  // Validate username
  if (!/^[a-zA-Z0-9_]{3,50}$/.test(username)) {
    logSecurityEvent('REGISTER_INVALID_USERNAME', `Username: ${username} | IP: ${ip}`);
    return res.status(400).json({ error: 'Username: hanya huruf, angka, underscore (3–50 karakter).' });
  }
  
  if (password.length < 8) {
    return res.status(400).json({ error: 'Password minimal 8 karakter.' });
  }
  
  if (password.length > 128) {
    return res.status(400).json({ error: 'Password terlalu panjang (maks 128 karakter).' });
  }
  
  try {
    const { userExistsByUsername } = await import('../utils/database.js');
    
    if (userExistsByUsername(username)) {
      logSecurityEvent('REGISTER_DUP_USERNAME', `Username: ${username} | IP: ${ip}`);
      // Generic success message (don't reveal user exists)
      return res.json({ 
        success: true, 
        message: "Registrasi berhasil! Silakan login →" 
      });
    }
    
    // Create user
    const newUser = dbCreateUser(username, password, fullname);
    
    logSecurityEvent('REGISTER_SUCCESS', `Username: ${username} | IP: ${ip}`);
    
    res.json({ 
      success: true, 
      message: "Registrasi berhasil! <a href='/login'>Silakan login →</a>" 
    });
  } catch (error) {
    console.error('Register error:', error);
    logSecurityEvent('REGISTER_DB_ERROR', `Error: ${error.message}`);
    res.status(500).json({ error: 'Terjadi kesalahan. Coba lagi nanti.' });
  }
});

// GET /logout - Handle logout
router.get('/logout', (req, res) => {
  if (req.session?.user_id) {
    try {
      updateLastSeen(req.session.user_id);
    } catch (error) {
      console.error('Logout DB error:', error);
    }
  }
  
  req.session.destroy((err) => {
    if (err) console.error('Session destroy error:', err);
  });
  
  res.clearCookie('connect.sid');
  res.redirect('/login');
});

export default router;
