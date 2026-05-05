<?php
//path tambal choocie
require_once __DIR__ . '/scrp.php';
// ============================================================
//  register.php — Secure User Registration (FIXED)
// ============================================================
require_once __DIR__ . '/config.php';
secureSessionConfig();
session_start();
sendSecurityHeaders();
denyIframe();

if (isset($_SESSION['user_id'])) { header("Location: chat"); exit(); }

$message    = "";
$msgType    = "";
$csrf_token = generateCsrfToken();

// Get rate limit status for UI display
$rlInfo = getRateLimitStatus('register', 3, 3600); // IP-based, 3 attempts per hour
$isBlocked = $rlInfo['blocked'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isBlocked) {
        $message = "Terlalu banyak percobaan. Tunggu " . (int)$rlInfo['wait'] . " detik.";
        $msgType = "ratelimit";
        logSecurityEvent('REGISTER_RATE_LIMITED', "IP: " . getClientIp());
    } else {
        rateLimit('register', 3, 3600); // IP-based, 3 attempts per hour
        
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $message = "Permintaan tidak valid. Muat ulang halaman.";
            $msgType = "error";
            logSecurityEvent('REGISTER_CSRF_FAILED', "IP: " . getClientIp());
        } else {
            $username = sanitizeString($_POST['username'] ?? '', 50);
            $password = $_POST['password'] ?? '';
            $fullname = sanitizeString($_POST['fullname'] ?? '', 100);
            
            // Validate username: alphanumeric + underscore only, 3-50 chars
            if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
                $message = "Username: hanya huruf, angka, underscore (3–50 karakter).";
                $msgType = "error";
                logSecurityEvent('REGISTER_INVALID_USERNAME', "Username: $username | IP: " . getClientIp());
            } elseif (strlen($password) < 8) {
                $message = "Password minimal 8 karakter.";
                $msgType = "error";
            } elseif (strlen($password) > 128) {
                $message = "Password terlalu panjang (maks 128 karakter).";
                $msgType = "error";
            } else {
                // Hash password dengan bcrypt cost 12
                $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                
                try {
                    // Check if username already exists (to prevent duplicate, but DON'T show user)
                    $checkUser = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
                    $checkUser->execute([$username]);
                    $exists = $checkUser->fetch();
                    
                    if ($exists) {
                        // Username sudah ada, tapi jangan beritahu user!
                        logSecurityEvent('REGISTER_DUP_USERNAME', "Username: $username | IP: " . getClientIp());
                        // Generic message - biarkan mereka pikir registrasi berhasil
                        $message = "Registrasi berhasil! Silakan login →</a>";
                        $msgType = "success";
                    } else {
                        // Insert user
                        $insertStmt = $conn->prepare(
                            "INSERT INTO users (username, password, fullname, failed_attempts, locked_until) 
                             VALUES (?, ?, ?, 0, NULL)"
                        );
                        $insertStmt->execute([$username, $hashed, $fullname]);
                        
                        $message = "Registrasi berhasil! <a href='login'>Silakan login →</a>";
                        $msgType = "success";
                        logSecurityEvent('REGISTER_SUCCESS', "Username: $username | IP: " . getClientIp());
                    }
                } catch (PDOException $e) {
                    error_log("Register: " . $e->getMessage());
                    // Generic error message
                    logSecurityEvent('REGISTER_DB_ERROR', "Error: " . $e->getMessage());
                    $message = "Terjadi kesalahan. Coba lagi nanti.";
                    $msgType = "error";
                }
            }
        }
    }
}

