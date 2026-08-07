import express from 'express';
import { 
  insertMessage, 
  deleteMessage,
  logSecurityEvent 
} from '../utils/database.js';
import { 
  verifyCsrfToken, 
  sanitizeString, 
  sanitizePositiveInt,
  getClientIp,
  rateLimitCheck
} from '../utils/helpers.js';

const router = express.Router();

// POST /api/send-message - Send or delete message
router.post('/send-message', (req, res) => {
  const userId = req.session?.user_id;
  
  if (!userId) {
    return res.status(401).json({ error: 'Unauthorized' });
  }
  
  const ip = getClientIp(req);
  
  // Rate limiting
  const rlResult = rateLimitCheck('send_message', 30, 60, req);
  if (rlResult.blocked) {
    return res.status(429).json({ error: 'Terlalu banyak pesan. Coba lagi nanti.' });
  }
  
  // Verify CSRF
  if (!verifyCsrfToken(req.session.csrfToken, req.body.csrf_token)) {
    logSecurityEvent('MESSAGE_CSRF_FAILED', `UID: ${userId}`);
    return res.status(403).json({ error: 'CSRF_INVALID' });
  }
  
  const action = (req.body.action || 'send').trim();
  
  // DELETE action
  if (action === 'delete') {
    const msgId = sanitizePositiveInt(req.body.msg_id);
    if (!msgId) {
      return res.status(400).json({ error: 'INVALID_ID' });
    }
    
    try {
      const deleted = deleteMessage(msgId, userId);
      
      if (!deleted) {
        logSecurityEvent('DELETE_UNAUTHORIZED', `UID: ${userId} attempted to delete message ${msgId}`);
        return res.status(403).json({ error: 'UNAUTHORIZED' });
      }
      
      logSecurityEvent('MESSAGE_DELETED', `UID: ${userId} | MsgID: ${msgId}`);
      return res.json({ ok: true, deleted: msgId });
    } catch (error) {
      console.error('delete_message:', error);
      logSecurityEvent('MESSAGE_DELETE_ERROR', `UID: ${userId} | Error: ${error.message}`);
      return res.status(500).json({ error: 'DB_ERROR' });
    }
  }
  
  // SEND action
  if (action !== 'send') {
    return res.status(400).json({ error: 'INVALID_ACTION' });
  }
  
  const message = (req.body.message || '').trim();
  const receiverId = req.body.receiver_id !== '' && req.body.receiver_id !== undefined
    ? sanitizePositiveInt(req.body.receiver_id) : null;
  const replyTo = req.body.reply_to !== '' && req.body.reply_to !== undefined
    ? sanitizePositiveInt(req.body.reply_to) : null;
  
  // Validate message
  if (message === '') {
    return res.status(400).json({ error: 'EMPTY_MSG' });
  }
  
  if (message.length > 1000) {
    logSecurityEvent('MESSAGE_TOO_LONG', `UID: ${userId} | Length: ${message.length}`);
    return res.status(400).json({ error: 'MSG_TOO_LONG' });
  }
  
  if (/^\s+$/.test(message)) {
    return res.status(400).json({ error: 'WHITESPACE_ONLY' });
  }
  
  // Validate receiver
  if (receiverId !== null) {
    if (receiverId === userId) {
      logSecurityEvent('MESSAGE_SELF_SEND', `UID: ${userId}`);
      return res.status(400).json({ error: 'CANNOT_MESSAGE_SELF' });
    }
    
    try {
      const { checkUserExists } = await import('../utils/database.js');
      if (!checkUserExists(receiverId)) {
        return res.status(400).json({ error: 'RECEIVER_NOT_FOUND' });
      }
    } catch (error) {
      console.error('send_message receiver check:', error);
      return res.status(500).json({ error: 'DB_ERROR' });
    }
  }
  
  // Validate reply_to
  let validReplyTo = replyTo;
  if (replyTo !== null) {
    try {
      const { db } = await import('../utils/database.js');
      const exists = db.prepare('SELECT id FROM messages WHERE id = ? LIMIT 1').get(replyTo);
      if (!exists) {
        logSecurityEvent('MESSAGE_REPLY_NOT_FOUND', `UID: ${userId} | ReplyID: ${replyTo}`);
        validReplyTo = null;
      }
    } catch (error) {
      console.error('check reply_to:', error);
      validReplyTo = null;
    }
  }
  
  // Insert message
  try {
    const newId = insertMessage(userId, message, receiverId, validReplyTo);
    
    const msgPreview = message.substring(0, 50);
    logSecurityEvent('MESSAGE_SENT', `UID: ${userId} | MsgID: ${newId} | To: ${receiverId || 'PUBLIC'} | Preview: ${msgPreview}`);
    
    res.json({ ok: true, id: newId });
  } catch (error) {
    console.error('send_message insert:', error);
    logSecurityEvent('MESSAGE_INSERT_ERROR', `UID: ${userId} | Error: ${error.message}`);
    res.status(500).json({ error: 'DB_ERROR' });
  }
});

export default router;
