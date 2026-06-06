<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['user_id'])) {
    db()->prepare('UPDATE users SET remember_token = NULL WHERE id = ?')->execute([$_SESSION['user_id']]);
}

setcookie('remember_token', '', time() - 3600, '/');
    session_destroy();
header('Location: index.php?mode=login');
exit;