<?php
//path tambal choocie
require_once __DIR__ . '/scrp.php';
// ============================================================
//  get_users.php — Secure User List (FIXED)
// ============================================================
require_once __DIR__ . '/auth_check.php';
sendApiSecurityHeaders();

// MUCH STRICTER rate limiting to prevent user enumeration
// 2 requests per 60 seconds per IP
rateLimit('get_users', 2, 60);

try {
    $stmt = $conn->prepare(
        "SELECT id, username,
                (last_seen > NOW() - INTERVAL 2 MINUTE) AS is_online
         FROM users
         ORDER BY is_online DESC, username ASC"
    );
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as &$u) {
        $u['id']        = (int)$u['id'];
        $u['is_online'] = (int)$u['is_online'];
        // JANGAN bocorkan data sensitif
        unset($u['password'], $u['fullname'], $u['last_seen'], $u['email'], $u['failed_attempts'], $u['locked_until']);
    }
    unset($u);

    logSecurityEvent('GET_USERS', "UID: {$_SESSION['user_id']} | Users returned: " . count($users));
    echo json_encode($users, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("get_users error: " . $e->getMessage());
    logSecurityEvent('GET_USERS_ERROR', "UID: {$_SESSION['user_id']} | Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
