<?php
/**
 * Yandex Cloud Foundation Models (YandexGPT).
 * Скопируйте в yandex_gpt_config.php на сервере (не в Git).
 *
 * === Пошаговое подключение ===
 *
 * 1. Зайдите в https://console.cloud.yandex.ru/
 * 2. Выберите или создайте «Каталог» (folder).
 *    Скопируйте ID каталога — это YANDEX_GPT_FOLDER_ID (вид: b1gxxxxxxxxxx).
 * 3. «Сервисные аккаунты» → Создать → имя, например legion-gpt.
 * 4. Назначьте роль сервисному аккаунту:
 *    ai.languageModels.user  (достаточно для Completion API)
 * 5. Откройте сервисный аккаунт → «Создать API-ключ».
 *    Скопируйте ключ — это YANDEX_GPT_API_KEY (вид: AQVN...).
 * 6. Убедитесь, что на каталоге есть баланс / включён биллинг
 *    (YandexGPT — платный, есть бесплатный лимит на старте).
 * 7. Скопируйте этот файл в yandex_gpt_config.php и вставьте ключи.
 * 8. Откройте отчёт с &ai=1 в конце ссылки.
 *
 * Модели:
 *   yandexgpt-lite — быстрее и дешевле (для тестов)
 *   yandexgpt      — качественнее
 */
define('YANDEX_GPT_API_KEY', 'AQVN...');
define('YANDEX_GPT_FOLDER_ID', 'b1g...');
define('YANDEX_GPT_MODEL', 'yandexgpt-lite');
