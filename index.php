<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

// BUG FIX: original index.php was an EXACT COPY of dashboard.php — this caused
// an infinite redirect loop. Logged-in users should go to dashboard.php.
// Guests should see the login/signup form.
redirect_if_logged_in(); // redirects to dashboard.php if already logged in

$mode    = $_GET['mode']    ?? 'login';
$mode    = in_array($mode, ['login', 'signup'], true) ? $mode : 'login';
$error   = $_GET['error']   ?? '';
$success = $_GET['success'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $mode === 'signup' ? 'Sign Up' : 'Log In' ?> — <?= APP_NAME ?></title>
  <link rel="icon" type="image/png" href="assets/img/logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>
<body class="auth-body">
  <main class="auth-shell fade-in">

    <!-- Brand panel (left) -->
    <section class="brand-panel">
      <div class="logo-mark">
        <img src="assets/img/logo.png" alt="Study Tracker" class="brand-logo-img">
      </div>
      <h1>Study<span> Tracker</span></h1>
      <p>AI-powered lessons &amp; quizzes</p>
    </section>

    <!-- Auth card (right) -->
    <section class="auth-card">
      <!-- Tabs -->
      <div class="auth-tabs">
        <a href="index.php?mode=login"  class="<?= $mode === 'login'  ? 'active' : '' ?>">Log In</a>
        <a href="index.php?mode=signup" class="<?= $mode === 'signup' ? 'active' : '' ?>">Sign Up</a>
      </div>

      <?php if ($success): ?>
        <div class="alert success"><?= e($success) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert error"><?= e($error) ?></div>
      <?php endif; ?>

      <?php if ($mode === 'login'): ?>
      <!-- ── LOGIN FORM ───────────────────────────────────── -->
      <form class="auth-form" method="post" action="auth_action.php">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action"     value="login">
        <h2>Welcome back</h2>

        <label>Email
          <span class="input-icon">
            <span class="material-icons">mail</span>
            <input type="email" name="email" required autocomplete="email" placeholder="study@gmail.com" value="<?= e($_COOKIE['remember_email'] ?? '') ?>">
          </span>
        </label>

        <label>Password
          <span class="input-icon">
            <span class="material-icons">lock</span>
            <input type="password" name="password" id="password" required placeholder="••••••••">
            <button type="button" class="pw-toggle" tabindex="-1">
              <span class="material-icons">visibility</span>
            </button>
          </span>
        </label>

        <div class="form-row">
          <label class="remember">
            <input type="checkbox" name="remember" <?= !empty($_COOKIE['remember_email']) ? 'checked' : '' ?>> Remember me
          </label>
          <a href="forgot_password.php">Forgot password?</a>
        </div>

        <button class="primary-btn ripple" type="submit">Log In</button>
      </form>

      <?php else: ?>
      <!-- ── SIGNUP FORM ──────────────────────────────────── -->
      <form class="auth-form" method="post" action="auth_action.php">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action"     value="signup">
        <h2>Create account</h2>

        <label>Email
          <span class="input-icon">
            <span class="material-icons">mail</span>
            <input type="email" name="email" required autocomplete="email" placeholder="study@gmail.com">
          </span>
        </label>

        <label>Password
          <div class="pw-rules-wrap">
            <span class="input-icon">
              <span class="material-icons">lock</span>
              <input type="password" name="password" id="password" required placeholder="••••••••">
              <button type="button" class="pw-toggle" tabindex="-1">
                <span class="material-icons">visibility</span>
              </button>
            </span>
            <div class="pw-rules" id="pwRules">
              <strong>Password must have:</strong>
              <span data-rule="length">8–30 characters</span>
              <span data-rule="upper">Uppercase letter (A–Z)</span>
              <span data-rule="lower">Lowercase letter (a–z)</span>
              <span data-rule="number">Number (0–9)</span>
              <span data-rule="special">Special character (!@#$…)</span>
            </div>
          </div>
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

        <button class="primary-btn ripple" type="submit">Create Account</button>
      </form>
      <?php endif; ?>
    </section>
  </main>
  <script src="assets/js/auth.js"></script>
  <script>
    // Ensure the user is welcomed by the empty "Let's get started" view 
    // instead of an old chat upon logging in.
    localStorage.removeItem('activeHistoryId');
  </script>
</body>
</html>
