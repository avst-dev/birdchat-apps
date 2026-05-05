<?php
// ============================================================
//  config.php — HARDENED SECURE CONFIGURATION (FIXED)
// ============================================================

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// ── Load .env ────────────────────────────────────────────────
$envPath = __DIR__ . '/.env';
if (!file_exists($envPath)) {
    http_response_code(500);
    die("Konfigurasi sistem tidak ditemukan.");
}

$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
    [$name, $value] = explode('=', $line, 2);
    $name  = trim($name);
    $value = trim($value, " \t\n\r\0\x0B\"'");
    if (preg_match('/^[A-Z0-9_]+$/', $name)) {
        putenv("$name=$value");
        $_ENV[$name] = $value;
    }
}

// ── Koneksi Database ─────────────────────────────────────────
$host = getenv('DB_HOST');
$db   = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');

if (!$host || !$db || !$user) {
    http_response_code(500);
    die("Konfigurasi database tidak lengkap.");
}

try {
    $conn = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die("Error Database: " . $e->getMessage());
}

// ============================================================
//  SECURITY LOGGING
// ============================================================
function logSecurityEvent(string $type, string $event, ?string $ip = null, ?int $uid = null): void
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0700, true);
    
    $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');
    $uid = $uid ?? ($_SESSION['user_id'] ?? null);
    $timestamp = date('Y-m-d H:i:s');
    $ua = function_exists('mb_substr')
        ? mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 100, 'UTF-8')
        : substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 100);
    $logEntry = sprintf(
        "[%s] %s | IP:%s | UID:%s | UA:%s | %s\n",
        $timestamp, $type, $ip, $uid ?? 'ANON', $ua, $event
    );
    
    $logFile = $logDir . '/security.log';
    error_log($logEntry, 3, $logFile);
}

// ============================================================
//  IP-BASED RATE LIMITER (FILE CACHE)
// ============================================================
function rateLimit(string $key, int $maxAttempts, int $decaySeconds): void
{
    $cacheDir = __DIR__ . '/../cache';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0700, true);
    
    $key = preg_replace('/[^a-z0-9_]/', '', strtolower($key));
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $cacheKey = "{$key}_{$ip}";
    $cacheFile = $cacheDir . '/rl_' . md5($cacheKey) . '.json';
    $now = time();
    
    $data = ['count' => 0, 'reset_at' => $now + $decaySeconds];
    if (file_exists($cacheFile)) {
        $json = @json_decode(file_get_contents($cacheFile), true);
        if (is_array($json) && isset($json['reset_at'])) {
            $data = $json;
        }
    }
    
    if ($now >= $data['reset_at']) {
        $data = ['count' => 0, 'reset_at' => $now + $decaySeconds];
    }
    
    $data['count']++;
    
    @file_put_contents($cacheFile, json_encode($data), LOCK_EX);
    @chmod($cacheFile, 0600);
    
    if ($data['count'] > $maxAttempts) {
        $wait = max(0, $data['reset_at'] - $now);
        logSecurityEvent('RATE_LIMIT_EXCEEDED', "Key: $key | Attempts: {$data['count']}");
        http_response_code(429);
        die(json_encode([
            'error' => "Terlalu banyak percobaan. Coba lagi dalam {$wait} detik."
        ]));
    }
}

