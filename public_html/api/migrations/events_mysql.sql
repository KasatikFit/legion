-- Move Boss: игроки, прогресс, лидерборд, сессии.
-- Выполните в phpMyAdmin в той же базе, где pilot_* / legion_*.
-- Гостевая игра живёт в localStorage; в SQL — только после регистрации (ник+пароль).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS legion_mb_players (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nickname VARCHAR(40) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(40) NOT NULL DEFAULT '',
    streak_days INT UNSIGNED NOT NULL DEFAULT 0,
    lifetime_reps INT UNSIGNED NOT NULL DEFAULT 0,
    last_active_day CHAR(10) NOT NULL DEFAULT '',
    banners_cleared TINYINT UNSIGNED NOT NULL DEFAULT 0,
    challenges_cleared TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_legion_mb_players_nickname (nickname),
    KEY idx_legion_mb_players_leaderboard (banners_cleared, lifetime_reps)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS legion_mb_sessions (
    token CHAR(64) NOT NULL PRIMARY KEY,
    kind ENUM('player', 'admin') NOT NULL,
    player_id INT UNSIGNED NULL DEFAULT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_legion_mb_sessions_expires (expires_at),
    KEY idx_legion_mb_sessions_player (player_id),
    CONSTRAINT fk_legion_mb_sessions_player FOREIGN KEY (player_id)
        REFERENCES legion_mb_players (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS legion_mb_level_clears (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    player_id INT UNSIGNED NOT NULL,
    level_id VARCHAR(32) NOT NULL,
    total_reps INT UNSIGNED NOT NULL DEFAULT 0,
    exercise VARCHAR(32) NOT NULL DEFAULT 'pushup',
    cleared_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_legion_mb_level (player_id, level_id),
    CONSTRAINT fk_legion_mb_level_player FOREIGN KEY (player_id)
        REFERENCES legion_mb_players (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS legion_mb_challenge_bests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    player_id INT UNSIGNED NOT NULL,
    challenge_id VARCHAR(32) NOT NULL,
    time_ms INT UNSIGNED NOT NULL DEFAULT 0,
    target_reps INT UNSIGNED NOT NULL DEFAULT 0,
    best_reps INT UNSIGNED NULL DEFAULT NULL,
    exercise VARCHAR(32) NOT NULL DEFAULT 'pushup',
    cleared_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_legion_mb_challenge (player_id, challenge_id),
    CONSTRAINT fk_legion_mb_challenge_player FOREIGN KEY (player_id)
        REFERENCES legion_mb_players (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS legion_mb_runs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    player_id INT UNSIGNED NOT NULL,
    kind ENUM('arena', 'challenge') NOT NULL,
    ref_id VARCHAR(32) NOT NULL,
    reps INT UNSIGNED NOT NULL DEFAULT 0,
    duration_ms INT UNSIGNED NULL DEFAULT NULL,
    exercise VARCHAR(32) NOT NULL DEFAULT 'pushup',
    completed TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_legion_mb_runs_player (player_id, created_at),
    CONSTRAINT fk_legion_mb_runs_player FOREIGN KEY (player_id)
        REFERENCES legion_mb_players (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
