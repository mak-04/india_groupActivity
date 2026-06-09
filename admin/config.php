<?php
declare(strict_types=1);
// Admin config - lives in admin/ folder; includes parent project config
require_once dirname(__DIR__) . '/config/config.php';

// ── Admin session ─────────────────────────────────────────────
function current_admin(): ?array
{
    if (!isset($_SESSION['admin_id'])) return null;
    
    // Check if logged in via ENV
    if ($_SESSION['admin_id'] === 0 && !empty($_SESSION['admin_username'])) {
        return ['id' => 0, 'username' => $_SESSION['admin_username']];
    }
    
    $stmt = db()->prepare('SELECT id, username FROM admins WHERE id = ?');
    $stmt->execute([$_SESSION['admin_id']]);
    return $stmt->fetch() ?: null;
}

function require_admin_auth(): array
{
    $admin = current_admin();
    if (!$admin) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || !empty($_SERVER['HTTP_X_CSRF_TOKEN']) || !empty($_SERVER['HTTP_X_ADMIN_CSRF'])) {
            http_response_code(401);
            exit(json_encode(['ok' => false, 'message' => 'Session expired. Please log in again.']));
        }
        header('Location: index.php');
        exit;
    }
    return $admin;
}

// ── Admin CSRF ────────────────────────────────────────────────
function admin_csrf_token(): string
{
    if (empty($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf'];
}

function verify_admin_csrf(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_ADMIN_CSRF'] ?? '';
    if (!hash_equals($_SESSION['admin_csrf'] ?? '', $token)) {
        http_response_code(419);
        exit(json_encode(['ok' => false, 'message' => 'Security token expired. Please refresh the page.']));
    }
}

// ── Brute-force / rate limiting ───────────────────────────────
function check_login_rate_limit(string $ip): bool
{
    try {
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM admin_login_attempts
             WHERE ip_address = ? AND success = 0
             AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)'
        );
        $stmt->execute([$ip]);
        return (int) $stmt->fetchColumn() < 10;
    } catch (\Throwable $e) { return true; }
}

function record_login_attempt(string $username, string $ip, bool $success): void
{
    try {
        db()->prepare('INSERT INTO admin_login_attempts (username, ip_address, success) VALUES (?, ?, ?)')
           ->execute([$username, $ip, $success ? 1 : 0]);
    } catch (\Throwable $e) { /* non-critical */ }
}

// ── Online status ─────────────────────────────────────────────
function is_user_online(int $userId): bool
{
    try {
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM user_sessions
             WHERE user_id = ? AND last_activity > DATE_SUB(NOW(), INTERVAL 5 MINUTE)'
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (\Throwable $e) { return false; }
}

// ── Audit logging ─────────────────────────────────────────────
function log_admin_action(int $adminId, string $action, string $targetType = '', ?int $targetId = null, string $details = ''): void
{
    try {
        db()->prepare(
            'INSERT INTO activity_logs (admin_id, action, target_type, target_id, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$adminId, $action, $targetType, $targetId, $details, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (\Throwable $e) { /* non-critical */ }
}

// ── Auto-create user profile if missing ───────────────────────
function ensure_user_profile(int $userId): void
{
    try {
        db()->prepare('INSERT IGNORE INTO user_profiles (user_id) VALUES (?)')->execute([$userId]);
    } catch (\Throwable $e) { /* table may not exist yet */ }
}