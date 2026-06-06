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

define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: 'YOUR_GEMINI_API_KEY_HERE');
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
    $stmt = db()->prepare('SELECT id, username, email, birthday, gender, created_at FROM users WHERE id = ?');
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