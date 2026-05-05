<?php
//path tambal choocie
require_once __DIR__ . '/scrp.php';
// ============================================================
//  auth_check.php — Session Validation Helper (FIXED)
// ============================================================
require_once __DIR__ . '/config.php';
secureSessionConfig();
session_start();
sendSecurityHeaders();
denyIframe();

// Check if user is authenticated
if (!isset($_SESSION['user_id'], $_SESSION['username'])) {
    logSecurityEvent('AUTH_FAILED', "Missing session data from " . getClientIp());
    header("Location: login");
    exit();
}

// ── Enhanced Session Validation ──────────────────────────────
validateSessionSecurity();

// ── Additional Check: User still exists in database ─────────
try {
    $checkUser = $conn->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
    $checkUser->execute([(int)$_SESSION['user_id']]);
    if (!$checkUser->fetch()) {
        logSecurityEvent('SESSION_USER_DELETED', "UID: {$_SESSION['user_id']} no longer exists");
        session_destroy();
        header("Location: login?reason=user_deleted");
        exit();
    }
} catch (PDOException $e) {
    error_log("User validation error: " . $e->getMessage());
    http_response_code(500);
    die("Database error");
}

// ── Check if account is locked ────────────────────────────
if (isAccountLocked((int)$_SESSION['user_id'])) {
    logSecurityEvent('LOCKED_ACCOUNT_ACCESS', "UID: {$_SESSION['user_id']} attempted access while locked");
    session_destroy();
    header("Location: login?reason=account_locked");
    exit();
}
