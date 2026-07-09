<?php
// Скопируйте в pilot_auth.php на сервере (не в Git).
// Пароль для /pilot-demo/training/

// Вариант 1 — простой пароль (для теста):
define('PILOT_PASSWORD', 'pilot2026');

// Вариант 2 — хэш (надёжнее). Сгенерировать: password_hash('ваш-пароль', PASSWORD_DEFAULT)
// define('PILOT_PASSWORD_HASH', '$2y$10$...');
