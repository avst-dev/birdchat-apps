<?php
//path tambal choocie
require_once __DIR__ . '/scrp.php';
// ============================================================
//  chat.php — BIRDCHAT Main Interface (Fully Refactored)
// ============================================================
require_once __DIR__ . '/auth_check.php';

try {
    $conn->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?")
         ->execute([(int)$_SESSION['user_id']]);
} catch (PDOException $e) {
    error_log("chat last_seen: " . $e->getMessage());
}

$csrf_token  = generateCsrfToken();
$jsUsername  = json_encode($_SESSION['username'],
                   JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$jsCsrfToken = json_encode($csrf_token,
                   JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$jsUserId    = json_encode((int)$_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="theme-color" content="#05070a">
<meta name="referrer" content="no-referrer">
<title>BIRDCHAT 🐦</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'><rect width='64' height='64' rx='16' fill='%2305070a'/><text y='44' x='8' font-size='40'>🐦</text></svg>">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════════
   RESET & VARIABLES
═══════════════════════════════════════════════════ */
:root {
    --c:          #00ffcc;
    --c2:         #00c9f5;
    --c-glow:     rgba(0,255,204,0.3);
    --bg:         #060810;
    --panel:      #0d1018;
    --panel2:     #111520;
    --border:     rgba(255,255,255,0.06);
    --border2:    rgba(0,255,204,0.15);
    --text:       #dde6f0;
    --muted:      #6b7a8d;
    --red:        #ff3b6b;
    --me-bg:      rgba(0,255,204,0.08);
    --me-border:  rgba(0,255,204,0.18);
    --them-bg:    rgba(255,255,255,0.04);
    --them-border:rgba(255,255,255,0.07);
    --sidebar-w:  290px;
    --hdr-h:      60px;
    --input-h:    72px;
    --radius:     18px;
    --ff-head:    'Syne', sans-serif;
    --ff-body:    'DM Sans', sans-serif;
}

*, *::before, *::after {
    margin: 0; padding: 0;
    box-sizing: border-box;
    -webkit-tap-highlight-color: transparent;
    -webkit-text-size-adjust: 100%;
    text-size-adjust: 100%;
}

html, body { height: 100%; }

body {
    font-family: var(--ff-body);
    background: var(--bg);
    background-image:
        radial-gradient(ellipse 60% 40% at 10% 20%, rgba(0,255,204,0.04) 0%, transparent 60%),
        radial-gradient(ellipse 50% 50% at 90% 80%, rgba(0,100,255,0.04) 0%, transparent 60%);
    color: var(--text);
    display: flex;
    overflow: hidden;
    height: 100dvh;
    padding: env(safe-area-inset-top,0) env(safe-area-inset-right,0) 0 env(safe-area-inset-left,0);
}

::-webkit-scrollbar { width: 3px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }

/* ═══════════════════════════════════════════════════
   SIDEBAR
═══════════════════════════════════════════════════ */
#sidebar {
    width: var(--sidebar-w);
    flex-shrink: 0;
    background: var(--panel);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    z-index: 200;
    transition: transform 0.38s cubic-bezier(0.22,1,0.36,1);
    will-change: transform;
}

.sb-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 16px;
    height: var(--hdr-h);
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
    gap: 10px;
}

.sb-logo {
    font-family: var(--ff-head);
    font-weight: 800;
    font-size: 1.2rem;
    letter-spacing: 3px;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 8px;
}
.sb-logo .bird { font-size: 1.4rem; }
.sb-logo span  { color: var(--c); }

#btn-close-sb {
    display: none;
    background: none;
    border: none;
    color: var(--muted);
    cursor: pointer;
    width: 36px; height: 36px;
    border-radius: 10px;
    align-items: center; justify-content: center;
    font-size: 18px;
    transition: color 0.2s, background 0.2s;
    touch-action: manipulation;
    flex-shrink: 0;
}
#btn-close-sb:hover { color: var(--red); background: rgba(255,59,107,0.1); }

.sb-section-label {
    padding: 16px 18px 6px;
    font-size: 9.5px;
    font-weight: 700;
    letter-spacing: 2px;
    text-transform: uppercase;
    color: var(--muted);
    font-family: var(--ff-head);
}

#user-list {
    flex: 1;
    overflow-y: auto;
    padding: 4px 8px 8px;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior: contain;
}

.user-item {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 11px 12px;
    min-height: 50px;
    border-radius: 13px;
    border: 1px solid transparent;
    cursor: pointer;
    transition: background 0.18s, border-color 0.18s, transform 0.18s;
    margin-bottom: 2px;
    -webkit-user-select: none; user-select: none;
    touch-action: manipulation;
    position: relative;
    overflow: hidden;
}
.user-item::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 13px;
    background: linear-gradient(135deg, rgba(0,255,204,0.06), transparent);
    opacity: 0;
    transition: opacity 0.2s;
}
.user-item:hover  { background: rgba(255,255,255,0.04); border-color: var(--border); }
.user-item:hover::before { opacity: 1; }
.user-item:active { transform: scale(0.98); }
.user-item.active { background: rgba(0,255,204,0.07); border-color: var(--border2); }

.user-item .uname {
    flex: 1;
    font-size: 14px;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Online dot */
.dot {
    width: 9px; height: 9px;
    border-radius: 50%;
    flex-shrink: 0;
    position: relative;
}
.dot.online {
    background: var(--c);
    box-shadow: 0 0 0 2px rgba(0,255,204,0.2);
}
.dot.online::after {
    content: '';
    position: absolute;
    inset: -3px;
    border-radius: 50%;
    background: var(--c);
    opacity: 0.25;
    animation: pulse-dot 2s ease-in-out infinite;
}
@keyframes pulse-dot {
    0%,100% { transform: scale(1); opacity: 0.25; }
    50%      { transform: scale(2.2); opacity: 0; }
}
.dot.offline { background: #2d3446; }

/* Notification badge */
.notif-badge {
    background: linear-gradient(135deg, var(--c), var(--c2));
    color: #060810;
    font-size: 10px;
    font-weight: 800;
    font-family: var(--ff-head);
    min-width: 20px;
    height: 20px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    padding: 0 5px;
    animation: badge-pop 0.35s cubic-bezier(0.175,0.885,0.32,1.275) forwards;
    flex-shrink: 0;
}
@keyframes badge-pop {
    from { transform: scale(0); opacity: 0; }
    to   { transform: scale(1); opacity: 1; }
}

/* ═══════════════════════════════════════════════════
   OVERLAY
═══════════════════════════════════════════════════ */
#sidebar-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(3px);
    -webkit-backdrop-filter: blur(3px);
    z-index: 150;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.35s ease;
}
#sidebar-overlay.open { opacity: 1; pointer-events: auto; }

/* ═══════════════════════════════════════════════════
   MAIN CHAT
═══════════════════════════════════════════════════ */
#main {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
    position: relative;
}

