<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['user_id'])) {
    db()->prepare('UPDATE users SET remember_token = NULL WHERE id = ?')->execute([$_SESSION['user_id']]);
    db()->prepare('DELETE FROM user_sessions WHERE user_id = ?')->execute([$_SESSION['user_id']]);
}

setcookie('remember_token', '', time() - 3600, '/');
unset($_SESSION['user_id']);
session_regenerate_id(true);

header('Location: index.php?mode=login');
exit;