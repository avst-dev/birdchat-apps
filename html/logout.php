<?php
//path tambal choocie
require_once __DIR__ . '/scrp.php';
// ============================================================
//  logout.php — Secure Session Termination
// ============================================================
require_once __DIR__ . '/config.php';
secureSessionConfig();
session_start();

if (isset($_SESSION['user_id'])) {
    try {
        $conn->prepare("UPDATE users SET last_seen = NOW() - INTERVAL 10 MINUTE WHERE id = ?")
             ->execute([(int)$_SESSION['user_id']]);
    } catch (PDOException $e) {
        error_log("Logout DB error: " . $e->getMessage());
    }
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();
header("Location: login");
exit();