// Update rate limit info
$rlInfo = getRateLimitStatus('register', 3, 3600);
$maxAttempts  = 3;
$attemptsUsed = $maxAttempts - $rlInfo['remaining'];
$barPercent   = min(100, ($attemptsUsed / $maxAttempts) * 100);
$isBlocked    = $rlInfo['blocked'] || $msgType === 'ratelimit';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#060810">
<title>BIRDCHAT — Daftar</title>
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
    --warn: #f59e0b;
    --ff-head: 'Syne', sans-serif;
    --ff-body: 'DM Sans', sans-serif;
}
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: var(--ff-body);
    background: var(--bg);
    background-image:
        radial-gradient(ellipse 60% 50% at 50% -10%, rgba(0,255,204,0.08) 0%, transparent 60%),
        radial-gradient(ellipse 40% 40% at 80% 90%, rgba(0,80,200,0.06) 0%, transparent 60%);
    color: var(--text);
    min-height: 100dvh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.card {
    width: 100%;
    max-width: 420px;
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: 24px;
    padding: 40px 36px 32px;
    box-shadow: 0 0 0 1px rgba(0,255,204,0.04), 0 24px 64px rgba(0,0,0,0.7);
    position: relative;
    overflow: hidden;
    animation: fadeUp 0.55s cubic-bezier(0.22,1,0.36,1) both;
}
.card::before {
    content: '';
    position: absolute;
    top:0; left:10%; right:10%;
    height:1px;
    background: linear-gradient(90deg, transparent, var(--c), transparent);
}
@keyframes fadeUp {
    from { opacity:0; transform:translateY(24px); }
    to   { opacity:1; transform:translateY(0); }
}

.logo-wrap {
    text-align: center;
    margin-bottom: 28px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}
.logo-icon { font-size: 2.5rem; animation: floatBird 3s ease-in-out infinite; }
@keyframes floatBird {
    0%,100% { transform:translateY(0); }
    50%      { transform:translateY(-5px); }
}
.logo-text { font-family: var(--ff-head); font-weight:800; font-size:1.6rem; letter-spacing:4px; }
.logo-text span { color: var(--c); }
.logo-sub { font-size:11px; color: var(--muted); letter-spacing:1px; }

/* Attempt meter */
.meter-wrap { margin-bottom:20px; }
.meter-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:7px; }
.meter-label { font-size:10px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase; color:var(--muted); font-family:var(--ff-head); }
.meter-badge { font-family:var(--ff-head); font-size:10px; padding:2px 8px; border-radius:20px; font-weight:700; border:1px solid; }
.badge-ok   { color:var(--c);    border-color:rgba(0,255,204,0.4);  background:rgba(0,255,204,0.06); }
.badge-warn { color:var(--warn); border-color:rgba(245,158,11,0.4); background:rgba(245,158,11,0.06); }
.badge-bad  { color:var(--red);  border-color:rgba(255,59,107,0.4); background:rgba(255,59,107,0.06); }
.meter-track { height:4px; background:rgba(255,255,255,0.06); border-radius:999px; overflow:hidden; }
.meter-fill {
    height:100%; border-radius:999px;
    width: <?= $barPercent ?>%;
    background: <?= $barPercent<=33 ? 'var(--c)' : ($barPercent<=66 ? 'var(--warn)' : 'var(--red)') ?>;
    transition: width 0.5s ease;
}

/* Block */
.block-box {
    background: rgba(255,59,107,0.07);
    border: 1px solid rgba(255,59,107,0.25);
    border-radius:16px;
    padding:18px;
    margin-bottom:20px;
    text-align:center;
}
.block-box .block-icon { font-size:2rem; margin-bottom:8px; }
.block-box .block-title { font-family:var(--ff-head); font-weight:700; color:var(--red); font-size:14px; letter-spacing:1px; }
.block-box .block-cd { font-size:13px; color:var(--muted); margin-top:6px; }
.block-box strong { color:var(--text); }

/* Alerts */
.alert {
    padding:12px 14px; border-radius:12px;
    margin-bottom:18px; font-size:13px; line-height:1.6;
}
.alert.success { background:rgba(0,255,204,0.07); color:var(--c); border:1px solid rgba(0,255,204,0.25); }
.alert.success a { color:var(--c); font-weight:700; }
.alert.error   { background:rgba(255,59,107,0.07); color:var(--red); border:1px solid rgba(255,59,107,0.25); }

.field { margin-bottom:16px; }
label {
    display:block; font-size:10.5px; font-weight:700;
    letter-spacing:1.5px; text-transform:uppercase;
    color:var(--c); font-family:var(--ff-head);
    margin-bottom:7px; opacity:0.8;
}
input[type="text"], input[type="password"] {
    width:100%; background:rgba(255,255,255,0.04);
    border:1px solid var(--border); color:var(--text);
    padding:13px 15px; border-radius:14px;
    font-size:15px; font-family:var(--ff-body);
    outline:none; -webkit-appearance:none; appearance:none;
    transition: border-color 0.25s, box-shadow 0.25s, opacity 0.2s;
}
input[type="text"]:focus, input[type="password"]:focus { border-color:rgba(0,255,204,0.4); box-shadow:0 0 0 3px rgba(0,255,204,0.07); }
input::placeholder { color:var(--muted); }
input:disabled { opacity:0.3; cursor:not-allowed; }

