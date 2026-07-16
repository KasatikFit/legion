-- Реестр тренеров и пароли режима тренировки.
-- Таблицы создаются автоматически при первом обращении к coaches_lib.php;
-- этот файл — для документации и ручного развёртывания.

CREATE TABLE IF NOT EXISTS legion_coaches (
    slug VARCHAR(64) NOT NULL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    tagline VARCHAR(255) NOT NULL DEFAULT 'Группа тренера',
    storage VARCHAR(16) NOT NULL DEFAULT 'mysql',
    csv_url TEXT NOT NULL,
    ranks_csv_url TEXT NOT NULL,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS legion_coach_auth (
    coach_slug VARCHAR(64) NOT NULL PRIMARY KEY,
    password_hash VARCHAR(255) NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
