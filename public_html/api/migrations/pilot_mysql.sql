-- Пилотная группа: схема для MySQL (Beget).
-- Выполните в phpMyAdmin после создания базы.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS pilot_meta (
    meta_key VARCHAR(64) NOT NULL PRIMARY KEY,
    meta_value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pilot_athletes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    photo VARCHAR(512) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_pilot_athletes_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pilot_results (
    athlete_id INT UNSIGNED NOT NULL,
    exercise VARCHAR(32) NOT NULL,
    value DOUBLE NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (athlete_id, exercise),
    CONSTRAINT fk_pilot_results_athlete FOREIGN KEY (athlete_id)
        REFERENCES pilot_athletes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pilot_rank_marks (
    athlete_id INT UNSIGNED NOT NULL,
    mark_index SMALLINT UNSIGNED NOT NULL,
    value TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (athlete_id, mark_index),
    CONSTRAINT fk_pilot_rank_marks_athlete FOREIGN KEY (athlete_id)
        REFERENCES pilot_athletes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pilot_history (
    id VARCHAR(32) NOT NULL PRIMARY KEY,
    athlete_id INT UNSIGNED NOT NULL,
    exercise VARCHAR(32) NOT NULL,
    old_val DOUBLE NULL,
    new_val DOUBLE NULL,
    diff DOUBLE NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    KEY idx_pilot_history_athlete (athlete_id, created_at),
    CONSTRAINT fk_pilot_history_athlete FOREIGN KEY (athlete_id)
        REFERENCES pilot_athletes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pilot_achievements (
    athlete_id INT UNSIGNED NOT NULL,
    achievement_id VARCHAR(64) NOT NULL,
    granted_at DATE NOT NULL,
    PRIMARY KEY (athlete_id, achievement_id),
    CONSTRAINT fk_pilot_achievements_athlete FOREIGN KEY (athlete_id)
        REFERENCES pilot_athletes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