.btn {
    width:100%;
    background:linear-gradient(135deg,var(--c),#00c9f5);
    color:#060810; border:none;
    padding:15px; border-radius:14px;
    font-weight:800; font-family:var(--ff-head);
    font-size:14px; letter-spacing:1.5px;
    cursor:pointer; margin-top:6px;
    transition:transform 0.2s, box-shadow 0.2s, opacity 0.2s;
}
.btn:hover:not(:disabled) { transform:translateY(-2px); box-shadow:0 8px 24px rgba(0,255,204,0.28); }
.btn:disabled { opacity:0.3; cursor:not-allowed; }
.btn:active:not(:disabled) { transform:scale(0.98); }

.footer { text-align:center; margin-top:24px; font-size:13px; color:var(--muted); }
.footer a { color:var(--c); text-decoration:none; font-weight:600; }
.footer a:hover { text-decoration:underline; }
</style>
</head>
<body>
<div class="card">
    <div class="logo-wrap">
        <div class="logo-icon">🐦</div>
        <div class="logo-text">BIRD<span>CHAT</span></div>
        <div class="logo-sub">BUAT AKUN BARU</div>
    </div>

    <?php
    $bc = $barPercent <= 33 ? 'badge-ok' : ($barPercent <= 66 ? 'badge-warn' : 'badge-bad');
    $bt = $isBlocked ? '0/3' : $rlInfo['remaining'].'/3';
    ?>
    <div class="meter-wrap">
        <div class="meter-head">
            <span class="meter-label">Sisa Percobaan</span>
            <span class="meter-badge <?= $bc ?>"><?= $bt ?></span>
        </div>
        <div class="meter-track"><div class="meter-fill"></div></div>
    </div>

    <?php if ($isBlocked): ?>
    <div class="block-box">
        <div class="block-icon">🔒</div>
        <div class="block-title">Akses Sementara Diblokir</div>
        <div class="block-cd">Tunggu <strong id="cd"><?= (int)$rlInfo['wait'] ?>s</strong> sebelum mencoba lagi.</div>
    </div>
    <?php elseif ($message && $msgType==='success'): ?>
    <div class="alert success">✅ <?= $message ?></div>
    <?php elseif ($message): ?>
    <div class="alert error">⚠️ <?= $message ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
        <div class="field">
            <label>👤 Username</label>
            <input type="text" name="username" placeholder="hanya huruf, angka, _"
                   maxlength="50" pattern="[a-zA-Z0-9_]{3,50}" required autocomplete="off"
                   <?= $isBlocked ? 'disabled' : '' ?>>
        </div>
        <div class="field">
            <label>🔑 Password</label>
            <input type="password" name="password" placeholder="Minimal 8 karakter"
                   minlength="8" maxlength="128" required
                   <?= $isBlocked ? 'disabled' : '' ?>>
        </div>
        <div class="field">
            <label>✏️ Nama Lengkap</label>
            <input type="text" name="fullname" placeholder="Nama Anda (opsional)"
                   maxlength="100" autocomplete="off"
                   <?= $isBlocked ? 'disabled' : '' ?>>
        </div>
        <button type="submit" class="btn" <?= $isBlocked ? 'disabled' : '' ?>>
            DAFTAR SEKARANG →
        </button>
    </form>

    <div class="footer">
        Sudah punya akun? <a href="login">Login di sini</a>
    </div>
</div>

<?php if ($isBlocked && $rlInfo['wait'] > 0): ?>
<script nonce="<?= getCspNonce() ?>">
(function(){
    var s=<?= (int)$rlInfo['wait'] ?>,el=document.getElementById('cd');
    var t=setInterval(function(){
        s--;el.textContent=s+'s';
        if(s<=0){clearInterval(t);location.reload();}
    },1000);
})();
</script>
<?php endif; ?>
</body>
</html>
