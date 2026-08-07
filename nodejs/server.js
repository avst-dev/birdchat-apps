import express from 'express';
import session from 'express-session';
import cookieParser from 'cookie-parser';
import helmet from 'helmet';
import dotenv from 'dotenv';
import path from 'path';
import { fileURLToPath } from 'url';
import { WebSocketServer } from 'ws';
import http from 'http';

import { initializeDatabase, updateLastSeen, logSecurityEvent } from './utils/database.js';
import { generateCsrfToken, getClientIp } from './utils/helpers.js';

// Import routes
import indexRoutes from './routes/index.js';
import authRoutes from './routes/auth.js';
import chatRoutes from './routes/chat.js';
import apiRoutes from './routes/api.js';
import messageRoutes from './routes/messages.js';

dotenv.config();

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const app = express();
const PORT = process.env.PORT || 3000;
const WS_PORT = process.env.WS_PORT || 8080;

// Initialize database
initializeDatabase();

// Security middleware
app.use(helmet({
  contentSecurityPolicy: {
    directives: {
      defaultSrc: ["'self'"],
      scriptSrc: ["'self'", "'unsafe-inline'"],
      styleSrc: ["'self'", "'unsafe-inline'", "https://fonts.googleapis.com"],
      fontSrc: ["https://fonts.gstatic.com"],
      imgSrc: ["'self'", "data:"],
      connectSrc: ["'self'", `ws://localhost:${WS_PORT}`],
    },
  },
  xFrameOptions: { action: 'deny' },
}));

// Trust proxy for proper IP detection behind reverse proxies
app.set('trust proxy', 1);

// Parse cookies
app.use(cookieParser());

// Parse JSON and URL-encoded bodies
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Session configuration
app.use(session({
  secret: process.env.SESSION_SECRET || 'your-secret-key-change-in-production',
  resave: false,
  saveUninitialized: false,
  cookie: {
    secure: process.env.NODE_ENV === 'production',
    httpOnly: true,
    sameSite: 'strict',
    maxAge: 3600000 // 1 hour
  }
}));

// CSP nonce generator middleware
app.use((req, res, next) => {
  import('crypto').then(crypto => {
    res.locals.cspNonce = crypto.default.randomBytes(16).toString('base64');
    next();
  });
});

// Security headers middleware
app.use((req, res, next) => {
  res.setHeader('X-Content-Type-Options', 'nosniff');
  res.setHeader('X-XSS-Protection', '1; mode=block');
  res.setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
  res.setHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
  
  // Detect HTTPS
  const isHttps = req.secure || 
                  req.headers['x-forwarded-proto'] === 'https' ||
                  req.get('X-Forwarded-Proto') === 'https';
  
  if (isHttps) {
    res.setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
  }
  
  next();
});

// Prevent iframe embedding
app.use((req, res, next) => {
  const secFetchDest = req.headers['sec-fetch-dest'];
  if (secFetchDest === 'iframe') {
    logSecurityEvent('IFRAME_BLOCKED', `Attempted iframe loading from ${getClientIp(req)}`);
    return res.status(403).send('Forbidden');
  }
  next();
});

// Static files
app.use(express.static(path.join(__dirname, 'public')));

// Set views directory
app.set('view engine', 'ejs');
app.set('views', path.join(__dirname, 'views'));

// Routes
app.use('/', indexRoutes);
app.use('/', authRoutes);
app.use('/chat', chatRoutes);
app.use('/api', apiRoutes);

// Create HTTP server
const server = http.createServer(app);

// WebSocket server for real-time messaging
const wss = new WebSocketServer({ port: WS_PORT });
const connectedClients = new Set();

wss.on('connection', (ws) => {
  connectedClients.add(ws);
  console.log(`Client connected. Total clients: ${connectedClients.size}`);
  
  ws.on('message', (message) => {
    try {
      const data = JSON.parse(message);
      // Broadcast to all connected clients
      connectedClients.forEach(client => {
        if (client.readyState === ws.OPEN) {
          client.send(JSON.stringify(data));
        }
      });
    } catch (error) {
      console.error('WebSocket message error:', error);
    }
  });
  
  ws.on('close', () => {
    connectedClients.delete(ws);
    console.log(`Client disconnected. Total clients: ${connectedClients.size}`);
  });
  
  ws.on('error', (error) => {
    console.error('WebSocket error:', error);
    connectedClients.delete(ws);
  });
});

console.log(`🚀 BirdChat Server starting...`);
console.log(`📡 HTTP Server running on port ${PORT}`);
console.log(`🔌 WebSocket Server running on port ${WS_PORT}`);

server.listen(PORT, () => {
  console.log(`✅ Server ready at http://localhost:${PORT}`);
});

export { app, server, wss };
