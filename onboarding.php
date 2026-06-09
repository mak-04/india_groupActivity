<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

$user = current_user();
if (!$user) {
    header('Location: index.php?mode=login');
    exit;
}

// If they already have both set, go straight to dashboard
if ($user['birthday'] && $user['gender']) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $birthday = trim($_POST['birthday'] ?? '');
    $gender = trim($_POST['gender'] ?? '');

    if ($birthday === '' || $gender === '') {
        $error = 'Please fill in both your birthday and gender.';
    } else {
        try {
            $stmt = db()->prepare('UPDATE users SET birthday = ?, gender = ? WHERE id = ?');
            $stmt->execute([$birthday, $gender, $user['id']]);
            header('Location: dashboard.php');
            exit;
        } catch (Throwable $e) {
            $error = 'Failed to update profile. Please try again.';
        }
    }
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Complete Your Profile — <?= APP_NAME ?></title>
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
      <p>Almost there! Let's get to know you better.</p>
    </section>

    <!-- Auth card (right) -->
    <section class="auth-card">
      <form class="auth-form" method="post" action="onboarding.php">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <h2>Complete Profile</h2>
        <p style="color:var(--gray);font-size:0.9rem;margin-bottom:24px">Please provide your birthday and gender to finish setting up your account.</p>

        <?php if ($error): ?>
          <div class="alert error"><?= e($error) ?></div>
        <?php endif; ?>

        <label>Birthday
          <span class="input-icon" style="padding:0">
            <input type="date" name="birthday" required style="width:100%; padding:12px 14px; background:rgba(0,0,0,0.03); border:1px solid rgba(0,0,0,0.1); border-radius:8px; color:var(--black); font-size:0.9rem; outline:none; transition:border-color 0.2s, box-shadow 0.2s;" onfocus="this.style.borderColor='var(--blue)'; this.style.boxShadow='0 0 0 3px rgba(32,88,220,0.15)';" onblur="this.style.borderColor='rgba(0,0,0,0.1)'; this.style.boxShadow='none';">
          </span>
        </label>

        <label>Gender
          <span class="input-icon" style="padding:0">
            <select name="gender" required style="width:100%; padding:12px 14px; background:rgba(0,0,0,0.03); border:1px solid rgba(0,0,0,0.1); border-radius:8px; color:var(--black); font-size:0.9rem; outline:none; transition:border-color 0.2s, box-shadow 0.2s; cursor:pointer; appearance:none; -webkit-appearance:none; background-image:url('data:image/svg+xml;utf8,<svg fill=%22%23737373%22 height=%2224%22 viewBox=%220 0 24 24%22 width=%2224%22 xmlns=%22http://www.w3.org/2000/svg%22><path d=%22M7 10l5 5 5-5z%22/></svg>'); background-repeat:no-repeat; background-position-x:calc(100% - 12px); background-position-y:50%;" onfocus="this.style.borderColor='var(--blue)'; this.style.boxShadow='0 0 0 3px rgba(32,88,220,0.15)';" onblur="this.style.borderColor='rgba(0,0,0,0.1)'; this.style.boxShadow='none';">
              <option value="" disabled selected>Select Gender</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Prefer Not To Say">Prefer Not To Say</option>
            </select>
          </span>
        </label>

        <button class="primary-btn ripple" type="submit" style="margin-top:16px">Proceed to Dashboard</button>
      </form>
    </section>
  </main>
</body>
</html>
