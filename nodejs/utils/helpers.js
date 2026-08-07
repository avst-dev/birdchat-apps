import { createRequire } from 'module';
const require = createRequire(import.meta.url);
import crypto from 'crypto';

export function generateCsrfToken() {
  return crypto.randomBytes(32).toString('hex');
}

export function verifyCsrfToken(sessionToken, submittedToken) {
  if (!sessionToken || !submittedToken) {
    logSecurityEvent('CSRF_FAILED', 'Token mismatch or missing');
    return false;
  }
  // Constant-time comparison to prevent timing attacks
  const a = Buffer.from(sessionToken, 'utf8');
  const b = Buffer.from(submittedToken, 'utf8');
  if (a.length !== b.length) {
    logSecurityEvent('CSRF_FAILED', 'Invalid token length');
    return false;
  }
  const valid = crypto.timingSafeEqual(a, b);
  if (!valid) {
    logSecurityEvent('CSRF_FAILED', 'Invalid token');
  }
  return valid;
}

// Re-export other security functions
export { 
  rateLimitCheck, 
  getRateLimitStatus, 
  sanitizeString, 
  sanitizePositiveInt, 
  escapeHtml, 
  getClientIp,
  createRateLimiter,
  logSecurityEvent
} from './security.js';