/* ── Header ── */
#chat-header {
    height: var(--hdr-h);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 18px;
    background: rgba(13,16,24,0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
    z-index: 10;
    gap: 12px;
}

#btn-menu {
    background: none; border: none;
    color: var(--c);
    cursor: pointer;
    width: 40px; height: 40px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 10px;
    font-size: 22px;
    flex-shrink: 0;
    transition: background 0.2s, transform 0.2s;
    touch-action: manipulation;
}
#btn-menu:hover  { background: rgba(0,255,204,0.08); }
#btn-menu:active { transform: scale(0.9); }

#chat-title-wrap {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    overflow: hidden;
}

#chat-status-dot {
    width: 9px; height: 9px;
    border-radius: 50%;
    background: var(--c);
    box-shadow: 0 0 8px var(--c);
    flex-shrink: 0;
    animation: pulse-dot 2s ease-in-out infinite;
}
#chat-status-dot.offline-dot {
    background: var(--muted);
    box-shadow: none;
    animation: none;
}

#chat-title {
    font-family: var(--ff-head);
    font-weight: 700;
    font-size: 1rem;
    letter-spacing: 0.3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
#chat-title.public  { color: var(--c); }
#chat-title.private {
    background: linear-gradient(270deg, #ff3b6b, #b040ff, #00ffcc, #ff3b6b);
    background-size: 300%;
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    animation: grad 4s ease infinite;
}
@keyframes grad { 0%,100% { background-position:0% 50%; } 50% { background-position:100% 50%; } }

#chat-subtitle {
    font-size: 11px;
    color: var(--muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.logout-btn {
    display: flex; align-items: center; gap: 6px;
    color: var(--red);
    text-decoration: none;
    font-size: 11px;
    font-weight: 700;
    font-family: var(--ff-head);
    letter-spacing: 0.8px;
    padding: 0 12px;
    height: 34px;
    border: 1px solid rgba(255,59,107,0.25);
    border-radius: 10px;
    flex-shrink: 0;
    transition: background 0.2s, border-color 0.2s;
    touch-action: manipulation;
}
.logout-btn:hover { background: rgba(255,59,107,0.1); border-color: rgba(255,59,107,0.5); }

/* ── Messages area ── */
#chat-display {
    flex: 1;
    overflow-y: auto;
    padding: 20px 18px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior: contain;
}

/* ── Date separator ── */
.date-sep {
    display: flex; align-items: center; gap: 12px;
    margin: 14px 0 6px;
}
.date-sep::before,.date-sep::after {
    content: ''; flex: 1;
    height: 1px; background: var(--border);
}
.date-sep span {
    font-size: 10px; font-weight: 700;
    font-family: var(--ff-head);
    letter-spacing: 1.5px;
    color: var(--muted);
    text-transform: uppercase;
    white-space: nowrap;
}

/* ── Bubble ── */
.bubble-wrap {
    display: flex;
    flex-direction: column;
    margin-bottom: 2px;
    max-width: 72%;
    position: relative;
    outline: none;
}
.bubble-wrap.me    { align-self: flex-end; align-items: flex-end; }
.bubble-wrap.other { align-self: flex-start; align-items: flex-start; }
.bubble-wrap.bubble-new {
    animation: slideIn 0.32s cubic-bezier(0.22,1,0.36,1) both;
}
.bubble-wrap.me.bubble-new    { animation-name: slideInRight; }
.bubble-wrap.other.bubble-new { animation-name: slideInLeft; }

@keyframes slideInRight {
    from { opacity:0; transform: translateX(18px) scale(0.96); }
    to   { opacity:1; transform: translateX(0)    scale(1); }
}
@keyframes slideInLeft {
    from { opacity:0; transform: translateX(-18px) scale(0.96); }
    to   { opacity:1; transform: translateX(0)     scale(1); }
}

.bubble-name {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    font-family: var(--ff-head);
    margin-bottom: 3px;
    padding: 0 4px;
}
.bubble-wrap.me    .bubble-name { color: var(--c); opacity: 0.7; }
.bubble-wrap.other .bubble-name { color: var(--muted); }

.bubble {
    padding: 10px 14px 8px;
    font-size: 14.5px;
    line-height: 1.55;
    word-break: break-word;
    overflow-wrap: break-word;
    position: relative;
    cursor: default;
}
.bubble-wrap.me    .bubble {
    background: var(--me-bg);
    border: 1px solid var(--me-border);
    border-radius: 18px 18px 4px 18px;
}
.bubble-wrap.other .bubble {
    background: var(--them-bg);
    border: 1px solid var(--them-border);
    border-radius: 18px 18px 18px 4px;
}

