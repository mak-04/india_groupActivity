<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

// Redirect if already logged in
if (current_admin()) {
    header('Location: panel.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip       = $_SERVER['REMOTE_ADDR'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password']      ?? '';

    if (!check_login_rate_limit($ip)) {
        $error = 'Too many failed attempts. Please try again in 15 minutes.';
    } elseif (!$username || !$password) {
        $error = 'Please enter both username and password.';
        record_login_attempt($username, $ip, false);
    } else {
        $envUser = getenv('ADMIN_USERNAME');
        $envPass = getenv('ADMIN_PASSWORD');

        if ($envUser && $envPass && $username === $envUser && $password === $envPass) {
            session_regenerate_id(true);
            $_SESSION['admin_id']       = 0;
            $_SESSION['admin_username'] = $username;
            admin_csrf_token();
            record_login_attempt($username, $ip, true);
            log_admin_action(0, 'LOGIN', 'admin', 0, 'Successful login via ENV');
            header('Location: panel.php');
            exit;
        }
        $stmt = db()->prepare('SELECT id, username, password FROM admins WHERE username = ?');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id']       = (int) $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            admin_csrf_token(); // generate CSRF token
            record_login_attempt($username, $ip, true);
            log_admin_action((int) $admin['id'], 'LOGIN', 'admin', (int) $admin['id'], 'Successful login');
            header('Location: panel.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
            record_login_attempt($username, $ip, false);
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Login — <?= APP_NAME ?></title>
  <link rel="icon" type="image/png" href="../assets/img/logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Poppins', sans-serif;
      background: #fff;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .login-wrap {
      width: 100%;
      max-width: 460px;
      padding: 24px;
    }
    h1 {
      font-size: 2rem;
      font-weight: 800;
      color: #000;
      text-align: center;
      margin-bottom: 36px;
      line-height: 1.2;
    }
    .input-group {
      display: flex;
      align-items: center;
      border: 1.5px solid #d0d0d0;
      border-radius: 8px;
      margin-bottom: 18px;
      background: #fff;
      overflow: hidden;
      transition: border-color .2s;
    }
    .input-group:focus-within { border-color: #2058dc; }
    .input-icon {
      width: 50px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      border-right: 1.5px solid #d0d0d0;
      height: 52px;
    }
    .input-icon svg { width: 22px; height: 22px; fill: #888; }
    .input-group input {
      flex: 1;
      border: none;
      outline: none;
      padding: 14px 16px;
      font-family: 'Poppins', sans-serif;
      font-size: 0.95rem;
      color: #333;
      background: transparent;
    }
    .input-group input::placeholder { color: #aaa; font-style: italic; }
    .pw-toggle {
      background: none;
      border: none;
      cursor: pointer;
      padding: 0 14px;
      color: #888;
      display: flex;
      align-items: center;
    }
    .pw-toggle svg { width: 20px; height: 20px; fill: #888; transition: fill .2s; }
    .pw-toggle:hover svg { fill: #2058dc; }
    .remember-row {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 24px;
      font-size: 0.85rem;
      color: #666;
    }
    .remember-row input { accent-color: #2058dc; }
    .btn-login {
      width: 100%;
      background: #2058dc;
      color: #fff;
      border: none;
      border-radius: 8px;
      padding: 16px;
      font-family: 'Poppins', sans-serif;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      transition: background .2s, transform .1s;
      position: relative;
      overflow: hidden;
    }
    .btn-login:hover { background: #1648c0; }
    .btn-login:active { transform: scale(0.99); }
    .btn-login.loading { pointer-events: none; opacity: .8; }
    .btn-login .spinner {
      display: none;
      width: 18px; height: 18px;
      border: 2.5px solid rgba(255,255,255,.4);
      border-top-color: #fff;
      border-radius: 50%;
      animation: spin .7s linear infinite;
      margin: 0 auto;
    }
    .btn-login.loading .btn-text { display: none; }
    .btn-login.loading .spinner { display: block; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .alert {
      padding: 12px 16px;
      border-radius: 8px;
      margin-bottom: 18px;
      font-size: 0.85rem;
      font-weight: 500;
    }
    .alert.error { background: #fff0f0; color: #d32f2f; border: 1px solid #ffcdd2; }
    .alert.success { background: #f0fff4; color: #2e7d32; border: 1px solid #c8e6c9; }
  </style>
</head>
<body>
  <div class="login-wrap">
    <h1>Welcome back, Admin!</h1>

    <?php if ($error): ?>
      <div class="alert error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="alert success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="post" id="loginForm" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= admin_csrf_token() ?>">

      <!-- Username -->
      <div class="input-group">
        <span class="input-icon">
          <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
        </span>
        <input type="text" name="username" placeholder="Enter admin username" required autocomplete="username"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>

      <!-- Password -->
      <div class="input-group">
        <span class="input-icon">
          <svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
        </span>
        <input type="password" name="password" id="pwField" placeholder="Password" required autocomplete="current-password">
        <button type="button" class="pw-toggle" id="pwToggle" title="Show/hide password">
          <svg id="eyeIcon" viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zm0 12.5c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
        </button>
      </div>

      <!-- Remember me -->
      <div class="remember-row">
        <input type="checkbox" name="remember" id="remember">
        <label for="remember">Remember me</label>
      </div>

      <button class="btn-login" type="submit" id="loginBtn">
        <span class="btn-text">Login as Admin</span>
        <span class="spinner"></span>
      </button>
    </form>
  </div>

  <script>
    // Show/hide password
    const pwField  = document.getElementById('pwField');
    const pwToggle = document.getElementById('pwToggle');
    const eyeIcon  = document.getElementById('eyeIcon');

    pwToggle.addEventListener('click', () => {
      if (pwField.type === 'password') {
        pwField.type = 'text';
        eyeIcon.innerHTML = '<path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46A11.804 11.804 0 001 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/>';
      } else {
        pwField.type = 'password';
        eyeIcon.innerHTML = '<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zm0 12.5c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>';
      }
    });

    // Loading state on submit
    document.getElementById('loginForm').addEventListener('submit', () => {
      document.getElementById('loginBtn').classList.add('loading');
    });
  </script>
</body>
</html>