function getRateLimitStatus(string $key, int $maxAttempts, int $decaySeconds): array
{
    $cacheDir = __DIR__ . '/../cache';
    if (!is_dir($cacheDir)) return ['remaining' => $maxAttempts, 'wait' => 0, 'blocked' => false, 'used' => 0];
    
    $key = preg_replace('/[^a-z0-9_]/', '', strtolower($key));
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $cacheKey = "{$key}_{$ip}";
    $cacheFile = $cacheDir . '/rl_' . md5($cacheKey) . '.json';
    $now = time();
    
    if (!file_exists($cacheFile)) {
        return ['remaining' => $maxAttempts, 'wait' => 0, 'blocked' => false, 'used' => 0];
    }
    
    $json = @json_decode(file_get_contents($cacheFile), true);
    if (!is_array($json)) {
        return ['remaining' => $maxAttempts, 'wait' => 0, 'blocked' => false, 'used' => 0];
    }
    
    if ($now >= ($json['reset_at'] ?? 0)) {
        return ['remaining' => $maxAttempts, 'wait' => 0, 'blocked' => false, 'used' => 0];
    }
    
    $used = $json['count'] ?? 0;
    $remaining = max(0, $maxAttempts - $used);
    $wait = ($json['reset_at'] ?? $now) - $now;
    $blocked = $used >= $maxAttempts;
    
    return ['remaining' => $remaining, 'wait' => (int)$wait, 'blocked' => $blocked, 'used' => $used];
}

// ============================================================
//  ACCOUNT LOCKOUT
// ============================================================
function isAccountLocked(int $userId): bool
{
    try {
        $stmt = $GLOBALS['conn']->prepare(
            "SELECT locked_until FROM users WHERE id = ? AND locked_until > NOW() LIMIT 1"
        );
        $stmt->execute([$userId]);
        return (bool)$stmt->fetch();
    } catch (PDOException $e) {
        error_log("isAccountLocked: " . $e->getMessage());
        return false;
    }
}

function lockAccount(int $userId, int $minutes = 15): void
{
    try {
        $GLOBALS['conn']->prepare(
            "UPDATE users SET locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE), 
             failed_attempts = COALESCE(failed_attempts, 0) + 1 
             WHERE id = ?"
        )->execute([$minutes, $userId]);
        logSecurityEvent('ACCOUNT_LOCKED', "UID: $userId | Duration: {$minutes} minutes");
    } catch (PDOException $e) {
        error_log("lockAccount: " . $e->getMessage());
    }
}

function resetFailedAttempts(int $userId): void
{
    try {
        $GLOBALS['conn']->prepare(
            "UPDATE users SET failed_attempts = 0 WHERE id = ?"
        )->execute([$userId]);
    } catch (PDOException $e) {
        error_log("resetFailedAttempts: " . $e->getMessage());
    }
}

// ============================================================
//  CSRF Helper
// ============================================================
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_created'] = time();
    }
    
    if ((time() - ($_SESSION['csrf_created'] ?? 0)) > 3600) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_created'] = time();
    }
    
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool
{
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        logSecurityEvent('CSRF_FAILED', "Token mismatch or missing");
        return false;
    }
    $valid = hash_equals($_SESSION['csrf_token'], $token);
    if (!$valid) {
        logSecurityEvent('CSRF_FAILED', "Invalid token from {$_SERVER['REMOTE_ADDR']}");
    }
    return $valid;
}

// ============================================================
//  Input Sanitizer
// ============================================================
function sanitizeString(string $input, int $maxLen = 500): string
{
    $input = trim($input);
    $input = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $input);
    $input = function_exists('mb_substr')
        ? mb_substr($input, 0, $maxLen, 'UTF-8')
        : substr($input, 0, $maxLen);
    return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
}