/* ── Reply preview inside bubble ── */
.reply-preview {
    background: rgba(0,0,0,0.25);
    border-left: 3px solid var(--c);
    border-radius: 8px;
    padding: 6px 10px;
    margin-bottom: 8px;
    font-size: 12px;
}
.reply-preview .rp-name {
    color: var(--c);
    font-weight: 700;
    font-size: 10px;
    letter-spacing: 0.5px;
    font-family: var(--ff-head);
    margin-bottom: 2px;
}
.reply-preview .rp-text {
    color: var(--muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 260px;
}

/* ── Bubble time ── */
.bubble-time {
    font-size: 10px;
    color: var(--muted);
    margin-top: 4px;
    padding: 0 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* ── Bubble actions (appear on hover/long-press) ── */
.bubble-actions {
    display: flex;
    gap: 4px;
    opacity: 0;
    transition: opacity 0.2s;
    pointer-events: none;
    position: absolute;
    top: 6px;
    z-index: 5;
}
.bubble-wrap.me    .bubble-actions { left: -100px; }
.bubble-wrap.other .bubble-actions { right: -100px; }
.bubble-wrap:hover .bubble-actions,
.bubble-wrap.actions-visible .bubble-actions {
    opacity: 1;
    pointer-events: auto;
}

.act-btn {
    background: var(--panel2);
    border: 1px solid var(--border);
    color: var(--muted);
    width: 30px; height: 30px;
    border-radius: 8px;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px;
    transition: background 0.15s, color 0.15s, transform 0.15s;
    touch-action: manipulation;
}
.act-btn:hover { background: var(--panel); color: var(--text); }
.act-btn.del:hover { color: var(--red); }
.act-btn:active { transform: scale(0.88); }

/* ── Reply bar (above input) ── */
#reply-bar {
    display: none;
    align-items: center;
    gap: 10px;
    padding: 8px 16px;
    background: var(--panel2);
    border-top: 1px solid var(--border);
    animation: slideUp 0.2s ease;
}
#reply-bar.show { display: flex; }
@keyframes slideUp {
    from { opacity:0; transform: translateY(6px); }
    to   { opacity:1; transform: translateY(0); }
}
#reply-bar .rb-icon { color: var(--c); font-size: 16px; flex-shrink: 0; }
#reply-bar .rb-content { flex: 1; min-width: 0; }
#reply-bar .rb-name { font-size: 10px; font-weight: 700; color: var(--c); font-family: var(--ff-head); letter-spacing: 0.5px; }
#reply-bar .rb-text {
    font-size: 12px; color: var(--muted);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
#btn-cancel-reply {
    background: none; border: none;
    color: var(--muted); font-size: 18px;
    cursor: pointer; width: 32px; height: 32px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 8px;
    transition: color 0.2s;
    flex-shrink: 0;
    touch-action: manipulation;
}
#btn-cancel-reply:hover { color: var(--red); }

/* ── Emoji picker ── */
#emoji-picker {
    position: absolute;
    bottom: calc(100% + 8px);
    left: 16px;
    background: var(--panel2);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 12px;
    z-index: 100;
    display: none;
    box-shadow: 0 8px 32px rgba(0,0,0,0.5);
    animation: fadeScaleUp 0.2s cubic-bezier(0.22,1,0.36,1) both;
    max-width: min(340px, calc(100vw - 32px));
}
#emoji-picker.show { display: block; }
@keyframes fadeScaleUp {
    from { opacity:0; transform: scale(0.92) translateY(8px); transform-origin: bottom left; }
    to   { opacity:1; transform: scale(1)    translateY(0); }
}
.emoji-grid {
    display: grid;
    grid-template-columns: repeat(8, 1fr);
    gap: 4px;
}
.emoji-btn {
    background: none; border: none;
    font-size: 20px;
    width: 36px; height: 36px;
    border-radius: 8px;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: background 0.15s, transform 0.15s;
    touch-action: manipulation;
}
.emoji-btn:hover  { background: rgba(255,255,255,0.08); transform: scale(1.2); }
.emoji-btn:active { transform: scale(0.9); }

/* ── Input bar ── */
.input-area {
    flex-shrink: 0;
    background: rgba(13,16,24,0.85);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-top: 1px solid var(--border);
    position: relative;
    z-index: 20;
}

.input-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px 14px;
    padding-bottom: calc(11px + env(safe-area-inset-bottom, 0px));
}

.input-action-btn {
    background: none; border: none;
    color: var(--muted);
    width: 40px; height: 40px;
    border-radius: 12px;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
    transition: color 0.2s, background 0.2s;
    touch-action: manipulation;
}
.input-action-btn:hover { color: var(--c); background: rgba(0,255,204,0.06); }
.input-action-btn.active { color: var(--c); }

#msg-input {
    flex: 1;
    min-width: 0;
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--border);
    color: var(--text);
    font-size: 16px; /* iOS: ≥16px = no auto-zoom */
    font-family: var(--ff-body);
    padding: 12px 16px;
    border-radius: 26px;
    outline: none;
    -webkit-appearance: none; appearance: none;
    transition: border-color 0.25s, box-shadow 0.25s;
    touch-action: manipulation;
}
#msg-input:focus {
    border-color: rgba(0,255,204,0.4);
    box-shadow: 0 0 0 3px rgba(0,255,204,0.07);
}
#msg-input::placeholder { color: var(--muted); }

#btn-send {
    width: 46px; height: 46px;
    flex-shrink: 0;
    border: none;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--c), var(--c2));
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 2px 12px rgba(0,255,204,0.25);
    transition: transform 0.2s, box-shadow 0.2s, opacity 0.2s;
    touch-action: manipulation;
}
#btn-send svg {
    width: 18px; height: 18px;
    fill: none; stroke: #060810;
    stroke-width: 2.5;
    stroke-linecap: round; stroke-linejoin: round;
    transform: translateX(-1px) translateY(1px);
    pointer-events: none;
}
#btn-send:hover  { transform: scale(1.08); box-shadow: 0 4px 18px rgba(0,255,204,0.4); }
#btn-send:active { transform: scale(0.9); }
#btn-send.loading { opacity: 0.5; pointer-events: none; }

/* ── Scroll to bottom ── */
#scroll-btn {
    position: absolute;
    bottom: calc(var(--input-h) + 12px);
    right: 16px;
    width: 38px; height: 38px;
    background: var(--panel2);
    border: 1px solid var(--border2);
    border-radius: 50%;
    cursor: pointer;
    display: none;
    align-items: center; justify-content: center;
    color: var(--c);
    font-size: 16px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.4);
    transition: transform 0.2s;
    z-index: 15;
    touch-action: manipulation;
}
#scroll-btn.visible { display: flex; animation: fadeIn 0.2s ease; }
#scroll-btn:hover { transform: scale(1.1); }

