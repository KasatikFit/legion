<?php

function legion_yandex_gpt_config_path() {
    return __DIR__ . '/yandex_gpt_config.php';
}

function legion_yandex_gpt_is_configured() {
    $path = legion_yandex_gpt_config_path();
    if (!is_file($path)) {
        return false;
    }
    require_once $path;
    return defined('YANDEX_GPT_API_KEY') && YANDEX_GPT_API_KEY !== ''
        && defined('YANDEX_GPT_FOLDER_ID') && YANDEX_GPT_FOLDER_ID !== '';
}

function legion_yandex_gpt_completion($systemText, $userText) {
    if (!legion_yandex_gpt_is_configured()) {
        throw new RuntimeException('YandexGPT не настроен (yandex_gpt_config.php)');
    }
    require_once legion_yandex_gpt_config_path();

    $folderId = (string) YANDEX_GPT_FOLDER_ID;
    $model = defined('YANDEX_GPT_MODEL') && YANDEX_GPT_MODEL !== ''
        ? (string) YANDEX_GPT_MODEL
        : 'yandexgpt-lite';

    $payload = array(
        'modelUri' => 'gpt://' . $folderId . '/' . $model . '/latest',
        'completionOptions' => array(
            'stream' => false,
            'temperature' => 0.35,
            'maxTokens' => 1200,
        ),
        'messages' => array(
            array('role' => 'system', 'text' => (string) $systemText),
            array('role' => 'user', 'text' => (string) $userText),
        ),
    );

    $url = 'https://llm.api.cloud.yandex.net/foundationModels/v1/completion';
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

    if (!function_exists('curl_init')) {
        throw new RuntimeException('Для YandexGPT нужен PHP cURL');
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Authorization: Api-Key ' . YANDEX_GPT_API_KEY,
            'x-folder-id: ' . $folderId,
        ),
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 60,
    ));
    $raw = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('YandexGPT: ' . ($curlErr ?: 'ошибка сети'));
    }

    $decoded = json_decode($raw, true);
    if ($httpCode < 200 || $httpCode >= 300) {
        $msg = is_array($decoded) && isset($decoded['message'])
            ? $decoded['message']
            : ('HTTP ' . $httpCode);
        throw new RuntimeException('YandexGPT: ' . $msg);
    }

    if (!is_array($decoded)
        || empty($decoded['result']['alternatives'][0]['message']['text'])) {
        throw new RuntimeException('YandexGPT: пустой ответ');
    }

    return trim((string) $decoded['result']['alternatives'][0]['message']['text']);
}

function legion_yandex_gpt_dossier_recommendations(array $dossier) {
    require_once __DIR__ . '/athlete_dossier_lib.php';
    $age = isset($dossier['athlete']['age']) ? $dossier['athlete']['age'] : null;
    $ageHint = $age !== null ? ' Возраст спортсмена: ' . (int) $age . ' лет.' : '';
    $system = 'Ты составляешь аналитический текст отчёта для родителя юного спортсмена группы функционального фитнеса «Легион».' . $ageHint . ' '
        . 'Проанализируй результаты, прогресс, сданные нормативы на ранг и конкуренцию в группе и клубе по каждому упражнению. '
        . 'В блоке конкуренция_по_упражнениям_в_группе и конкуренция_по_упражнениям_в_клубе для каждого упражнения указаны место, нужно_улучшить_для_места_выше, запас_перед_преследователем и флаг лидер_в_упражнении. '
        . 'Обязательно отрази это в тексте: где ребёнок лидирует и с каким запасом, а где есть реальный шанс подняться на место выше и сколько для этого нужно. '
        . 'Если передан блок дистанция_в_общем_рейтинге — упомяни, насколько близко ребёнок к более высокому общему месту в клубе (без баллов и технических деталей). '
        . 'Если передан блок итог_за_период — кратко отрази недавнюю динамику улучшений за указанный период. '
        . 'По сданным ранговым нормативам и профилю_упражнений (радар) сделай вывод о развитии физических качеств (сила, выносливость, координация, ловкость, гибкость). '
        . 'Если элита=true — можно тёпло отметить статус элиты клуба. '
        . 'Напиши 3–4 связных абзаца тёплым языком для родителя. Между абзацами — одна пустая строка, без заголовков, списков и markdown. '
        . 'Опирайся только на переданные данные. Не ставь диагнозы и не пугай. Не упоминай рейтинговые баллы.';
    $user = legion_dossier_ai_prompt_text($dossier);
    return legion_yandex_gpt_completion($system, $user);
}
