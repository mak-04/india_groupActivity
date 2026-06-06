<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
redirect_if_logged_in();

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'request';

    try {
        if ($action === 'request') {
            $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
            if (!$email) { throw new RuntimeException('Please enter a valid email address.'); }

            $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if (!$user) { throw new RuntimeException('Email address was not found.'); }

            $token = bin2hex(random_bytes(32));
            db()->prepare('UPDATE users SET reset_token = ?, reset_expires = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE id = ?')
               ->execute([hash('sha256', $token), $user['id']]);

            $link = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                  . rtrim(dirname($_SERVER['PHP_SELF']), '/')
                  . '/forgot_password.php?token=' . $token;
            $message = 'Reset link: <a href="' . e($link) . '" style="color:var(--blue)">' . e($link) . '</a>';

        } elseif ($action === 'reset') {
            $token   = hash('sha256', $_POST['token'] ?? '');
            $pass    = $_POST['password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if ($pass !== $confirm) { throw new RuntimeException('Passwords do not match.'); }
            if (!password_is_strong($pass)) { throw new RuntimeException('Please use a strong password (8–30 chars, uppercase, lowercase, number, special character).'); }

            $stmt = db()->prepare('SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()');
            $stmt->execute([$token]);
            $user = $stmt->fetch();
            if (!$user) { throw new RuntimeException('Reset link is invalid or expired.'); }

            db()->prepare('UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?')
               ->execute([password_hash($pass, PASSWORD_DEFAULT), $user['id']]);
            $message = 'Password updated successfully. You can now <a href="index.php?mode=login" style="color:var(--blue)">log in</a>.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$token = $_GET['token'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Forgot Password — <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>
<body class="auth-body">
  <main class="auth-shell small fade-in">
    <section class="auth-card full">
      <a class="back-link" href="index.php?mode=login">
        <span class="material-icons">arrow_back</span> Back to login
      </a>

      <form class="auth-form" method="post">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <?php if ($token): ?>
          <input type="hidden" name="action" value="reset">
          <input type="hidden" name="token" value="<?= e($token) ?>">
          <h2>Create New Password</h2>

          <label>New Password
            <span class="input-icon">
              <span class="material-icons">lock</span>
              <input type="password" name="password" id="password" required placeholder="••••••••">
              <button type="button" class="pw-toggle" tabindex="-1">
                <span class="material-icons">visibility</span>
              </button>
            </span>
          </label>

          <label>Confirm Password
            <span class="input-icon">
              <span class="material-icons">done</span>
              <input type="password" name="confirm_password" id="confirmPassword" required placeholder="••••••••">
              <button type="button" class="pw-toggle" tabindex="-1">
                <span class="material-icons">visibility</span>
              </button>
            </span>
          </label>
          <small id="matchText" class="helper-text">Passwords must match.</small>

        <?php else: ?>
          <input type="hidden" name="action" value="request">
          <h2>Forgot Password</h2>
          <p style="font-size:0.83rem;color:var(--gray);margin-top:-8px">Enter your email and we'll generate a reset link.</p>

          <label>Email
            <span class="input-icon">
              <span class="material-icons">mail</span>
              <input type="email" name="email" required placeholder="study@gmail.com">
            </span>
          </label>
        <?php endif; ?>

        <?php if ($message): ?>
          <div class="alert success"><?= $message ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="alert error"><?= e($error) ?></div>
        <?php endif; ?>

        <button class="primary-btn ripple" type="submit">
          <?= $token ? 'Update Password' : 'Send Reset Link' ?>
        </button>
      </form>
    </section>
  </main>
  <script src="assets/js/auth.js"></script>
</body>
</html>