/* ── Delete animation ── */
.bubble-wrap.deleting {
    animation: deleteOut 0.3s ease forwards;
    pointer-events: none;
}
@keyframes deleteOut {
    to { opacity:0; transform: scale(0.85); max-height: 0; margin: 0; padding: 0; }
}

/* ── Toast ── */
#toast {
    position: fixed;
    bottom: 90px;
    left: 50%;
    transform: translateX(-50%) translateY(0);
    background: var(--panel2);
    border: 1px solid var(--border2);
    color: var(--text);
    padding: 10px 20px;
    border-radius: 40px;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
    z-index: 999;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.25s, transform 0.25s;
}
#toast.show { opacity: 1; transform: translateX(-50%) translateY(-6px); }

/* ═══════════════════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════════════════ */
@media (max-width: 768px) {
    #sidebar {
        position: fixed;
        top: 0; left: 0; bottom: 0;
        height: 100dvh;
        transform: translateX(-100%);
        width: min(var(--sidebar-w), 85vw);
        z-index: 200;
    }
    #sidebar.open        { transform: translateX(0); }
    #sidebar-overlay     { display: block; }
    #btn-close-sb        { display: flex; }
    .bubble-wrap         { max-width: 85%; }
    #chat-display        { padding: 14px 12px; }
    .input-row           { padding: 9px 10px; }
    .emoji-grid          { grid-template-columns: repeat(7, 1fr); }
    .bubble-wrap.me    .bubble-actions { left: -88px; }
    .bubble-wrap.other .bubble-actions { right: -88px; }
}

@media (max-width: 380px) {
    .bubble-wrap         { max-width: 92%; }
    .bubble              { font-size: 13.5px; padding: 9px 12px; }
    #msg-input           { font-size: 15px; padding: 11px 13px; }
    #btn-send            { width: 42px; height: 42px; }
    .emoji-grid          { grid-template-columns: repeat(6, 1fr); }
    .emoji-btn           { width: 33px; height: 33px; font-size: 18px; }
    .logout-btn          { padding: 0 9px; font-size: 10px; }
}

@media (max-height: 500px) and (max-width: 900px) {
    #chat-header { height: 50px; }
    .input-row   { padding: 7px 10px; }
    #chat-display { padding: 8px; }
}

@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: 0.01ms !important;
        transition-duration: 0.01ms !important;
    }
}
</style>
</head>
<body>

<!-- Overlay -->
<div id="sidebar-overlay" aria-hidden="true"></div>

<!-- Sidebar -->
<aside id="sidebar" aria-label="Navigasi">
    <div class="sb-header">
        <div class="sb-logo">
            <span class="bird">🐦</span>
            BIRD<span>CHAT</span>
        </div>
        <button id="btn-close-sb" aria-label="Tutup sidebar">✕</button>
    </div>

    <div style="padding:6px 8px 4px;">
        <div class="user-item" id="item-public" role="button" tabindex="0" aria-label="Public Chat">
            <span class="dot online"></span>
            <span class="uname" style="font-weight:700;color:var(--c);">🌍 Public Chat</span>
        </div>
    </div>

    <div class="sb-section-label">Online Users</div>
    <div id="user-list" role="list"></div>

    <div style="padding:12px 14px;border-top:1px solid var(--border);flex-shrink:0;">
        <div style="font-size:11px;color:var(--muted);text-align:center;">
            Masuk sebagai <strong style="color:var(--c);"><?= e($_SESSION['username']) ?></strong>
        </div>
    </div>
</aside>

<!-- Main Chat -->
<main id="main">
    <header id="chat-header">
        <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;">
            <button id="btn-menu" aria-label="Menu" aria-expanded="false" aria-controls="sidebar">
                <svg width="20" height="14" viewBox="0 0 20 14" fill="none">
                    <rect width="20" height="2" rx="1" fill="currentColor"/>
                    <rect y="6" width="14" height="2" rx="1" fill="currentColor"/>
                    <rect y="12" width="20" height="2" rx="1" fill="currentColor"/>
                </svg>
            </button>
            <div id="chat-title-wrap">
                <div>
                    <div id="chat-title" class="public">Public Chat</div>
                    <div id="chat-subtitle">Semua pengguna</div>
                </div>
            </div>
        </div>
        <a href="logout" class="logout-btn" id="btn-logout" aria-label="Logout">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            KELUAR
        </a>
    </header>

    <div id="chat-display" role="log" aria-live="polite" aria-label="Pesan chat"></div>

    <div id="scroll-btn" aria-label="Scroll ke bawah">↓</div>

    <div class="input-area">
        <div id="reply-bar" role="status" aria-label="Membalas pesan">
            <span class="rb-icon">↩️</span>
            <div class="rb-content">
                <div class="rb-name" id="rb-name"></div>
                <div class="rb-text" id="rb-text"></div>
            </div>
            <button id="btn-cancel-reply" aria-label="Batal reply">✕</button>
        </div>

        <!-- Emoji Picker -->
        <div id="emoji-picker" role="dialog" aria-label="Pilih emoji">
            <div class="emoji-grid" id="emoji-grid"></div>
        </div>

        <div class="input-row">
            <button class="input-action-btn" id="btn-emoji" aria-label="Emoji" title="Emoji">😊</button>
            <input type="text" id="msg-input"
                   placeholder="Ketik pesan..."
                   autocomplete="off" maxlength="1000"
                   aria-label="Tulis pesan" enterkeyhint="send">
            <button id="btn-send" aria-label="Kirim">
                <svg viewBox="0 0 24 24">
                    <line x1="22" y1="2" x2="11" y2="13"/>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                </svg>
            </button>
        </div>
    </div>
</main>

<!-- Toast -->
<div id="toast" role="status" aria-live="polite"></div>