function sanitizePositiveInt($val): ?int
{
    $n = filter_var($val, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return ($n === false) ? null : (int)$n;
}

function e(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
}

// ============================================================
//  Secure Session Config — FIXED
//  Perubahan utama:
//  1. Deteksi HTTPS dengan benar (tidak paksa secure=true di HTTP)
//  2. Pakai session_set_cookie_params() array form (PHP 7.3+)
//  3. TIDAK memanggil session_start() — dipanggil oleh masing-masing file
// ============================================================
function secureSessionConfig(): void
{
    // Deteksi HTTPS dengan benar (support reverse proxy/Cloudflare)
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (($_SERVER['SERVER_PORT'] ?? 80) == 443);

    // Harus dipanggil SEBELUM session_start()
    session_set_cookie_params([
        'lifetime' => 0,          // cookie hilang saat browser ditutup
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,   // false di HTTP/localhost — INI FIX UTAMA
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    ini_set('session.use_strict_mode',  1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_httponly',  1);
    ini_set('session.cookie_samesite',  'Strict');
    ini_set('session.gc_maxlifetime',   3600);
    ini_set('session.cookie_lifetime',  0);
    ini_set('session.use_trans_sid',    0);
    ini_set('session.cache_limiter',    'nocache');

    if (!$isHttps) {
        error_log('WARNING: HTTPS not detected - session cookies may be vulnerable');
    }
}

// ============================================================
//  CSP NONCE
// ============================================================
function generateCspNonce(): string
{
    if (empty($GLOBALS['_csp_nonce'])) {
        $GLOBALS['_csp_nonce'] = base64_encode(random_bytes(16));
    }
    return $GLOBALS['_csp_nonce'];
}

function getCspNonce(): string
{
    return htmlspecialchars(generateCspNonce(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ── Security Headers ─────────────────────────────────────────
function sendSecurityHeaders(): void
{
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
    
    $nonce = generateCspNonce();
    header("Content-Security-Policy: default-src 'self'; "
         . "script-src 'self' 'nonce-{$nonce}'; "
         . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
         . "font-src https://fonts.gstatic.com; "
         . "img-src 'self' data:; "
         . "connect-src 'self';");
    
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    if ($isHttps) {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
    }
}

function denyIframe(): void
{
    if (!empty($_SERVER['HTTP_SEC_FETCH_DEST'])
        && $_SERVER['HTTP_SEC_FETCH_DEST'] === 'iframe') {
        http_response_code(403);
        logSecurityEvent('IFRAME_BLOCKED', "Attempted iframe loading from {$_SERVER['REMOTE_ADDR']}");
        exit('Forbidden');
    }
}

// ============================================================
//  GET CLIENT IP
// ============================================================
function getClientIp(): string
{
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED'];
    } elseif (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['HTTP_FORWARDED'])) {
        $ip = $_SERVER['HTTP_FORWARDED'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return '127.0.0.1';
    }
    
    return $ip;
}

// ============================================================
//  SESSION VALIDATION
// ============================================================
function validateSessionSecurity(): void
{
    $currentIp = getClientIp();
    $currentUa = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (isset($_SESSION['ip']) && !hash_equals($_SESSION['ip'], $currentIp)) {
        logSecurityEvent('SESSION_IP_MISMATCH', "Expected: {$_SESSION['ip']} | Got: {$currentIp}");
        session_destroy();
        header("Location: login?reason=ip_mismatch");
        exit();
    }
    
    if (isset($_SESSION['ua']) && !hash_equals($_SESSION['ua'], hash('sha256', $currentUa))) {
        logSecurityEvent('SESSION_UA_MISMATCH', "User-Agent changed");
        session_destroy();
        header("Location: login?reason=ua_mismatch");
        exit();
    }
    
    $sessionLifetime = 3600;
    if (isset($_SESSION['last_activity'])
        && (time() - $_SESSION['last_activity']) > $sessionLifetime) {
        logSecurityEvent('SESSION_TIMEOUT', "Inactive for {$sessionLifetime} seconds");
        session_destroy();
        header("Location: login?reason=timeout");
        exit();
    }
    $_SESSION['last_activity'] = time();
    
    if (!isset($_SESSION['regenerated_at'])) {
        $_SESSION['regenerated_at'] = time();
    }
    if ((time() - $_SESSION['regenerated_at']) > 900) {
        session_regenerate_id(true);
        $_SESSION['regenerated_at'] = time();
        logSecurityEvent('SESSION_REGENERATED', "UID: {$_SESSION['user_id']}");
    }
}

// ============================================================
//  API SECURITY HEADERS
// ============================================================
function sendApiSecurityHeaders(): void
{
    sendSecurityHeaders();
    header("Content-Type: application/json; charset=utf-8");
    header("X-Content-Type-Options: nosniff");
}
