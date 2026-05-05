<?php
//path tambal choocie
require_once __DIR__ . '/scrp.php';
// ============================================================
//  login.php — Secure Authentication (FIXED)
// ============================================================
require_once __DIR__ . '/config.php';
secureSessionConfig();
session_start();
sendSecurityHeaders();
denyIframe();

if (isset($_SESSION['user_id'])) { header("Location: chat"); exit(); }

$error_msg  = "";
$csrf_token = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rateLimit('login', 5, 300); // IP-based, max 5 attempts per 5 minutes
    
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($submittedToken)) {
        logSecurityEvent('LOGIN_CSRF_FAILED', "Token validation failed");
        $error_msg = "Permintaan tidak valid. Muat ulang halaman.";
    } else {
        $username = sanitizeString($_POST['username'] ?? '', 50);
        $password = $_POST['password'] ?? '';
        
        if (strlen($username) < 3 || strlen($password) < 1) {
            logSecurityEvent('LOGIN_INVALID_FORMAT', "Invalid username/password format");
            $error_msg = "Kredensial tidak valid.";
        } else {
            try {
                // Get user from database
                $stmt = $conn->prepare(
                    "SELECT id, username, password, locked_until, failed_attempts 
                     FROM users WHERE username = ? LIMIT 1"
                );
                $stmt->execute([$username]);
                $data = $stmt->fetch();
                
                // Use dummy hash untuk timing attack mitigation
                $dummyHash = '$2y$12$invalidhashfortimingatk12345678901234567890123456789';
                $hashToVerify = $data ? $data['password'] : $dummyHash;
                
                // Verify password dengan constant-time comparison
                $valid = password_verify($password, $hashToVerify);
                
                if ($data && $valid) {
                    // Check if account is locked
                    if ($data['locked_until'] && strtotime($data['locked_until']) > time()) {
                        logSecurityEvent('LOGIN_LOCKED_ACCOUNT', "UID: {$data['id']} | Locked until {$data['locked_until']}");
                        // Generic error
                        usleep(random_int(200000, 400000));
                        $error_msg = "Akses tidak dapat diberikan. Coba lagi nanti.";
                    } else {
                        // Successful login
                        session_regenerate_id(true);
                        $_SESSION['user_id']       = (int)$data['id'];
                        $_SESSION['username']      = $data['username'];
                        $_SESSION['ip']            = getClientIp();
                        $_SESSION['ua']            = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
                        $_SESSION['created']       = time();
                        $_SESSION['last_activity'] = time();
                        // Token websocket
                        $_SESSION['ws_token'] = getenv('WS_TOKEN');
                        
                        // Reset failed attempts
                        resetFailedAttempts((int)$data['id']);
                        
                        // Clear rate limit
                        unset($_SESSION['rl_login']);
                        
                        logSecurityEvent('LOGIN_SUCCESS', "UID: {$data['id']} | IP: {$_SESSION['ip']}");
                        
                        header("Location: chat");
                        exit();
                    }
                } else {
                    // Failed login - lockout after 5 attempts
                    if ($data) {
                        $failedAttempts = ($data['failed_attempts'] ?? 0) + 1;
                        
                        if ($failedAttempts >= 5) {
                            // Lock account for 15 minutes
                            lockAccount((int)$data['id'], 15);
                            logSecurityEvent('LOGIN_LOCKOUT', "UID: {$data['id']} | Failed attempts: {$failedAttempts}");
                        } else {
                            // Update failed attempts
                            try {
                                $conn->prepare("UPDATE users SET failed_attempts = ? WHERE id = ?")
                                    ->execute([$failedAttempts, (int)$data['id']]);
                            } catch (PDOException $e) {
                                error_log("Failed to update attempts: " . $e->getMessage());
                            }
                            logSecurityEvent('LOGIN_FAILED', "UID: {$data['id']} | Failed attempts: {$failedAttempts}");
                        }
                    } else {
                        logSecurityEvent('LOGIN_FAILED', "Unknown username: $username");
                    }
                    
                    // Variable timing delay untuk prevent timing attacks
                    usleep(random_int(200000, 400000));
                    
                    // GENERIC ERROR MESSAGE - don't reveal if username exists!
                    $error_msg = "Kredensial tidak valid.";
                }
            } catch (PDOException $e) {
                error_log("Login DB error: " . $e->getMessage());
                http_response_code(500);
                die("Database error");
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#060810">
<title>BIRDCHAT — Login</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'><rect width='64' height='64' rx='16' fill='%2305070a'/><text y='44' x='8' font-size='40'>🐦</text></svg>">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
:root {
    --c: #00ffcc;
    --bg: #060810;
    --panel: #0d1018;
    --border: rgba(255,255,255,0.07);
    --text: #dde6f0;
    --muted: #6b7a8d;
    --red: #ff3b6b;
    --ff-head: 'Syne', sans-serif;
    --ff-body: 'DM Sans', sans-serif;
}
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

body {
    font-family: var(--ff-body);
    background: var(--bg);
    background-image:
        radial-gradient(ellipse 70% 50% at 50% -10%, rgba(0,255,204,0.1) 0%, transparent 60%),
        radial-gradient(ellipse 50% 40% at 90% 100%, rgba(0,100,255,0.06) 0%, transparent 60%);
    color: var(--text);
    min-height: 100dvh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.card {
    width: 100%;
    max-width: 400px;
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 24px;
    padding: 44px 36px 36px;
    box-shadow: 0 0 0 1px rgba(0,255,204,0.04),
                0 24px 64px rgba(0,0,0,0.7);
    position: relative;
    overflow: hidden;
    animation: fadeUp 0.55s cubic-bezier(0.22,1,0.36,1) both;
}
.card::before {
    content: '';
    position: absolute;
    top: 0; left: 10%; right: 10%;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--c), transparent);
}
@keyframes fadeUp {
    from { opacity:0; transform: translateY(24px); }
    to   { opacity:1; transform: translateY(0); }
}

.logo-wrap {
    text-align: center;
    margin-bottom: 32px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
}
.logo-icon {
    font-size: 3rem;
    animation: floatBird 3s ease-in-out infinite;
}
@keyframes floatBird {
    0%,100% { transform: translateY(0); }
    50%      { transform: translateY(-6px); }
}
.logo-text {
    font-family: var(--ff-head);
    font-weight: 800;
    font-size: 1.8rem;
    letter-spacing: 4px;
    color: var(--text);
}
.logo-text span { color: var(--c); }
.logo-sub {
    font-size: 12px;
    color: var(--muted);
    letter-spacing: 1px;
    font-weight: 400;
}

.error-box {
    background: rgba(255,59,107,0.08);
    border: 1px solid rgba(255,59,107,0.3);
    color: var(--red);
    padding: 12px 14px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 22px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.field { margin-bottom: 18px; }
label {
    display: block;
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: var(--c);
    font-family: var(--ff-head);
    margin-bottom: 8px;
    opacity: 0.8;
}
input[type="text"], input[type="password"] {
    width: 100%;
    background: rgba(255,255,255,0.04);
    border: 1px solid var(--border);
    color: var(--text);
    padding: 14px 16px;
    border-radius: 14px;
    font-size: 15px;
    font-family: var(--ff-body);
    outline: none;
    -webkit-appearance: none; appearance: none;
    transition: border-color 0.25s, box-shadow 0.25s;
}
input:focus {
    border-color: rgba(0,255,204,0.4);
    box-shadow: 0 0 0 3px rgba(0,255,204,0.07);
}
input::placeholder { color: var(--muted); }

.btn {
    width: 100%;
    background: linear-gradient(135deg, var(--c), #00c9f5);
    color: #060810;
    border: none;
    padding: 15px;
    border-radius: 14px;
    font-weight: 800;
    font-family: var(--ff-head);
    font-size: 15px;
    letter-spacing: 1.5px;
    cursor: pointer;
    margin-top: 8px;
    transition: transform 0.2s, box-shadow 0.2s;
}
.btn:hover  { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,255,204,0.28); }
.btn:active { transform: scale(0.98); }

.footer {
    text-align: center;
    margin-top: 28px;
    font-size: 13px;
    color: var(--muted);
}
.footer a { color: var(--c); text-decoration: none; font-weight: 600; }
.footer a:hover { text-decoration: underline; }
</style>
</head>
<body>
<div class="card">
    <div class="logo-wrap">
        <div class="logo-icon">🐦</div>
        <div class="logo-text">BIRD<span>CHAT</span></div>
        <div class="logo-sub">VIRTUAL COMMUNICATION ENVIRONMENT</div>
    </div>

    <?php if ($error_msg): ?>
    <div class="error-box">⚠️ <?= e($error_msg) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

        <div class="field">
            <label for="username">👤 Username</label>
            <input type="text" id="username" name="username"
                   placeholder="Masukkan username..."
                   maxlength="50" required autocomplete="off"
                   value="<?= isset($_POST['username']) ? e(sanitizeString($_POST['username'] ?? '', 50)) : '' ?>">
        </div>
        <div class="field">
            <label for="password">🔑 Password</label>
            <input type="password" id="password" name="password"
                   placeholder="••••••••" required maxlength="128" autocomplete="off">
        </div>
        <button type="submit" class="btn">MASUK KE SISTEM →</button>
    </form>

    <div class="footer">
        Belum punya akun? <a href="register">Daftar sekarang</a>
    </div>
</div>
</body>
</html>