<script nonce="<?= getCspNonce() ?>">
(function () {
'use strict';

/* ── Constants from PHP ── */
var CSRF    = <?= $jsCsrfToken ?>;
var ME      = <?= $jsUsername ?>;
var MY_UID  = <?= $jsUserId ?>;

/* ── Emoji list ── */
var EMOJIS = [
    '😀','😂','🥹','😍','🤩','😎','🥳','😭',
    '😡','🤔','😴','🤯','🥰','😇','🤣','😅',
    '👍','👏','🙌','🫶','❤️','🔥','💯','✨',
    '🎉','🎊','💪','🫡','👀','💀','🤝','🙏',
    '😏','😬','🤭','🫠','🥺','😤','😵','🤗',
    '🐦','🌍','🚀','⚡','🌊','🍕','🎮','🎵'
];

/* ── State ── */
var receiver    = null;
var chatName    = 'Public Chat';
var lastCount   = 0;
var unreadMap   = {}; // uid → count
var sending     = false;
var lastSentAt  = 0;
var COOLDOWN    = 600;
var cachedUsers = [];
var lastUsersHash = '';
var lastRecv    = undefined;
var msgPending  = false;
var replyTo     = null; // { id, name, text }
var longPressTimer = null;
var emojiOpen   = false;

/* ── DOM ── */
var $  = function(id) { return document.getElementById(id); };
var elSidebar   = $('sidebar');
var elOverlay   = $('sidebar-overlay');
var elBtnMenu   = $('btn-menu');
var elBtnClose  = $('btn-close-sb');
var elTitle     = $('chat-title');
var elSubtitle  = $('chat-subtitle');
var elDisplay   = $('chat-display');
var elInput     = $('msg-input');
var elBtnSend   = $('btn-send');
var elUserList  = $('user-list');
var elReplyBar  = $('reply-bar');
var elRbName    = $('rb-name');
var elRbText    = $('rb-text');
var elBtnCancelReply = $('btn-cancel-reply');
var elBtnEmoji  = $('btn-emoji');
var elEmojiPicker = $('emoji-picker');
var elScrollBtn = $('scroll-btn');
var elToast     = $('toast');
var toastTimer  = null;

/* ──────────────────────────────────────────────
   HELPERS
────────────────────────────────────────────── */
function safeInt(v) {
    var n = parseInt(v, 10);
    return (isNaN(n) || n <= 0) ? null : n;
}

function esc(str) {
    if (typeof str !== 'string') return '';
    return str
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;')
        .replace(/'/g,'&#39;');
}

function fmtTime(dt) {
    if (!dt) return '';
    var d = new Date(dt.replace(' ','T'));
    if (isNaN(d.getTime())) return '';
    return d.toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit' });
}

function fmtDate(dt) {
    if (!dt) return '';
    var d = new Date(dt.replace(' ','T'));
    if (isNaN(d.getTime())) return '';
    var today = new Date();
    var yest  = new Date(); yest.setDate(yest.getDate() - 1);
    if (d.toDateString() === today.toDateString()) return 'Hari Ini';
    if (d.toDateString() === yest.toDateString())  return 'Kemarin';
    return d.toLocaleDateString('id-ID', { day:'numeric', month:'short', year:'numeric' });
}

function showToast(msg) {
    elToast.textContent = msg;
    elToast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function(){ elToast.classList.remove('show'); }, 2200);
}

/* ──────────────────────────────────────────────
   SIDEBAR
────────────────────────────────────────────── */
function sidebarOpen() {
    elSidebar.classList.add('open');
    elOverlay.classList.add('open');
    elBtnMenu.setAttribute('aria-expanded','true');
}
function sidebarClose() {
    elSidebar.classList.remove('open');
    elOverlay.classList.remove('open');
    elBtnMenu.setAttribute('aria-expanded','false');
}
function sidebarToggle() {
    elSidebar.classList.contains('open') ? sidebarClose() : sidebarOpen();
}

/* ──────────────────────────────────────────────
   SET CHAT
────────────────────────────────────────────── */
function setChat(id, name) {
    var sid  = (id !== null && id !== undefined) ? safeInt(id) : null;
    var snam = (typeof name === 'string') ? name.slice(0,100) : 'Chat';

    receiver = sid;
    chatName = snam;
    sessionStorage.setItem('bc_rid',   sid !== null ? String(sid) : '');
    sessionStorage.setItem('bc_rname', snam);

    elTitle.textContent = snam;
    elTitle.className   = sid !== null ? 'private' : 'public';
    elSubtitle.textContent = sid !== null ? 'Pesan Privat' : 'Semua pengguna';

    if (sid !== null) {
        delete unreadMap[sid];
        updateBadges();
    }
    if (Object.keys(unreadMap).length === 0) document.title = '🐦 BIRDCHAT';

    if (window.innerWidth <= 768) sidebarClose();
    clearReply();
    lastCount = 0;
    loadMessages();
    updateActiveItem();
}

/* ──────────────────────────────────────────────
   USERS
────────────────────────────────────────────── */
function loadUsers() {
    var xhr = new XMLHttpRequest();
    xhr.open('GET','get_users',true);
    xhr.timeout = 8000;
    xhr.onreadystatechange = function() {
        if (xhr.readyState !== 4 || xhr.status !== 200) return;
        try { cachedUsers = JSON.parse(xhr.responseText); } catch(e) { return; }
        if (!Array.isArray(cachedUsers)) { cachedUsers = []; return; }
        renderUsers();
    };
    xhr.send();
}

function updateBadges() {
    var items = elUserList.querySelectorAll('[data-uid]');
    for (var i=0; i<items.length; i++) {
        var uid  = safeInt(items[i].dataset.uid);
        var cnt  = unreadMap[uid] || 0;
        var badge = items[i].querySelector('.notif-badge');
        if (cnt > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'notif-badge';
                items[i].appendChild(badge);
            }
            badge.textContent = cnt > 9 ? '9+' : String(cnt);
            items[i].querySelector('.uname').style.fontWeight = '700';
            items[i].querySelector('.uname').style.color = 'var(--c)';
        } else {
            if (badge) badge.remove();
            items[i].querySelector('.uname').style.fontWeight = '';
            items[i].querySelector('.uname').style.color = '';
        }
    }
}

function updateActiveItem() {
    var items = elUserList.querySelectorAll('[data-uid]');
    for (var i=0; i<items.length; i++) {
        var uid = safeInt(items[i].dataset.uid);
        items[i].classList.toggle('active', uid === receiver);
    }
    $('item-public').classList.toggle('active', receiver === null);
}

function renderUsers() {
    var hash = cachedUsers.map(function(u){
        return u.id+':'+u.is_online+':'+(unreadMap[u.id]||0);
    }).join('|');
    if (hash === lastUsersHash) return;
    lastUsersHash = hash;

    var frag = document.createDocumentFragment();
    for (var i=0; i<cachedUsers.length; i++) {
        var u = cachedUsers[i];
        if (!u || u.username === ME) continue;
        var uid  = safeInt(u.id);
        if (uid === null) continue;
        var cnt  = unreadMap[uid] || 0;

        var item = document.createElement('div');
        item.className = 'user-item' + (uid === receiver ? ' active' : '');
        item.setAttribute('role','button');
        item.setAttribute('tabindex','0');
        item.dataset.uid  = uid;
        item.dataset.name = u.username.slice(0,100); // raw — textContent sudah aman, tidak butuh HTML-escape di sini

        var dot = document.createElement('span');
        dot.className = u.is_online == 1 ? 'dot online' : 'dot offline';

        var nm = document.createElement('span');
        nm.className = 'uname';
        nm.textContent = u.username;
        if (cnt > 0) {
            nm.style.fontWeight = '700';
            nm.style.color = 'var(--c)';
        }

        item.appendChild(dot);
        item.appendChild(nm);

        if (cnt > 0) {
            var badge = document.createElement('span');
            badge.className = 'notif-badge';
            badge.textContent = cnt > 9 ? '9+' : String(cnt);
            item.appendChild(badge);
        }

        frag.appendChild(item);
    }
    elUserList.innerHTML = '';
    elUserList.appendChild(frag);
}

/* ──────────────────────────────────────────────
   MESSAGES
────────────────────────────────────────────── */
function loadMessages() {
    if (msgPending) return;
    msgPending = true;
    var url = receiver !== null ? 'get_messages?with='+receiver : 'get_messages';
    var xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.timeout = 8000;
    xhr.onreadystatechange = function() {
        if (xhr.readyState !== 4) return;
        msgPending = false;
        if (xhr.status !== 200) return;
        var data;
        try { data = JSON.parse(xhr.responseText); } catch(e) { return; }
        if (!Array.isArray(data)) return;

        var recvChanged = (lastRecv !== receiver);
        var newCount    = data.length - lastCount;
        var hasNew      = newCount > 0;

        if (!recvChanged && !hasNew) return;

        if (hasNew && lastCount !== 0) {
            var last = data[data.length - 1];
            if (last && last.username !== ME) {
                notif();
                var lid = safeInt(last.user_id);
                if (lid !== null && (last.receiver_id !== null) && lid !== receiver) {
                    unreadMap[lid] = (unreadMap[lid] || 0) + newCount;
                    document.title = '🔔 BIRDCHAT';
                    renderUsers();
                }
            }
        }

        var atBottom = recvChanged ||
            (elDisplay.scrollHeight - elDisplay.scrollTop - elDisplay.clientHeight) < 100;

        if (recvChanged) {
            var frag = document.createDocumentFragment();
            var prevDate = '';
            for (var i=0; i<data.length; i++) {
                var d = fmtDate(data[i].created_at);
                if (d && d !== prevDate) {
                    frag.appendChild(makeDateSep(d));
                    prevDate = d;
                }
                frag.appendChild(makeBubble(data[i], false));
            }
            elDisplay.innerHTML = '';
            elDisplay.appendChild(frag);
        } else {
            var frag2 = document.createDocumentFragment();
            for (var j=lastCount; j<data.length; j++) {
                frag2.appendChild(makeBubble(data[j], true));
            }
            elDisplay.appendChild(frag2);
        }

        lastCount = data.length;
        lastRecv  = receiver;

        if (atBottom) {
            requestAnimationFrame(function(){ elDisplay.scrollTop = elDisplay.scrollHeight; });
        }
    };
    xhr.send();
}

function makeDateSep(label) {
    var d = document.createElement('div');
    d.className = 'date-sep';
    var s = document.createElement('span');
    s.textContent = label;
    d.appendChild(s);
    return d;
}

function makeBubble(m, isNew) {
    if (!m || typeof m.message !== 'string') return document.createElement('div');
    var isMe = (m.username === ME);

    var wrap = document.createElement('div');
    wrap.className = 'bubble-wrap ' + (isMe ? 'me' : 'other') + (isNew ? ' bubble-new' : '');
    wrap.dataset.msgid  = m.id;
    wrap.dataset.owner  = m.user_id;
    wrap.setAttribute('tabindex','-1');

    // Name (only in public or not-mine)
    if (!isMe && !receiver) {
        var nm = document.createElement('div');
        nm.className   = 'bubble-name';
        nm.textContent = m.username || '';
        wrap.appendChild(nm);
    }

    // Actions
    var acts = document.createElement('div');
    acts.className = 'bubble-actions';

    var btnReply = document.createElement('button');
    btnReply.className = 'act-btn reply-btn';
    btnReply.title = 'Balas';
    btnReply.setAttribute('aria-label','Balas pesan');
    btnReply.textContent = '↩';
    btnReply.dataset.msgid  = m.id;
    btnReply.dataset.name   = m.username || '';
    btnReply.dataset.text   = (m.message || '').slice(0,120);
    acts.appendChild(btnReply);

    if (isMe) {
        var btnDel = document.createElement('button');
        btnDel.className = 'act-btn del';
        btnDel.title = 'Hapus';
        btnDel.setAttribute('aria-label','Hapus pesan');
        btnDel.textContent = '🗑';
        btnDel.dataset.msgid = m.id;
        acts.appendChild(btnDel);
    }
    wrap.appendChild(acts);

    // Bubble
    var bub = document.createElement('div');
    bub.className = 'bubble';

    // Reply preview
    if (m.reply_id && m.reply_text) {
        var rp = document.createElement('div');
        rp.className = 'reply-preview';
        var rpName = document.createElement('div');
        rpName.className   = 'rp-name';
        rpName.textContent = m.reply_user || '?';
        var rpText = document.createElement('div');
        rpText.className   = 'rp-text';
        rpText.textContent = (m.reply_text || '').slice(0,100);
        rp.appendChild(rpName);
        rp.appendChild(rpText);
        bub.appendChild(rp);
    }

    var txt = document.createElement('span');
    txt.textContent = m.message;
    bub.appendChild(txt);
    wrap.appendChild(bub);

    // Time
    var tm = document.createElement('div');
    tm.className   = 'bubble-time';
    tm.textContent = fmtTime(m.created_at);
    wrap.appendChild(tm);

    // Long press (mobile)
    setupLongPress(wrap);

    return wrap;
}

/* ──────────────────────────────────────────────
   ACTIONS: Reply & Delete
────────────────────────────────────────────── */
elDisplay.addEventListener('click', function(e) {
    // Reply button
    var rBtn = e.target.closest('.reply-btn');
    if (rBtn) {
        setReply(
            safeInt(rBtn.dataset.msgid),
            rBtn.dataset.name || '',
            rBtn.dataset.text || ''
        );
        elInput.focus();
        return;
    }
    // Delete button
    var dBtn = e.target.closest('.del');
    if (dBtn) {
        var mid = safeInt(dBtn.dataset.msgid);
        if (!mid) return;
        confirmDelete(mid, dBtn.closest('.bubble-wrap'));
        return;
    }
    // Close actions on outside click
    closeAllActions();
});

function setupLongPress(wrap) {
    var t;
    function start(e) {
        t = setTimeout(function(){
            closeAllActions();
            wrap.classList.add('actions-visible');
            if (navigator.vibrate) navigator.vibrate(50);
        }, 500);
    }
    function end() { clearTimeout(t); }
    wrap.addEventListener('touchstart', start, { passive: true });
    wrap.addEventListener('touchend',   end,   { passive: true });
    wrap.addEventListener('touchmove',  end,   { passive: true });
}

function closeAllActions() {
    var vis = elDisplay.querySelectorAll('.actions-visible');
    for (var i=0; i<vis.length; i++) vis[i].classList.remove('actions-visible');
}

function setReply(id, name, text) {
    replyTo = { id: id, name: name, text: text };
    elRbName.textContent = name;
    elRbText.textContent = text;
    elReplyBar.classList.add('show');
}
function clearReply() {
    replyTo = null;
    elReplyBar.classList.remove('show');
}

function confirmDelete(msgId, wrap) {
    if (!confirm('Hapus pesan ini?')) return;
    wrap.classList.add('deleting');
    var fd = new FormData();
    fd.append('action',     'delete');
    fd.append('msg_id',     msgId);
    fd.append('csrf_token', CSRF);
    var xhr = new XMLHttpRequest();
    xhr.open('POST','send_message',true);
    xhr.timeout = 8000;
    xhr.onreadystatechange = function() {
        if (xhr.readyState !== 4) return;
        if (xhr.status === 200) {
            try {
                var res = JSON.parse(xhr.responseText);
                if (res.ok) {
                    setTimeout(function(){ if (wrap.parentNode) wrap.parentNode.removeChild(wrap); lastCount--; }, 280);
                    showToast('✅ Pesan dihapus');
                    return;
                }
            } catch(e){}
        }
        wrap.classList.remove('deleting');
        showToast('❌ Gagal menghapus');
    };
    xhr.onerror = function(){ wrap.classList.remove('deleting'); showToast('❌ Error jaringan'); };
    xhr.send(fd);
}

/* ──────────────────────────────────────────────
   SEND
────────────────────────────────────────────── */
function handleSend() {
    if (sending) return;
    if (Date.now() - lastSentAt < COOLDOWN) return;
    var msg = elInput.value.trim();
    if (!msg || msg.length > 1000) return;

    sending    = true;
    lastSentAt = Date.now();
    elBtnSend.classList.add('loading');

    var fd = new FormData();
    fd.append('action',     'send');
    fd.append('message',    msg);
    fd.append('csrf_token', CSRF);
    if (receiver !== null) fd.append('receiver_id', receiver);
    if (replyTo && replyTo.id) fd.append('reply_to', replyTo.id);

    elInput.value = '';
    clearReply();
    closeEmoji();

    var xhr = new XMLHttpRequest();
    xhr.open('POST','send_message',true);
    xhr.timeout = 8000;
    xhr.onreadystatechange = function() {
        if (xhr.readyState !== 4) return;
        sending = false;
        elBtnSend.classList.remove('loading');
        if (xhr.status === 200) {
            try {
                var res = JSON.parse(xhr.responseText);
                if (res.ok) { loadMessages(); return; }
            } catch(e) {}
        }
        showToast('❌ Gagal kirim pesan');
    };
    xhr.onerror   = function(){ sending = false; elBtnSend.classList.remove('loading'); showToast('❌ Error jaringan'); };
    xhr.ontimeout = function(){ sending = false; elBtnSend.classList.remove('loading'); showToast('⏱ Timeout'); };
    xhr.send(fd);
}

/* ──────────────────────────────────────────────
   EMOJI PICKER
────────────────────────────────────────────── */
(function buildEmoji() {
    var grid = $('emoji-grid');
    var frag = document.createDocumentFragment();
    for (var i=0; i<EMOJIS.length; i++) {
        var btn = document.createElement('button');
        btn.className = 'emoji-btn';
        btn.textContent = EMOJIS[i];
        btn.dataset.emoji = EMOJIS[i];
        btn.setAttribute('aria-label', EMOJIS[i]);
        btn.type = 'button';
        frag.appendChild(btn);
    }
    grid.appendChild(frag);
})();

function openEmoji() {
    emojiOpen = true;
    elEmojiPicker.classList.add('show');
    elBtnEmoji.classList.add('active');
}
function closeEmoji() {
    emojiOpen = false;
    elEmojiPicker.classList.remove('show');
    elBtnEmoji.classList.remove('active');
}

elBtnEmoji.addEventListener('click', function(e) {
    e.stopPropagation();
    emojiOpen ? closeEmoji() : openEmoji();
});

$('emoji-grid').addEventListener('click', function(e) {
    var btn = e.target.closest('.emoji-btn');
    if (!btn) return;
    var cursor = elInput.selectionStart || elInput.value.length;
    elInput.value = elInput.value.slice(0, cursor) + btn.dataset.emoji + elInput.value.slice(cursor);
    elInput.setSelectionRange(cursor + btn.dataset.emoji.length, cursor + btn.dataset.emoji.length);
    elInput.focus();
});

document.addEventListener('click', function(e) {
    if (emojiOpen && !elEmojiPicker.contains(e.target) && e.target !== elBtnEmoji) {
        closeEmoji();
    }
});

/* ──────────────────────────────────────────────
   PING
────────────────────────────────────────────── */
function pingStatus() {
    var xhr = new XMLHttpRequest();
    xhr.open('POST','update_status',true);
    xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
    xhr.timeout = 5000;
    xhr.send('csrf_token=' + encodeURIComponent(CSRF));
}

/* ──────────────────────────────────────────────
   NOTIFICATION SOUND (Web Audio API — no file needed)
────────────────────────────────────────────── */
function notif() {
    try {
        var ctx = new (window.AudioContext || window.webkitAudioContext)();
        var osc = ctx.createOscillator();
        var gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.frequency.setValueAtTime(880, ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(660, ctx.currentTime + 0.1);
        gain.gain.setValueAtTime(0.1, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.18);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.18);
        if (navigator.vibrate) navigator.vibrate([60,30,60]);
    } catch(e){}
}

/* ──────────────────────────────────────────────
   SCROLL TO BOTTOM BUTTON
────────────────────────────────────────────── */
elDisplay.addEventListener('scroll', function() {
    var dist = elDisplay.scrollHeight - elDisplay.scrollTop - elDisplay.clientHeight;
    if (dist > 200) {
        elScrollBtn.classList.add('visible');
    } else {
        elScrollBtn.classList.remove('visible');
    }
}, { passive: true });

elScrollBtn.addEventListener('click', function() {
    elDisplay.scrollTop = elDisplay.scrollHeight;
});

/* ──────────────────────────────────────────────
   EVENTS
────────────────────────────────────────────── */
elBtnMenu.addEventListener('click',  sidebarToggle);
elBtnClose.addEventListener('click', sidebarClose);
elOverlay.addEventListener('click',  sidebarClose);

$('item-public').addEventListener('click',   function(){ setChat(null,'Public Chat'); });
$('item-public').addEventListener('keydown', function(e){ if(e.key==='Enter'||e.key===' '){ e.preventDefault(); setChat(null,'Public Chat'); } });

elUserList.addEventListener('click', function(e) {
    var item = e.target.closest('[data-uid]');
    if (item) setChat(safeInt(item.dataset.uid), item.dataset.name);
});
elUserList.addEventListener('keydown', function(e) {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    var item = e.target.closest('[data-uid]');
    if (item) { e.preventDefault(); setChat(safeInt(item.dataset.uid), item.dataset.name); }
});

elBtnCancelReply.addEventListener('click', clearReply);

$('btn-logout').addEventListener('click', function(e){
    if (!confirm('Yakin ingin keluar?')) e.preventDefault();
});

elBtnSend.addEventListener('click', handleSend);
elInput.addEventListener('keydown', function(e){
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); handleSend(); }
});

