<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$user = current_user();
if (!$user) {
    header('Location: index.php?mode=login');
    exit;
}

// Redirect to dashboard if already onboarded
if ($user['birthday'] && $user['gender']) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $birthday = $_POST['birthday'] ?? '';
    $gender   = $_POST['gender'] ?? '';

    if ($username === '' || $birthday === '' || $gender === '') {
        $error = 'All fields are required.';
    } else {
        try {
            $stmt = db()->prepare('UPDATE users SET username = ?, birthday = ?, gender = ? WHERE id = ?');
            $stmt->execute([$username, $birthday, $gender, $user['id']]);
            header('Location: dashboard.php');
            exit;
        } catch (Throwable $e) {
            $error = 'An error occurred: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Complete Profile — <?= APP_NAME ?></title>
  <link rel="icon" type="image/png" href="assets/img/logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
  <style>
    .onboarding-card {
      background: var(--surface);
      border-radius: 12px;
      padding: 40px;
      width: 100%;
      max-width: 460px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.3);
      border: 1px solid rgba(255,255,255,0.05);
      margin: 40px auto;
    }
    .onboarding-card h2 {
      font-size: 1.6rem;
      margin-bottom: 8px;
      font-weight: 600;
    }
    .onboarding-card p {
      color: var(--gray);
      margin-bottom: 24px;
      font-size: 0.9rem;
    }
    .gender-options {
      display: flex;
      gap: 16px;
      margin-top: 8px;
    }
    .gender-options label {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 12px;
      background: rgba(255,255,255,0.04);
      border-radius: 8px;
      border: 1px solid transparent;
      cursor: pointer;
      transition: 0.2s;
      font-size: 0.95rem;
      font-weight: 500;
      color: var(--gray);
    }
    .gender-options label:hover {
      background: rgba(255,255,255,0.08);
    }
    .gender-options input[type="radio"] {
      display: none;
    }
    .gender-options input[type="radio"]:checked + span {
      color: var(--white);
    }
    .gender-options label:has(input:checked) {
      background: rgba(255,255,255,0.12);
      border-color: var(--gold);
      color: var(--white);
    }
    .onboarding-shell {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .auth-form label { margin-bottom: 20px; }
  </style>
</head>
<body class="auth-body">
  <main class="onboarding-shell fade-in">
    <div class="onboarding-card">
      <h2>Complete your profile</h2>
      <p>Tell us a little bit about yourself to get started.</p>

      <?php if ($error): ?>
        <div class="alert error"><?= e($error) ?></div>
      <?php endif; ?>

      <form class="auth-form" method="post" action="onboarding.php">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        
        <label>Username
          <span class="input-icon">
            <span class="material-icons">person</span>
            <input type="text" name="username" required value="<?= e($user['username']) ?>" placeholder="Choose a username">
          </span>
        </label>

        <label>Birthday
          <span class="input-icon">
            <span class="material-icons">cake</span>
            <input type="date" name="birthday" required>
          </span>
        </label>

        <label style="margin-bottom: 32px">Gender
          <div class="gender-options">
            <label>
              <input type="radio" name="gender" value="Male" required>
              <span>Male</span>
            </label>
            <label>
              <input type="radio" name="gender" value="Female" required>
              <span>Female</span>
            </label>
            <label>
              <input type="radio" name="gender" value="Other" required>
              <span>Other</span>
            </label>
          </div>
        </label>

        <button class="primary-btn ripple" type="submit" style="width: 100%">Get Started</button>
      </form>
    </div>
  </main>
</body>
</html>
