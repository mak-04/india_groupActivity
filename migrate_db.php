<?php
require_once __DIR__ . '/config/config.php';

try {
    // Rename fullname to username if it exists
    $stmt = db()->query("SHOW COLUMNS FROM users LIKE 'fullname'");
    if ($stmt->fetch()) {
        db()->exec("ALTER TABLE users CHANGE fullname username VARCHAR(120) NOT NULL");
        echo "Renamed fullname to username.\n";
    }

    // Add birthday
    $stmt = db()->query("SHOW COLUMNS FROM users LIKE 'birthday'");
    if (!$stmt->fetch()) {
        db()->exec("ALTER TABLE users ADD COLUMN birthday DATE DEFAULT NULL");
        echo "Added birthday column.\n";
    }

    // Add gender
    $stmt = db()->query("SHOW COLUMNS FROM users LIKE 'gender'");
    if (!$stmt->fetch()) {
        db()->exec("ALTER TABLE users ADD COLUMN gender VARCHAR(20) DEFAULT NULL");
        echo "Added gender column.\n";
    }

    // Create trash table if it does not exist
    db()->exec("
        CREATE TABLE IF NOT EXISTS trash (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            user_id    INT          NOT NULL,
            title      VARCHAR(180) NOT NULL,
            content    MEDIUMTEXT   NOT NULL,
            deleted_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "Checked trash table.\n";

    // Make username UNIQUE if not already
    try {
        db()->exec("ALTER TABLE users ADD UNIQUE (username)");
        echo "Added UNIQUE constraint to username.\n";
    } catch (PDOException $e) {
        // Will throw if duplicate constraint exists or if there are existing duplicate entries.
        // We catch it silently to prevent failing the migration if it's already unique.
        if (strpos($e->getMessage(), 'Duplicate') === false && strpos($e->getMessage(), '1061') === false) {
            echo "Notice on username unique constraint: " . $e->getMessage() . "\n";
        }
    }

    echo "Migration successful.\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