// Input auto-grow / char counter could go here

/* ──────────────────────────────────────────────
   RESTORE SESSION
────────────────────────────────────────────── */
(function restore() {
    var sid  = sessionStorage.getItem('bc_rid');
    var snam = sessionStorage.getItem('bc_rname');
    if (sid && /^\d+$/.test(sid)) {
        receiver = parseInt(sid, 10);
        chatName = (snam && snam.length <= 100) ? snam : 'Private';
        elTitle.textContent = chatName;
        elTitle.className   = 'private';
        elSubtitle.textContent = 'Pesan Privat';
    }
}());

/* ──────────────────────────────────────────────
   POLLING
────────────────────────────────────────────── */
setInterval(loadMessages, 3000);
setInterval(loadUsers,    5000);
setInterval(pingStatus,  60000);

loadMessages();
loadUsers();

}());
</script>
<script nonce="<?= getCspNonce() ?>">
(function() {
    let socket;
    const WS_URL = 'ws://' + window.location.hostname + ':8080';

    function connectWS() {
        socket = new WebSocket(WS_URL);

        socket.onopen = function() {
            console.log("Terhubung ke Python WebSocket");
            // Mengirim identitas saat pertama kali konek
            socket.send(JSON.stringify({
                type: 'auth',
                user_id: <?= (int)$_SESSION['user_id'] ?>,
                token: <?= json_encode($_SESSION['ws_token'] ?? '') ?>
            }));
        };

        socket.onmessage = function(event) {
            try {
                const data = JSON.parse(event.data);
                // Jika ada pesan masuk, picu pembaruan UI
                if (typeof loadMessages === 'function') {
                    // Cek apakah pesan untuk chat yang sedang dibuka
                    if (data.type === 'public' && receiver === null) loadMessages();
                    if (data.type === 'private') loadMessages();
                }
            } catch (e) {
                console.error("Gagal memproses pesan WS");
            }
        };

        socket.onclose = function() {
            console.log("Koneksi terputus, mencoba menyambung kembali...");
            setTimeout(connectWS, 3000); // Auto-reconnect setiap 3 detik
        };
    }

    // Picu notifikasi ke server saat Tuan Muda mengirim pesan via AJAX
    document.getElementById('btn-send').addEventListener('click', function() {
        if (socket && socket.readyState === WebSocket.OPEN) {
            socket.send(JSON.stringify({
                type: receiver ? 'private' : 'public',
                sender_id: <?= (int)$_SESSION['user_id'] ?>,
                target_id: receiver
            }));
        }
    });

    connectWS();
})();
</script>
</body>
</html>
