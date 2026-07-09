<?php



require_once __DIR__ . '/results_lib.php';

require_once __DIR__ . '/ranks_lib.php';

require_once __DIR__ . '/sheets_cache_lib.php';



define('LEGION_PAGE_DATA_CACHE_TTL', 120);



function legion_page_data_cache_key($coachSlugFilter = null) {

    return $coachSlugFilter ? 'coach:' . $coachSlugFilter : 'club';

}



function legion_page_data_cache_file($coachSlugFilter = null) {

    legion_sheets_cache_ensure_dir();

    return LEGION_SHEETS_CACHE_DIR . '/page_' . sha1(legion_page_data_cache_key($coachSlugFilter)) . '.json';

}



/**

 * @return array|null

 */

function legion_page_data_cache_read($coachSlugFilter = null) {

    $file = legion_page_data_cache_file($coachSlugFilter);

    if (!file_exists($file)) {

        return null;

    }

    $raw = @file_get_contents($file);

    if ($raw === false) {

        return null;

    }

    $data = json_decode($raw, true);

    if (!$data || !isset($data['payload'], $data['time'])) {

        return null;

    }

    $age = time() - (int) $data['time'];

    if ($age >= LEGION_PAGE_DATA_CACHE_TTL) {

        return null;

    }

    $payload = $data['payload'];

    if (!is_array($payload)) {

        return null;

    }

    $payload['_cacheAge'] = $age;

    return $payload;

}



function legion_page_data_cache_write($coachSlugFilter, array $payload) {

    legion_sheets_cache_ensure_dir();

    $file = legion_page_data_cache_file($coachSlugFilter);

    unset($payload['_cacheAge']);

    $encoded = json_encode(array(

        'time' => time(),

        'payload' => $payload,

    ), JSON_UNESCAPED_UNICODE);

    if ($encoded === false) {

        return false;

    }

    return @file_put_contents($file, $encoded, LOCK_EX) !== false;

}



/**

 * Все уникальные URL таблиц для рейтинга + рангов (один проход curl_multi).

 *

 * @return string[]

 */

function legion_collect_rating_sheet_urls($coachSlugFilter = null) {

    $coaches = legion_coaches_config();

    $urls = array();



    foreach ($coaches as $slug => $coach) {

        if ($coachSlugFilter === null || $slug === $coachSlugFilter) {

            if (!empty($coach['csvUrl'])) {

                $urls[] = $coach['csvUrl'];

            }

        }

        if (!empty($coach['ranksCsvUrl'])) {

            $urls[] = $coach['ranksCsvUrl'];

        }

    }



    // Для сопоставления ФИО в рангах нужны все таблицы результатов.

    foreach ($coaches as $coach) {

        if (!empty($coach['csvUrl'])) {

            $urls[] = $coach['csvUrl'];

        }

    }



    return array_values(array_unique($urls));

}



/**

 * Спортсмены + ранги за один запрос к Google Таблицам.

 *

 * @return array{athletes:array,coachBenchmarks:array,warnings:array,ranks:array,loaded:int,coaches:array}

 */

function legion_build_page_data($coachSlugFilter = null) {

    $fetched = legion_fetch_sheets_parallel(legion_collect_rating_sheet_urls($coachSlugFilter));

    $athletes = legion_load_all_athletes($coachSlugFilter, $fetched);

    $ranks = legion_load_all_ranks($fetched);



    return array(

        'athletes' => $athletes['athletes'],

        'coachBenchmarks' => $athletes['coachBenchmarks'],

        'warnings' => $athletes['warnings'],

        'ranks' => $ranks['ranks'],

        'loaded' => $ranks['loaded'],

        'coaches' => $ranks['coaches'],

        'ranksFromServer' => true,

    );

}



function legion_load_page_data($coachSlugFilter = null) {

    $cached = legion_page_data_cache_read($coachSlugFilter);

    if ($cached !== null) {

        return $cached;

    }



    $payload = legion_build_page_data($coachSlugFilter);

    legion_page_data_cache_write($coachSlugFilter, $payload);

    return $payload;

}



/**

 * Прогрев кэша таблиц и готового ответа (cron).

 */

function legion_warm_rating_cache() {

    legion_load_page_data(null);

    foreach (array_keys(legion_coaches_config()) as $slug) {

        legion_load_page_data($slug);

    }

    return true;

}


