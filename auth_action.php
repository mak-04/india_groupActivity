<?php
declare(strict_types=1);
// BUG FIX: original file required __DIR__ . '/includes/auth.php' which is correct
// only when auth_action.php is in the project root. Verified — kept as-is.
require_once __DIR__ . '/includes/auth.php';
verify_csrf();

$action = $_POST['action'] ?? '';

try {
    if ($action === 'signup') {
        $email   = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $pass    = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!$email) {
            throw new RuntimeException('Please enter a valid email address.');
        }
        if ($pass !== $confirm) {
            throw new RuntimeException('Passwords do not match.');
        }
        if (!password_is_strong($pass)) {
            throw new RuntimeException('Password does not meet the requirements. Please use 8–30 characters with uppercase, lowercase, number, and special character.');
        }

        $check = db()->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            throw new RuntimeException('This email is already registered. Please log in.');
        }

        $displayName = ucfirst(explode('@', $email)[0]);

        $stmt = db()->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
        $stmt->execute([$displayName, $email, password_hash($pass, PASSWORD_DEFAULT)]);
        
        // Auto-login the user
        $userId = (int) db()->lastInsertId();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;

        header('Location: onboarding.php');
        exit;
    }

    if ($action === 'login') {
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $pass  = $_POST['password'] ?? '';

        if (!$email || !$pass) {
            throw new RuntimeException('Please fill in all fields.');
        }

        $stmt = db()->prepare('
            SELECT u.*, COALESCE(up.is_suspended, 0) as is_suspended 
            FROM users u 
            LEFT JOIN user_profiles up ON up.user_id = u.id 
            WHERE u.email = ?
        ');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($pass, $user['password'])) {
            throw new RuntimeException('Invalid email or password.');
        }
        
        if (!empty($user['is_suspended'])) {
            throw new RuntimeException('You cannot login because your account has been suspended.');
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];

        if (!empty($_POST['remember'])) {
            $token = bin2hex(random_bytes(32));
            db()->prepare('UPDATE users SET remember_token = ? WHERE id = ?')
               ->execute([hash('sha256', $token), $user['id']]);
            // BUG FIX: secure flag should be true in production (HTTPS).
            // Set to false here for localhost XAMPP compatibility only.
            setcookie('remember_token', $token, time() + 60 * 60 * 24 * 30, '/', '', false, true);
            setcookie('remember_email', $email, time() + 60 * 60 * 24 * 30, '/', '', false, true);
        } else {
            setcookie('remember_email', '', time() - 3600, '/');
        }

        header('Location: dashboard.php');
        exit;
    }

} catch (Throwable $e) {
    $target = ($action === 'signup') ? 'index.php?mode=signup' : 'index.php?mode=login';
    header('Location: ' . $target . '&error=' . urlencode($e->getMessage()));
    exit;
}

header('Location: index.php');
exit;
