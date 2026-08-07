import express from 'express';
import { 
  updateLastSeen, 
  checkUserExists, 
  isAccountLocked,
  getAllUsers,
  getMessages,
  insertMessage,
  deleteMessage,
  logSecurityEvent
} from '../utils/database.js';
import { 
  generateCsrfToken, 
  verifyCsrfToken, 
  sanitizeString, 
  sanitizePositiveInt,
  escapeHtml,
  getClientIp,
  rateLimitCheck
} from '../utils/helpers.js';

const router = express.Router();

// Authentication middleware for chat routes
function requireAuth(req, res, next) {
  if (!req.session?.user_id || !req.session?.username) {
    logSecurityEvent('AUTH_FAILED', `Missing session data from ${getClientIp(req)}`);
    return res.redirect('/login');
  }
  
  // Validate session security
  const currentIp = getClientIp(req);
  const currentUa = req.headers['user-agent'] || '';
  const crypto = require('crypto');
  
  if (req.session.ip && req.session.ip !== currentIp) {
    logSecurityEvent('SESSION_IP_MISMATCH', `Expected: ${req.session.ip} | Got: ${currentIp}`);
    req.session.destroy();
    return res.redirect('/login?reason=ip_mismatch');
  }
  
  if (req.session.uaHash && req.session.uaHash !== crypto.createHash('sha256').update(currentUa).digest('hex')) {
    logSecurityEvent('SESSION_UA_MISMATCH', 'User-Agent changed');
    req.session.destroy();
    return res.redirect('/login?reason=ua_mismatch');
  }
  
  const sessionLifetime = 3600;
  if (req.session.lastActivity && (Date.now() - req.session.lastActivity) > sessionLifetime * 1000) {
    logSecurityEvent('SESSION_TIMEOUT', `Inactive for ${sessionLifetime} seconds`);
    req.session.destroy();
    return res.redirect('/login?reason=timeout');
  }
  req.session.lastActivity = Date.now();
  
  // Regenerate session every 15 minutes
  if (!req.session.regeneratedAt) {
    req.session.regeneratedAt = Date.now();
  }
  if ((Date.now() - req.session.regeneratedAt) > 900000) {
    req.session.regenerate((err) => {
      if (!err) {
        req.session.regeneratedAt = Date.now();
        logSecurityEvent('SESSION_REGENERATED', `UID: ${req.session.user_id}`);
      }
    });
  }
  
  // Check if user still exists
  if (!checkUserExists(req.session.user_id)) {
    logSecurityEvent('SESSION_USER_DELETED', `UID: ${req.session.user_id} no longer exists`);
    req.session.destroy();
    return res.redirect('/login?reason=user_deleted');
  }
  
  // Check if account is locked
  if (isAccountLocked(req.session.user_id)) {
    logSecurityEvent('LOCKED_ACCOUNT_ACCESS', `UID: ${req.session.user_id} attempted access while locked`);
    req.session.destroy();
    return res.redirect('/login?reason=account_locked');
  }
  
  // Update last seen
  try {
    updateLastSeen(req.session.user_id);
  } catch (error) {
    console.error('chat last_seen:', error);
  }
  
  next();
}

// GET /chat - Main chat interface
router.get('/', requireAuth, (req, res) => {
  const csrfToken = generateCsrfToken();
  req.session.csrfToken = csrfToken;
  
  res.render('chat', {
    title: 'BIRDCHAT 🐦',
    username: req.session.username,
    userId: req.session.user_id,
    csrfToken,
    cspNonce: res.locals.cspNonce || ''
  });
});

export default router;
