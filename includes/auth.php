<?php
// BUG FIX: original auth.php used __DIR__ . '/../config/config.php'
// which is correct when auth.php lives in /includes/. Kept as-is.
require_once __DIR__ . '/../config/config.php';

// Auto-login via remember_token cookie
// BUG FIX: compare hashed token (sha256) stored in DB, not raw cookie value
if (!empty($_COOKIE['remember_token']) && empty($_SESSION['user_id'])) {
    $stmt = db()->prepare('SELECT id FROM users WHERE remember_token = ?');
    $stmt->execute([hash('sha256', $_COOKIE['remember_token'])]);
    $user = $stmt->fetch();
    if ($user) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
    }
}

// Track online status
if (!empty($_SESSION['user_id'])) {
    $sessHash = session_id();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    $stmt = db()->prepare("UPDATE user_sessions SET last_activity = NOW(), ip_address = ? WHERE session_hash = ?");
    $stmt->execute([$ip, $sessHash]);
    
    if ($stmt->rowCount() === 0) {
        db()->prepare("INSERT IGNORE INTO user_sessions (user_id, session_hash, ip_address) VALUES (?, ?, ?)")
            ->execute([$_SESSION['user_id'], $sessHash, $ip]);
    }
}
