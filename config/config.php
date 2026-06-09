<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

define('APP_NAME', 'AI Study Tracker & Reviewer');
define('BASE_URL',  '');

// Simple .env parser
$envPath = dirname(__DIR__) . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

/**
 * ── GEMINI API CONFIGURATION ───────────────────────────────────────────────
 * Set environment variables OR replace 'YOUR_GEMINI_API_KEY_HERE' below.
 * Get a free API key: https://aistudio.google.com/
 *
 * Free-tier models confirmed working as of June 2026:
 *   gemini-2.5-flash      → 10 RPM / 1,500 RPD  ← recommended default
 *   gemini-2.5-flash-lite → 15 RPM / higher RPD  ← automatic fallback
 *
 * DEAD MODELS (shut down — do NOT use):
 *   gemini-1.5-flash, gemini-1.5-pro  → shut down, return 404
 *   gemini-2.0-flash, gemini-2.0-flash-lite → shut down June 1, 2026
 */
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
define('GEMINI_MODEL',   getenv('GEMINI_MODEL')   ?: 'gemini-2.5-flash');

define('HOURLY_AI_LIMIT', 20);
define('DAILY_AI_LIMIT',  100);

// ── Paths ─────────────────────────────────────────────────────────────────
define('UPLOAD_DIR',       dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads');
define('SESSION_DIR',      dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions');
define('PYTHON_EXTRACTOR', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'python'  . DIRECTORY_SEPARATOR . 'extract_text.py');

if (!is_dir(UPLOAD_DIR))  { mkdir(UPLOAD_DIR,  0775, true); }
if (!is_dir(SESSION_DIR)) { mkdir(SESSION_DIR, 0775, true); }

// ── Session ───────────────────────────────────────────────────────────────
ini_set('session.save_path', SESSION_DIR);
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ── Database Connection ──────────────────────────────────── */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) { return $pdo; }
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $name = getenv('DB_NAME') ?: 'ai_study_tracker';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $pdo  = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

function ensure_feedback_schema(): void
{
    static $done = false;
    if ($done) { return; }

    $pdo = db();
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS feedback (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            user_id     INT          DEFAULT NULL,
            message     TEXT         NOT NULL,
            rating      TINYINT(1)   DEFAULT NULL COMMENT '1-5 stars, NULL = no rating',
            is_reviewed TINYINT(1)   NOT NULL DEFAULT 0,
            is_archived TINYINT(1)   NOT NULL DEFAULT 0,
            sentiment   ENUM('positive','neutral','negative') DEFAULT 'neutral',
            created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )"
    );

    $columns = [];
    foreach ($pdo->query('SHOW COLUMNS FROM feedback')->fetchAll() as $column) {
        $columns[$column['Field']] = true;
    }

    if (empty($columns['rating'])) {
        $pdo->exec("ALTER TABLE feedback ADD COLUMN rating TINYINT(1) DEFAULT NULL COMMENT '1-5 stars, NULL = no rating' AFTER message");
    }
    if (empty($columns['is_reviewed'])) {
        $pdo->exec("ALTER TABLE feedback ADD COLUMN is_reviewed TINYINT(1) NOT NULL DEFAULT 0 AFTER rating");
    }
    if (empty($columns['is_archived'])) {
        $pdo->exec("ALTER TABLE feedback ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER is_reviewed");
    }
    if (empty($columns['sentiment'])) {
        $pdo->exec("ALTER TABLE feedback ADD COLUMN sentiment ENUM('positive','neutral','negative') DEFAULT 'neutral' AFTER is_archived");
    }

    $done = true;
}

function feedback_sentiment(?int $rating, string $message = ''): string
{
    if ($rating !== null) {
        if ($rating >= 4) { return 'positive'; }
        if ($rating <= 2) { return 'negative'; }
    }
    return 'neutral';
}

/* ── Helper Functions ─────────────────────────────────────── */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        exit(json_encode(['ok' => false, 'message' => 'Security token expired. Please refresh the page.']));
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) { return null; }
    $stmt = db()->prepare('
        SELECT u.id, u.username, u.email, u.birthday, u.gender, u.created_at, COALESCE(up.is_suspended, 0) AS is_suspended
        FROM users u
        LEFT JOIN user_profiles up ON up.user_id = u.id
        WHERE u.id = ?
    ');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        http_response_code(401);
        exit(json_encode(['ok' => false, 'message' => 'Please login first.']));
    }
    if (!empty($user['is_suspended'])) {
        http_response_code(403);
        exit(json_encode(['ok' => false, 'suspended' => true, 'message' => 'Your account has been suspended by the administrator.']));
    }
    return $user;
}

function redirect_if_logged_in(): void
{
    if (current_user()) {
        header('Location: dashboard.php');
        exit;
    }
}

function password_is_strong(string $password): bool
{
    return strlen($password) >= 8
        && strlen($password) <= 30
        && preg_match('/[A-Z]/', $password)
        && preg_match('/[a-z]/', $password)
        && preg_match('/\d/', $password)
        && preg_match('/[^A-Za-z0-9]/', $password);
}

/**
 * Educational guard — blocks explicit non-educational intents only.
 * Permissive by design: any real subject can be studied.
 */
function educational_guard(string $text): bool
{
    $blocked = [
        'write me a story', 'tell me a joke', 'roast me',
        'dating advice', 'pick up line', 'what should i eat',
        'recommend a movie', 'recommend a game',
    ];
    $lower = strtolower($text);
    foreach ($blocked as $phrase) {
        if (str_contains($lower, $phrase)) {
            return false;
        }
    }
    return true;
}
