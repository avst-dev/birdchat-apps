<?php
//path tambal choocie
require_once __DIR__ . '/scrp.php';
// ============================================================
//  get_messages.php — Secure Message Retrieval (FIXED)
// ============================================================
require_once __DIR__ . '/auth_check.php';
sendApiSecurityHeaders();

// STRICTER rate limiting untuk GET requests (prevent enumeration)
// 30 requests per 60 seconds per IP (changed from global session limit)
rateLimit('get_messages', 30, 60);

$uid    = (int)$_SESSION['user_id'];
$target = isset($_GET['with']) ? sanitizePositiveInt($_GET['with']) : null;

if (array_key_exists('with', $_GET) && $target === null) {
    http_response_code(400);
    logSecurityEvent('GET_MESSAGES_INVALID_TARGET', "UID: {$uid}");
    echo json_encode(['error' => 'Invalid target']);
    exit();
}

// ── Validasi: target harus user yang ada ───────────────────────────────
if ($target !== null) {
    // Prevent user from viewing other private conversations
    // (Only allow viewing their own private messages)
    try {
        $chk = $conn->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
        $chk->execute([$target]);
        if (!$chk->fetch()) {
            http_response_code(400);
            logSecurityEvent('GET_MESSAGES_USER_NOT_FOUND', "UID: {$uid} | Target: {$target}");
            echo json_encode(['error' => 'User not found']);
            exit();
        }
    } catch (PDOException $e) {
        error_log("get_messages user check: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Server error']);
        exit();
    }
}

try {
    $baseQuery = "SELECT m.id, m.user_id, m.receiver_id, m.message, m.created_at,
                         u.username,
                         r.id       AS reply_id,
                         r.message  AS reply_text,
                         ru.username AS reply_user
                  FROM messages m
                  JOIN users u ON m.user_id = u.id
                  LEFT JOIN messages r  ON m.reply_to = r.id
                  LEFT JOIN users   ru ON r.user_id   = ru.id ";

    if ($target) {
        // Private messages with specific user
        $stmt = $conn->prepare(
            $baseQuery .
            "WHERE (m.user_id = :uid  AND m.receiver_id = :tid)
                OR (m.user_id = :tid2 AND m.receiver_id = :uid2)
             ORDER BY m.created_at ASC
             LIMIT 200"
        );
        $stmt->execute([
            ':uid'  => $uid,
            ':tid'  => $target,
            ':tid2' => $target,
            ':uid2' => $uid,
        ]);
        logSecurityEvent('GET_MESSAGES_PRIVATE', "UID: {$uid} | Target: {$target}");
    } else {
        // Public messages (broadcasts)
        $stmt = $conn->prepare(
            $baseQuery .
            "WHERE m.receiver_id IS NULL
             ORDER BY m.created_at ASC
             LIMIT 200"
        );
        $stmt->execute();
        logSecurityEvent('GET_MESSAGES_PUBLIC', "UID: {$uid}");
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Type casting & data sanitization
    foreach ($rows as &$row) {
        $row['id']          = (int)$row['id'];
        $row['user_id']     = (int)$row['user_id'];
        $row['receiver_id'] = $row['receiver_id'] !== null ? (int)$row['receiver_id'] : null;
        $row['reply_id']    = $row['reply_id'] !== null ? (int)$row['reply_id'] : null;
        // Ensure message is string
        $row['message']     = (string)$row['message'];
    }
    unset($row);

    echo json_encode($rows, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("get_messages error: " . $e->getMessage());
    logSecurityEvent('GET_MESSAGES_ERROR', "UID: {$uid} | Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
