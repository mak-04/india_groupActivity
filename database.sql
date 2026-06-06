-- AI Study Tracker & Reviewer — Database Schema
CREATE DATABASE IF NOT EXISTS ai_study_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ai_study_tracker;

CREATE TABLE IF NOT EXISTS users (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    username       VARCHAR(120) NOT NULL,
    email          VARCHAR(160) NOT NULL UNIQUE,
    password       VARCHAR(255) NOT NULL,
    birthday       DATE         DEFAULT NULL,
    gender         VARCHAR(20)  DEFAULT NULL,
    reset_token    VARCHAR(128) DEFAULT NULL,
    reset_expires  DATETIME     DEFAULT NULL,
    remember_token VARCHAR(128) DEFAULT NULL,
    created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS usage_limits (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT  NOT NULL UNIQUE,
    hourly_count    INT  NOT NULL DEFAULT 0,
    daily_count     INT  NOT NULL DEFAULT 0,
    last_reset_hour DATETIME NOT NULL,
    last_reset_day  DATE     NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS history (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    title      VARCHAR(180) NOT NULL,
    content    MEDIUMTEXT   NOT NULL,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS pinned (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    history_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_pin (user_id, history_id),
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (history_id) REFERENCES history(id)  ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS archive (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL,
    title       VARCHAR(180) NOT NULL,
    content     MEDIUMTEXT   NOT NULL,
    archived_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Soft-delete trash bin (chats moved here when "deleted" from History/Recent)
CREATE TABLE IF NOT EXISTS trash (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    title      VARCHAR(180) NOT NULL,
    content    MEDIUMTEXT   NOT NULL,
    deleted_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS quiz_scores (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT            NOT NULL,
    score           INT            NOT NULL,
    total_questions INT            NOT NULL,
    percentage      DECIMAL(5, 2)  NOT NULL,
    created_at      TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS ai_cache (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    topic      VARCHAR(255) NOT NULL,
    response   MEDIUMTEXT   NOT NULL,
    mode       ENUM('lesson', 'quiz', 'chat') NOT NULL DEFAULT 'lesson',
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_topic_mode (topic, mode)
);

-- Migration helper: add trash table if upgrading an existing install
-- (safe to run even if table already exists due to IF NOT EXISTS above)