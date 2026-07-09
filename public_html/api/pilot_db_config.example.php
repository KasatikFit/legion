<?php
/**
 * Скопируйте в pilot_db_config.php на сервере (не в Git).
 *
 * Если файла нет — используется SQLite: api/data/pilot-demo.sqlite
 *
 * Для MySQL на Beget: создайте БД в панели, укажите данные ниже.
 */
define('PILOT_DB_DRIVER', 'mysql');
define('PILOT_DB_HOST', 'localhost');
define('PILOT_DB_NAME', 'ваша_база');
define('PILOT_DB_USER', 'ваш_пользователь');
define('PILOT_DB_PASS', 'ваш_пароль');
define('PILOT_DB_CHARSET', 'utf8mb4');

// SQLite (раскомментируйте вместо MySQL):
// define('PILOT_DB_DRIVER', 'sqlite');
// define('PILOT_DB_SQLITE_PATH', __DIR__ . '/data/pilot-demo.sqlite');
