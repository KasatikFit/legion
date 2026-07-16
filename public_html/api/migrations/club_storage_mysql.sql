-- Клубные данные (элита, достижения по scope, снимки). Создаются автоматически при первом запросе.
-- Схема v5 в legion_pilot_db_apply_migrations.

CREATE TABLE IF NOT EXISTS legion_coach_elite (
    coach_slug VARCHAR(64) NOT NULL PRIMARY KEY,
    elite_names MEDIUMTEXT NOT NULL,
    last_rotation_month VARCHAR(7) NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS legion_scope_achievements (
    scope VARCHAR(64) NOT NULL,
    person_name VARCHAR(255) NOT NULL,
    achievement_id VARCHAR(64) NOT NULL,
    granted_at DATE NOT NULL,
    PRIMARY KEY (scope, person_name, achievement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS legion_scope_snapshots (
    scope VARCHAR(64) NOT NULL,
    snapshot_kind VARCHAR(32) NOT NULL,
    payload MEDIUMTEXT NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (scope, snapshot_kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
