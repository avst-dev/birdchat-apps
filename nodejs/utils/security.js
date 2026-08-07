import rateLimit from 'express-rate-limit';
import { logSecurityEvent } from './database.js';
import crypto from 'crypto';

// File-based cache for rate limiting (similar to PHP implementation)
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const cacheDir = path.join(__dirname, '..', 'cache');

// Ensure cache directory exists
if (!fs.existsSync(cacheDir)) {
  fs.mkdirSync(cacheDir, { recursive: true, mode: 0o700 });
}

function getCacheFile(key, ip) {
  const cacheKey = `${key}_${ip}`;
  const hash = crypto.createHash('md5').update(cacheKey).digest('hex');
  return path.join(cacheDir, `rl_${hash}.json`);
}

export function createRateLimiter(key, maxAttempts, decaySeconds) {
  return rateLimit({
    windowMs: decaySeconds * 1000,
    max: maxAttempts,
    keyGenerator: (req) => {
      return `${key}_${req.ip}`;
    },
    standardHeaders: true,
    legacyHeaders: false,
    handler: (req, res) => {
      const wait = Math.ceil(decaySeconds);
      logSecurityEvent('RATE_LIMIT_EXCEEDED', `Key: ${key} | IP: ${req.ip}`);
      res.status(429).json({ error: `Terlalu banyak percobaan. Coba lagi dalam ${wait} detik.` });
    }
  });
}

// Custom rate limiter with file caching (mimics PHP behavior exactly)
export function rateLimitCheck(key, maxAttempts, decaySeconds, req) {
  const ip = req.ip || req.connection?.remoteAddress || '127.0.0.1';
  const cacheFile = getCacheFile(key, ip);
  const now = Math.floor(Date.now() / 1000);
  
  let data = { count: 0, reset_at: now + decaySeconds };
  
  if (fs.existsSync(cacheFile)) {
    try {
      const json = JSON.parse(fs.readFileSync(cacheFile, 'utf8'));
      if (json.reset_at) {
        data = json;
      }
    } catch (e) {
      // Invalid file, use defaults
    }
  }
  
  // Reset if expired
  if (now >= data.reset_at) {
    data = { count: 0, reset_at: now + decaySeconds };
  }
  
  data.count++;
  
  // Save to cache
  fs.writeFileSync(cacheFile, JSON.stringify(data), { mode: 0o600 });
  
  if (data.count > maxAttempts) {
    const wait = Math.max(0, data.reset_at - now);
    logSecurityEvent('RATE_LIMIT_EXCEEDED', `Key: ${key} | Attempts: ${data.count} | IP: ${ip}`);
    return { blocked: true, wait, used: data.count };
  }
  
  return { blocked: false, wait: 0, used: data.count, remaining: maxAttempts - data.count };
}

export function getRateLimitStatus(key, maxAttempts, decaySeconds, req) {
  const ip = req.ip || req.connection?.remoteAddress || '127.0.0.1';
  const cacheFile = getCacheFile(key, ip);
  const now = Math.floor(Date.now() / 1000);
  
  if (!fs.existsSync(cacheFile)) {
    return { remaining: maxAttempts, wait: 0, blocked: false, used: 0 };
  }
  
  try {
    const json = JSON.parse(fs.readFileSync(cacheFile, 'utf8'));
    if (!json || !json.reset_at) {
      return { remaining: maxAttempts, wait: 0, blocked: false, used: 0 };
    }
    
    if (now >= json.reset_at) {
      return { remaining: maxAttempts, wait: 0, blocked: false, used: 0 };
    }
    
    const used = json.count || 0;
    const remaining = Math.max(0, maxAttempts - used);
    const wait = (json.reset_at || now) - now;
    const blocked = used >= maxAttempts;
    
    return { remaining, wait: Math.floor(wait), blocked, used };
  } catch (e) {
    return { remaining: maxAttempts, wait: 0, blocked: false, used: 0 };
  }
}

// CSRF Token generation and verification
export function generateCsrfToken() {
  return crypto.randomBytes(32).toString('hex');
}

export function verifyCsrfToken(sessionToken, submittedToken) {
  if (!sessionToken || !submittedToken) {
    return false;
  }
  // Constant-time comparison to prevent timing attacks
  const a = Buffer.from(sessionToken, 'utf8');
  const b = Buffer.from(submittedToken, 'utf8');
  if (a.length !== b.length) return false;
  return crypto.timingSafeEqual(a, b);
}

// Input sanitization
export function sanitizeString(input, maxLen = 500) {
  if (!input) return '';
  
  // Remove zero-width characters
  input = input.replace(/[\u200B-\u200D\uFEFF]/g, '');
  
  // Trim and limit length
  input = input.trim().substring(0, maxLen);
  
  // HTML escape
  return input
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#x27;');
}

export function sanitizePositiveInt(val) {
  const n = parseInt(val, 10);
  if (isNaN(n) || n < 1) return null;
  return n;
}

export function escapeHtml(str) {
  if (!str) return '';
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#x27;');
}

// Get client IP (handles proxies)
export function getClientIp(req) {
  const cfIp = req.headers['cf-connecting-ip'];
  const forwardedFor = req.headers['x-forwarded-for'];
  const forwarded = req.headers['x-forwarded'];
  const realIp = req.headers['x-real-ip'];
  
  let ip = cfIp || 
           (forwardedFor ? forwardedFor.split(',')[0].trim() : null) ||
           forwarded ||
           realIp ||
           req.ip ||
           req.connection?.remoteAddress ||
           '127.0.0.1';
  
  // Validate IP
  if (!ip.match(/^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$|^(?:[a-fA-F0-9:]+)$/)) {
    return '127.0.0.1';
  }
  
  return ip;
}
