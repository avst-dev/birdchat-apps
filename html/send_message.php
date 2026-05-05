<?php
//path tambal choocie
require_once __DIR__ . '/scrp.php';
// ============================================================
//  send_message.php — Secure Message Sender (FIXED)
// ============================================================
require_once __DIR__ . '/auth_check.php';
sendApiSecurityHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit();
}

// IP-based rate limiting untuk message sending
// Max 30 messages per 60 seconds per IP (allows normal chatting)
// Stricter: 5 per minute per user to prevent spam
rateLimit('send_message', 30, 60);

// ── Verifikasi CSRF (sangat penting untuk POST) ───────────────────────────────────
$submittedToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($submittedToken)) {
    http_response_code(403);
    logSecurityEvent('MESSAGE_CSRF_FAILED', "UID: {$_SESSION['user_id']}");
    echo json_encode(['error' => 'CSRF_INVALID']);
    exit();
}

$uid    = (int)$_SESSION['user_id'];
$action = trim($_POST['action'] ?? 'send');

// ────────────────────────────────────────────────────────────
//  DELETE ACTION
// ────────────────────────────────────────────────────────────
if ($action === 'delete') {
    $msgId = sanitizePositiveInt($_POST['msg_id'] ?? null);
    if (!$msgId) {
        http_response_code(400);
        echo json_encode(['error' => 'INVALID_ID']);
        exit();
    }
    try {
        // Hanya boleh hapus pesan milik sendiri
        $stmt = $conn->prepare(
            "DELETE FROM messages WHERE id = ? AND user_id = ? LIMIT 1"
        );
        $stmt->execute([$msgId, $uid]);
        
        // Check apakah message berhasil dihapus
        if ($stmt->rowCount() === 0) {
            http_response_code(403);
            logSecurityEvent('DELETE_UNAUTHORIZED', "UID: {$uid} attempted to delete message {$msgId}");
            echo json_encode(['error' => 'UNAUTHORIZED']);
            exit();
        }
        
        logSecurityEvent('MESSAGE_DELETED', "UID: {$uid} | MsgID: {$msgId}");
        echo json_encode(['ok' => true, 'deleted' => $msgId]);
    } catch (PDOException $e) {
        error_log("delete_message: " . $e->getMessage());
        http_response_code(500);
        logSecurityEvent('MESSAGE_DELETE_ERROR', "UID: {$uid} | Error: " . $e->getMessage());
        echo json_encode(['error' => 'DB_ERROR']);
    }
    exit();
}

// ────────────────────────────────────────────────────────────
//  SEND ACTION
// ────────────────────────────────────────────────────────────
if ($action !== 'send') {
    http_response_code(400);
    echo json_encode(['error' => 'INVALID_ACTION']);
    exit();
}

$msg   = trim($_POST['message'] ?? '');
$rid   = isset($_POST['receiver_id']) && $_POST['receiver_id'] !== ''
         ? sanitizePositiveInt($_POST['receiver_id']) : null;
$reply = isset($_POST['reply_to']) && $_POST['reply_to'] !== ''
         ? sanitizePositiveInt($_POST['reply_to']) : null;

// ── Validasi message ───────────────────────────────────────────
if ($msg === '') {
    http_response_code(400);
    echo json_encode(['error' => 'EMPTY_MSG']);
    exit();
}

// Check message length (UTF-8 aware)
$msgLen = function_exists('mb_strlen') ? mb_strlen($msg, 'UTF-8') : strlen($msg);
if ($msgLen > 1000) {
    http_response_code(400);
    logSecurityEvent('MESSAGE_TOO_LONG', "UID: {$uid} | Length: {$msgLen}");
    echo json_encode(['error' => 'MSG_TOO_LONG']);
    exit();
}

// Prevent excessive whitespace (spam detection)
if (preg_match('/^\s+$/', $msg)) {
    http_response_code(400);
    echo json_encode(['error' => 'WHITESPACE_ONLY']);
    exit();
}

// ── Validasi receiver ───────────────────────────────────────────
if ($rid !== null) {
    // Check receiver exists
    if ($rid === $uid) {
        http_response_code(400);
        logSecurityEvent('MESSAGE_SELF_SEND', "UID: {$uid}");
        echo json_encode(['error' => 'CANNOT_MESSAGE_SELF']);
        exit();
    }
    
    try {
        $check = $conn->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
        $check->execute([$rid]);
        if (!$check->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'RECEIVER_NOT_FOUND']);
            exit();
        }
    } catch (PDOException $e) {
        error_log("send_message receiver check: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'DB_ERROR']);
        exit();
    }
}

// ── Validasi reply_to ───────────────────────────────────────────
if ($reply !== null) {
    try {
        $checkReply = $conn->prepare("SELECT id FROM messages WHERE id = ? LIMIT 1");
        $checkReply->execute([$reply]);
        if (!$checkReply->fetch()) {
            // If reply doesn't exist, just ignore it (don't fail the send)
            // Save ID *before* nullifying — otherwise log always prints "ReplyID: "
            $badReplyId = $reply;
            $reply      = null;
            logSecurityEvent('MESSAGE_REPLY_NOT_FOUND', "UID: {$uid} | ReplyID: {$badReplyId}");
        }
    } catch (PDOException $e) {
        error_log("check reply_to: " . $e->getMessage());
        $reply = null;
    }
}

// ── Insert message ───────────────────────────────────────────
try {
    $stmt = $conn->prepare(
        "INSERT INTO messages (user_id, message, receiver_id, reply_to, created_at) 
         VALUES (?, ?, ?, ?, NOW())"
    );
    $stmt->execute([$uid, $msg, $rid, $reply]);
    $newId = (int)$conn->lastInsertId();
    
    // Log successful message send
    $msgPreview = function_exists('mb_substr') ? mb_substr($msg, 0, 50, 'UTF-8') : substr($msg, 0, 50);
    logSecurityEvent('MESSAGE_SENT', "UID: {$uid} | MsgID: {$newId} | To: " . ($rid ?? 'PUBLIC') . " | Preview: {$msgPreview}");
    
    echo json_encode(['ok' => true, 'id' => $newId]);
} catch (PDOException $e) {
    error_log("send_message insert: " . $e->getMessage());
    http_response_code(500);
    logSecurityEvent('MESSAGE_INSERT_ERROR', "UID: {$uid} | Error: " . $e->getMessage());
    echo json_encode(['error' => 'DB_ERROR']);
}
