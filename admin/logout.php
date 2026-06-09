<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

$admin = current_admin();
if ($admin) {
    log_admin_action((int) $admin['id'], 'LOGOUT', 'admin', (int) $admin['id'], 'Admin logged out');
}

unset($_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_csrf']);
session_regenerate_id(true);

header('Location: index.php?msg=logged_out');
exit;