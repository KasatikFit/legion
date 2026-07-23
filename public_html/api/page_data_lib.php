<?php



require_once __DIR__ . '/results_lib.php';

require_once __DIR__ . '/ranks_lib.php';

require_once __DIR__ . '/sheets_cache_lib.php';
require_once __DIR__ . '/coaches.php';

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

function legion_page_data_cache_clear($coachSlug = null) {
    legion_sheets_cache_ensure_dir();
    @unlink(legion_page_data_cache_file(null));
    if ($coachSlug !== null && $coachSlug !== '') {
        @unlink(legion_page_data_cache_file($coachSlug));
    }
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

/** Все группы тренеров в MySQL — общий рейтинг с сервера. */
function legion_club_uses_server_storage() {
    $coaches = legion_coaches_config();
    if (empty($coaches)) {
        return false;
    }
    foreach ($coaches as $slug => $coach) {
        if (!legion_coach_uses_mysql($slug)) {
            return false;
        }
    }
    return true;
}

/**
 * Общий рейтинг клуба: все спортсмены и ранги из MySQL (данные тренеров).
 *
 * @return array{athletes:array,coachBenchmarks:array,warnings:array,ranks:array,history:array,achievements:array,loaded:int,coaches:array,storage:string}
 */
function legion_build_club_page_data_from_mysql() {
    require_once __DIR__ . '/pilot_lib.php';
    require_once __DIR__ . '/club_storage_lib.php';

    $athletes = array();
    $coachBenchmarks = array();
    $warnings = array();
    $ranks = array();
    $history = array();
    $achievements = array();
    $coachStats = array();
    $loaded = 0;

    foreach (legion_coaches_config() as $slug => $coach) {
        if (!legion_coach_uses_mysql($slug)) {
            $warnings[] = array(
                'coach' => $coach['name'],
                'slug' => $slug,
                'message' => 'Группа не переведена на MySQL',
            );
            continue;
        }

        try {
            $dataset = legion_pilot_dataset_for_api($slug, array(
                'includeHistory' => false,
                'recomputeAchievements' => false,
                'includeArchivedList' => false,
            ));
        } catch (Exception $e) {
            $warnings[] = array(
                'coach' => $coach['name'],
                'slug' => $slug,
                'message' => $e->getMessage(),
            );
            continue;
        }

        foreach ($dataset['athletes'] as $row) {
            if (is_array($row) && !empty($row['name'])) {
                $athletes[] = $row;
            }
        }

        if (!empty($dataset['coachBenchmark']) && is_array($dataset['coachBenchmark'])) {
            $coachBenchmarks[$slug] = $dataset['coachBenchmark'];
        }

        if (!empty($dataset['ranks']) && is_array($dataset['ranks'])) {
            foreach ($dataset['ranks'] as $key => $marks) {
                $ranks[$key] = $marks;
            }
        }

        if (!empty($dataset['achievements']) && is_array($dataset['achievements'])) {
            foreach ($dataset['achievements'] as $name => $list) {
                $achievements[$name] = $list;
            }
        }

        if (empty($dataset['athletes'])) {
            $warnings[] = array(
                'coach' => $coach['name'],
                'slug' => $slug,
                'message' => 'Нет спортсменов в базе — импортируйте в режиме тренировки',
            );
        }

        $loaded++;
        $coachStats[] = array(
            'slug' => $slug,
            'name' => $coach['name'],
            'ok' => true,
            'athletes' => count($dataset['athletes']),
        );
    }

    $achievements = legion_club_merge_achievement_maps(
        $achievements,
        legion_club_load_scope_achievements('global')
    );

    if (legion_club_storage_enabled()) {
        $history = legion_club_load_all_history();
    }

    return array(
        'athletes' => $athletes,
        'coachBenchmarks' => $coachBenchmarks,
        'warnings' => $warnings,
        'ranks' => $ranks,
        'history' => $history,
        'achievements' => $achievements,
        'loaded' => $loaded,
        'coaches' => $coachStats,
        'ranksFromServer' => true,
        'storage' => 'mysql',
    );
}

/**
 * Рейтинг одной группы из MySQL (fallback для get_page_data?coach=).
 */
function legion_build_coach_page_data_from_mysql($coachSlug) {
    require_once __DIR__ . '/pilot_lib.php';
    require_once __DIR__ . '/club_storage_lib.php';
    $coachSlug = legion_coach_normalize_slug($coachSlug);
    $dataset = legion_pilot_dataset_for_api($coachSlug, array(
        'includeArchivedList' => false,
    ));
    $meta = $dataset['coach'];

    $warnings = array();
    if (empty($dataset['athletes'])) {
        $warnings[] = array(
            'coach' => $meta['name'],
            'slug' => $coachSlug,
            'message' => 'Нет спортсменов в базе — импортируйте в режиме тренировки',
        );
    }

    $coachBenchmarks = array();
    if (!empty($dataset['coachBenchmark']) && is_array($dataset['coachBenchmark'])) {
        $coachBenchmarks[$coachSlug] = $dataset['coachBenchmark'];
    }

    return array(
        'athletes' => $dataset['athletes'],
        'coachBenchmarks' => $coachBenchmarks,
        'warnings' => $warnings,
        'ranks' => isset($dataset['ranks']) ? $dataset['ranks'] : array(),
        'history' => isset($dataset['history']) ? $dataset['history'] : array(),
        'achievements' => isset($dataset['achievements']) ? $dataset['achievements'] : array(),
        'loaded' => 1,
        'coaches' => array(array(
            'slug' => $coachSlug,
            'name' => $meta['name'],
            'ok' => true,
            'athletes' => count($dataset['athletes']),
        )),
        'ranksFromServer' => true,
        'storage' => 'mysql',
    );
}

function legion_load_page_data($coachSlugFilter = null) {

    $cached = legion_page_data_cache_read($coachSlugFilter);

    if ($cached !== null) {

        return $cached;

    }

    if ($coachSlugFilter === null) {
        if (!legion_club_uses_server_storage()) {
            $payload = array(
                'athletes' => array(),
                'coachBenchmarks' => array(),
                'warnings' => array(array(
                    'coach' => 'Клуб',
                    'slug' => '',
                    'message' => 'Не все группы переведены на MySQL. Проверьте /diagnostics/',
                )),
                'ranks' => array(),
                'history' => array(),
                'achievements' => array(),
                'rankHistory' => array(),
                'loaded' => 0,
                'coaches' => array(),
                'ranksFromServer' => true,
                'storage' => 'mysql',
            );
        } else {
            $payload = legion_build_club_page_data_from_mysql();
        }
    } elseif (legion_coach_uses_mysql($coachSlugFilter)) {
        $payload = legion_build_coach_page_data_from_mysql($coachSlugFilter);
    } else {
        $coaches = legion_coaches_config();
        $name = isset($coaches[$coachSlugFilter]['name']) ? $coaches[$coachSlugFilter]['name'] : $coachSlugFilter;
        $payload = array(
            'athletes' => array(),
            'coachBenchmarks' => array(),
            'warnings' => array(array(
                'coach' => $name,
                'slug' => $coachSlugFilter,
                'message' => 'Группа не переведена на MySQL',
            )),
            'ranks' => array(),
            'history' => array(),
            'achievements' => array(),
            'rankHistory' => array(),
            'loaded' => 0,
            'coaches' => array(),
            'ranksFromServer' => true,
            'storage' => 'mysql',
        );
    }

    legion_page_data_cache_write($coachSlugFilter, $payload);

    return $payload;

}



/**

 * Прогрев кэша таблиц и готового ответа (по запросу).

 */

function legion_warm_rating_cache() {

    legion_load_page_data(null);

    foreach (array_keys(legion_coaches_config()) as $slug) {

        legion_load_page_data($slug);

    }

    return true;

}


