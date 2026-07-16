<?php

require_once __DIR__ . '/pilot_lib.php';
require_once __DIR__ . '/diagnostics_lib.php';

function legion_report_h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function legion_rank_leagues_config() {
    return array(
        'league3Names' => array('Новобранец', 'Легионер', 'Кроссфит боевик', 'Убийца железа', 'Гладиатор', 'Машин кроссфита', 'Спецназовец', 'Неистовый', 'Безумный спартанец', 'Несущий ярость', 'Зверская мощь', 'Боевой берсерк', 'Воинственный киборг', 'Князь силы', 'Владыка', 'Верховный лорд', 'Адреналиновый монстр', 'ТИТАН', 'Император кроссфита', 'БЕССМЕРТНЫЙ'),
        'league2Names' => array('Испытующий', 'Победитель арены', 'Стальной кулак', 'Крушитель преград', 'Рубака', 'Железный охотник', 'Штормовой воин', 'Центурион', 'Несокрушимый', 'Каратель слабости', 'Громовержец', 'Воитель хаоса', 'Властелин боя', 'Яростный викинг', 'Терминатор', 'Сокрушитель стен', 'Демиург силы', 'Полководец Победы', 'Неукротимый', 'Верховный Бессмертный'),
        'league1Names' => array('Избранный', 'Преторианец', 'Стальной легат', 'Воевода', 'Вершитель', 'Пламя Олимпа', 'Разящий Гром', 'Разрушитель миров', 'Магистр Силы', 'Повелитель битвы', 'Несокрушимый колосс', 'Князь ярости', 'Дикий воитель', 'Архонт', 'Легенда легиона', 'Владыка стихий', 'Господин войны', 'Сверхчеловек', 'Царь Воинов', 'Бессмертный Повелитель'),
        'league3Exercises' => array('Отжим. 20', 'Подтяг. 3', 'Выпады 30', 'Прыжок 130см', 'Присед 50', 'Ситапы 50', 'Стойка на руках 40с', 'Вис нижний 1мин', 'Челнок 3х10 9.2с', 'Планка 3мин', 'Бёрпи 30', 'Выпрыг. 40', 'Стульчик 3мин', 'Канат 1 раз', 'Трастеры 30', 'Зашагивания 40', 'НКП 5', 'Жим лёжа 1/2 веса', 'Вис согнутые 20с', 'Скакалка 100'),
        'league3Desc' => array('20 отжиманий от пола', '3 подтягивания', '30 выпадов', 'Прыжок в длину с места — 130 см', '50 приседаний', '50 подъёмов корпуса', 'Стойка на руках — 40 секунд', 'Вис на перекладине (нижний хват) — 1 минута', 'Челночный бег 3×10 м — не медленнее 9,2 сек', 'Планка — 3 минуты', '30 бёрпи', '40 выпрыгиваний', 'Удержание «стульчика» у стены — 3 минуты', 'Подъём по канату — 1 раз', '30 трастеров', '40 зашагиваний на тумбу', '5 подтягиваний «ноги к перекладине»', 'Жим лёжа — половина собственного веса', 'Вис согнутыми ногами — 20 секунд', '100 прыжков на скакалке'),
        'league2Exercises' => array('Отжим. 50', 'Подтяг. 10', 'Выпады 100', 'Жим лёжа =вес', 'Минотавр 1 гиря 15мин', 'Брусья 20', 'Присед 300', 'Уголок 20с', 'Стойка на руках 1мин', 'Вис 2мин', 'Челнок 10х10 27с', 'Отжим. кольца 5', 'Отжим. стойка 5', 'Махи гирей 125', 'Бёрпи 50', 'Выпрыг. 100', 'НКП 10', 'Турецкие 100', 'Канат уголок 3', 'Стойка кулаки 5мин'),
        'league2Desc' => array('50 отжиманий от пола', '10 подтягиваний', '100 выпадов', 'Жим лёжа — вес собственного тела', '«Минотавр» с одной гирей — 15 минут', '20 отжиманий на брусьях', '300 приседаний', 'Уголок (L-sit) — 20 секунд', 'Стойка на руках — 1 минута', 'Вис на перекладине — 2 минуты', 'Челночный бег 10×10 м — не медленнее 27 сек', '5 отжиманий на кольцах', '5 отжиманий в стойке на руках', '125 махов гирей', '50 бёрпи', '100 выпрыгиваний', '10 подтягиваний «ноги к перекладине»', '100 турецких подъёмов', 'Канат + уголок — 3 повтора', 'Стойка на кулаках — 5 минут'),
        'league1Exercises' => array('Подтяг. уголок 10', 'Отжим. кольца 10', 'Уголок 30с', 'Минотавр 2 гири 15мин', 'Отжим. 100', 'Бёрпи 100', 'Подтяг. строгие 15', 'Брусья 30', 'Жим лёжа 5раз', 'Турецкий 50%', 'Канаты без ног 5', 'НКП 15', 'Тест Купера 3мин', 'Выходы 3', 'Выпады 200', 'Тяги подбородок 100', 'Рывки гири 100', 'Трастеры 2 гири 50', 'Отжим. 1 рука 5+5', 'Стойка кулаки 10мин'),
        'league1Desc' => array('10 подтягиваний с уголком', '10 отжиманий на кольцах', 'Уголок — 30 секунд', '«Минотавр» с двумя гирями — 15 минут', '100 отжиманий', '100 бёрпи', '15 строгих подтягиваний', '30 отжиманий на брусьях', '5 повторений жима лёжа', 'Турецкий подъём — 50% от веса', 'Подъём по канату без ног — 5 раз', '15 подтягиваний «ноги к перекладине»', 'Тест Купера — 3 минуты', '3 выхода силой на перекладине', '200 выпадов', '100 тяг к подбородку', '100 рывков гири', '50 трастеров с двумя гирями', 'Отжимания на одной руке — 5+5', 'Стойка на кулаках — 10 минут'),
    );
}

function legion_dossier_exercise_labels() {
    $out = array();
    foreach (legion_diagnostics_exercises() as $ex) {
        $out[$ex['key']] = $ex['label'];
    }
    return $out;
}

function legion_rating_points_for_rank($rank) {
    $rank = (int) $rank;
    if ($rank < 1) {
        return 0;
    }
    if ($rank === 1) {
        return 100;
    }
    if ($rank === 2) {
        return 95;
    }
    if ($rank === 3) {
        return 90;
    }
    return max(0, 90 - ($rank - 3) * 2);
}

function legion_rating_calculate_all(array &$athletes) {
    $keys = legion_pilot_exercise_keys();
    foreach ($athletes as &$a) {
        if (!is_array($a)) {
            continue;
        }
        foreach ($keys as $ex) {
            $a[$ex . '_points'] = 0;
            $a[$ex . '_rank'] = 0;
        }
        $a['total'] = 0;
    }
    unset($a);

    foreach ($keys as $ex) {
        $nonZero = array();
        foreach ($athletes as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            $val = isset($row[$ex]) ? (float) $row[$ex] : 0;
            if ($val > 0) {
                $nonZero[] = array('index' => $i, 'val' => $val);
            }
        }
        usort($nonZero, function ($a, $b) {
            if ($a['val'] == $b['val']) {
                return 0;
            }
            return ($a['val'] < $b['val']) ? 1 : -1;
        });

        $currentRank = 1;
        $prev = null;
        foreach ($nonZero as $idx => $item) {
            if ($prev !== null && $item['val'] !== $prev) {
                $currentRank = $idx + 1;
            }
            $prev = $item['val'];
            $athletes[$item['index']][$ex . '_points'] = legion_rating_points_for_rank($currentRank);
            $athletes[$item['index']][$ex . '_rank'] = $currentRank;
        }
    }

    foreach ($athletes as &$a) {
        if (!is_array($a)) {
            continue;
        }
        $sum = 0;
        foreach ($keys as $ex) {
            $sum += isset($a[$ex . '_points']) ? (int) $a[$ex . '_points'] : 0;
        }
        $a['total'] = $sum;
    }
    unset($a);
}

function legion_rating_sort_by_total(array $athletes) {
    $tieKeys = array('pull', 'push', 'hang', 'burpee', 'crunch', 'jump');
    $filtered = array();
    foreach ($athletes as $row) {
        if (is_array($row) && !empty($row['total'])) {
            $filtered[] = $row;
        }
    }
    usort($filtered, function ($a, $b) use ($tieKeys) {
        if ($a['total'] != $b['total']) {
            return ($a['total'] < $b['total']) ? 1 : -1;
        }
        foreach ($tieKeys as $ex) {
            $av = isset($a[$ex]) ? (float) $a[$ex] : 0;
            $bv = isset($b[$ex]) ? (float) $b[$ex] : 0;
            if ($av != $bv) {
                return ($av < $bv) ? 1 : -1;
            }
        }
        return 0;
    });
    return $filtered;
}

function legion_dossier_resolve_overall_rank($coachSlug, $athleteName) {
    require_once __DIR__ . '/page_data_lib.php';
    $clubData = legion_build_club_page_data_from_mysql();
    $allAthletes = isset($clubData['athletes']) && is_array($clubData['athletes'])
        ? $clubData['athletes']
        : array();
    if (empty($allAthletes)) {
        return array('place' => null, 'total' => 0);
    }

    legion_rating_calculate_all($allAthletes);
    $sorted = legion_rating_sort_by_total($allAthletes);
    $norm = legion_normalize_person_name($athleteName);
    $coachSlug = legion_coach_normalize_slug($coachSlug);
    $place = null;
    foreach ($sorted as $i => $row) {
        $rowSlug = isset($row['coachSlug']) ? (string) $row['coachSlug'] : '';
        if (legion_normalize_person_name($row['name']) === $norm && $rowSlug === $coachSlug) {
            $place = $i + 1;
            break;
        }
    }
    return array(
        'place' => $place,
        'total' => count($sorted),
    );
}

function legion_dossier_rank_gap_metrics(array $competitorValues, $myValue) {
    $myValue = (float) $myValue;
    $active = array();
    foreach ($competitorValues as $value) {
        $value = (float) $value;
        if ($value > 0) {
            $active[] = $value;
        }
    }
    if ($myValue <= 0) {
        return array(
            'участников' => count($active),
            'нужно_улучшить' => null,
            'результат_впереди' => null,
            'запас_перед_следующим' => null,
            'результат_сзади' => null,
        );
    }

    $strictlyAbove = null;
    $strictlyBelow = null;
    foreach ($active as $value) {
        if ($value > $myValue && ($strictlyAbove === null || $value < $strictlyAbove)) {
            $strictlyAbove = $value;
        }
        if ($value < $myValue && ($strictlyBelow === null || $value > $strictlyBelow)) {
            $strictlyBelow = $value;
        }
    }

    $need = null;
    if ($strictlyAbove !== null) {
        $need = round($strictlyAbove - $myValue, 1);
        if ($need <= 0) {
            $need = 1;
        }
    }

    return array(
        'участников' => count($active),
        'нужно_улучшить' => $need,
        'результат_впереди' => $strictlyAbove,
        'запас_перед_следующим' => $strictlyBelow !== null ? round($myValue - $strictlyBelow, 1) : null,
        'результат_сзади' => $strictlyBelow,
    );
}

function legion_dossier_build_exercise_rank_context(array $athletes, array $athlete, array $keys, array $labels) {
    $out = array();
    foreach ($keys as $ex) {
        $values = array();
        foreach ($athletes as $row) {
            if (is_array($row) && isset($row[$ex])) {
                $values[] = (float) $row[$ex];
            }
        }
        $myValue = isset($athlete[$ex]) ? (float) $athlete[$ex] : 0;
        $rank = isset($athlete[$ex . '_rank']) ? (int) $athlete[$ex . '_rank'] : 0;
        $gaps = legion_dossier_rank_gap_metrics($values, $myValue);
        $item = array(
            'упражнение' => isset($labels[$ex]) ? $labels[$ex] : $ex,
            'место' => $rank > 0 ? $rank : null,
            'результат' => $myValue > 0 ? $myValue : null,
        );
        if ($myValue > 0) {
            $item['участников_с_результатом'] = $gaps['участников'];
            if ($gaps['нужно_улучшить'] !== null) {
                $item['нужно_улучшить_для_места_выше'] = $gaps['нужно_улучшить'];
                $item['результат_ближайшего_впереди'] = $gaps['результат_впереди'];
            } else {
                $item['лидер_в_упражнении'] = true;
            }
            if ($gaps['запас_перед_следующим'] !== null) {
                $item['запас_перед_преследователем'] = $gaps['запас_перед_следующим'];
                $item['результат_ближайшего_сзади'] = $gaps['результат_сзади'];
            }
        }
        $out[] = $item;
    }
    return $out;
}

function legion_dossier_club_rank(array $marks) {
    $cfg = legion_rank_leagues_config();
    $count3 = legion_pilot_count_league_marks($marks, 3);
    if ($count3 < 20) {
        return array(
            'league' => 3,
            'completed' => $count3,
            'total' => 20,
            'rankName' => $count3 > 0 ? $cfg['league3Names'][$count3 - 1] : null,
            'leagueLabel' => 'Бронза',
        );
    }
    $count2 = legion_pilot_count_league_marks($marks, 2);
    if ($count2 < 20) {
        return array(
            'league' => 2,
            'completed' => $count2,
            'total' => 20,
            'rankName' => $count2 > 0 ? $cfg['league2Names'][$count2 - 1] : null,
            'leagueLabel' => 'Серебро',
        );
    }
    $count1 = legion_pilot_count_league_marks($marks, 1);
    return array(
        'league' => 1,
        'completed' => $count1,
        'total' => 20,
        'rankName' => $count1 > 0 ? $cfg['league1Names'][$count1 - 1] : null,
        'leagueLabel' => 'Золото',
    );
}

function legion_dossier_next_norms(array $marks, $limit = 3) {
    $cfg = legion_rank_leagues_config();
    $clubRank = legion_dossier_club_rank($marks);
    $league = (int) $clubRank['league'];
    $offset = $league === 3 ? 0 : ($league === 2 ? 20 : 40);
    $key = 'league' . $league;
    $exercises = $cfg[$key . 'Exercises'];
    $descs = $cfg[$key . 'Desc'];
    $next = array();
    for ($i = 0; $i < 20; $i++) {
        if (empty($marks[$offset + $i])) {
            $next[] = array(
                'short' => $exercises[$i],
                'desc' => $descs[$i],
            );
            if (count($next) >= $limit) {
                break;
            }
        }
    }
    return $next;
}

function legion_dossier_completed_norms(array $marks) {
    $cfg = legion_rank_leagues_config();
    $leagues = array(
        array('league' => 3, 'label' => 'Бронза', 'offset' => 0),
        array('league' => 2, 'label' => 'Серебро', 'offset' => 20),
        array('league' => 1, 'label' => 'Золото', 'offset' => 40),
    );
    $completed = array();
    foreach ($leagues as $lg) {
        $key = 'league' . $lg['league'];
        $exercises = $cfg[$key . 'Exercises'];
        $descs = $cfg[$key . 'Desc'];
        for ($i = 0; $i < 20; $i++) {
            if (!empty($marks[$lg['offset'] + $i])) {
                $completed[] = array(
                    'лига' => $lg['label'],
                    'норматив' => $exercises[$i],
                    'описание' => $descs[$i],
                );
            }
        }
    }
    return $completed;
}

function legion_dossier_rank_summary(array $marks) {
    return array(
        'бронза' => legion_pilot_count_league_marks($marks, 3) . '/20',
        'серебро' => legion_pilot_count_league_marks($marks, 2) . '/20',
        'золото' => legion_pilot_count_league_marks($marks, 1) . '/20',
        'сдано_всего' => legion_pilot_count_league_marks($marks, 3)
            + legion_pilot_count_league_marks($marks, 2)
            + legion_pilot_count_league_marks($marks, 1),
    );
}

function legion_dossier_load_club_athletes_rated() {
    require_once __DIR__ . '/page_data_lib.php';
    $clubData = legion_build_club_page_data_from_mysql();
    $allAthletes = isset($clubData['athletes']) && is_array($clubData['athletes'])
        ? $clubData['athletes']
        : array();
    if (!empty($allAthletes)) {
        legion_rating_calculate_all($allAthletes);
    }
    return $allAthletes;
}

function legion_dossier_find_club_athlete(array $allAthletes, $coachSlug, $athleteName) {
    $norm = legion_normalize_person_name($athleteName);
    $coachSlug = legion_coach_normalize_slug($coachSlug);
    foreach ($allAthletes as $row) {
        if (!is_array($row) || empty($row['name'])) {
            continue;
        }
        $rowSlug = isset($row['coachSlug']) ? (string) $row['coachSlug'] : '';
        if (legion_normalize_person_name($row['name']) === $norm && $rowSlug === $coachSlug) {
            return $row;
        }
    }
    return null;
}

function legion_dossier_group_averages(array $athletes, array $keys) {
    $sums = array();
    $counts = array();
    foreach ($keys as $ex) {
        $sums[$ex] = 0.0;
        $counts[$ex] = 0;
    }
    foreach ($athletes as $row) {
        if (!is_array($row)) {
            continue;
        }
        foreach ($keys as $ex) {
            $val = isset($row[$ex]) ? (float) $row[$ex] : 0;
            if ($val > 0) {
                $sums[$ex] += $val;
                $counts[$ex]++;
            }
        }
    }
    $out = array();
    foreach ($keys as $ex) {
        $out[$ex] = $counts[$ex] > 0 ? round($sums[$ex] / $counts[$ex], 1) : 0;
    }
    return $out;
}

function legion_dossier_group_maxes(array $athletes, array $keys) {
    $maxes = array();
    foreach ($keys as $ex) {
        $maxes[$ex] = 0.0;
    }
    foreach ($athletes as $row) {
        if (!is_array($row)) {
            continue;
        }
        foreach ($keys as $ex) {
            $val = isset($row[$ex]) ? (float) $row[$ex] : 0;
            if ($val > $maxes[$ex]) {
                $maxes[$ex] = $val;
            }
        }
    }
    return $maxes;
}

function legion_dossier_is_elite_member($coachSlug, $athleteName) {
    require_once __DIR__ . '/club_storage_lib.php';
    $norm = legion_normalize_person_name($athleteName);
    $scopes = array(legion_coach_normalize_slug($coachSlug), 'global');
    foreach ($scopes as $scope) {
        $data = legion_club_load_elite($scope);
        $elite = isset($data['elite']) && is_array($data['elite']) ? $data['elite'] : array();
        foreach ($elite as $name) {
            if (legion_normalize_person_name($name) === $norm) {
                return true;
            }
        }
    }
    return false;
}

function legion_dossier_period_improvements_summary(array $history, $name, $days = 30) {
    $cutoff = time() - ((int) $days * 86400);
    $norm = legion_normalize_person_name($name);
    $improvements = 0;
    $exercises = array();
    foreach ($history as $entry) {
        if (!is_array($entry) || empty($entry['name'])) {
            continue;
        }
        if (legion_normalize_person_name($entry['name']) !== $norm) {
            continue;
        }
        $diff = isset($entry['diff']) ? (float) $entry['diff'] : 0;
        if ($diff <= 0) {
            continue;
        }
        $ts = legion_dossier_parse_history_ts(isset($entry['date']) ? $entry['date'] : '');
        if ($ts > 0 && $ts < $cutoff) {
            continue;
        }
        $improvements++;
        $ex = isset($entry['exercise']) ? (string) $entry['exercise'] : '';
        if ($ex !== '') {
            $exercises[$ex] = true;
        }
    }
    if ($improvements === 0) {
        return null;
    }
    $exCount = count($exercises);
    $impWord = legion_dossier_plural_ru($improvements, array('улучшение', 'улучшения', 'улучшений'));
    $exWord = legion_dossier_plural_ru($exCount, array('упражнении', 'упражнениях', 'упражнениях'));
    return array(
        'дней' => (int) $days,
        'улучшений' => $improvements,
        'упражнений' => $exCount,
        'текст' => 'За последние ' . (int) $days . ' дней: ' . $improvements . ' ' . $impWord
            . ' в ' . $exCount . ' ' . $exWord,
    );
}

function legion_dossier_overall_rank_gap($coachSlug, $athleteName) {
    $allAthletes = legion_dossier_load_club_athletes_rated();
    if (empty($allAthletes)) {
        return array('место' => null, 'текст' => null);
    }
    $sorted = legion_rating_sort_by_total($allAthletes);
    $norm = legion_normalize_person_name($athleteName);
    $coachSlug = legion_coach_normalize_slug($coachSlug);
    $place = null;
    $myTotal = 0;
    foreach ($sorted as $i => $row) {
        $rowSlug = isset($row['coachSlug']) ? (string) $row['coachSlug'] : '';
        if (legion_normalize_person_name($row['name']) === $norm && $rowSlug === $coachSlug) {
            $place = $i + 1;
            $myTotal = isset($row['total']) ? (int) $row['total'] : 0;
            break;
        }
    }
    if ($place === null || $place <= 1) {
        return array(
            'место' => $place,
            'текст' => $place === 1 ? '1-е общее место в клубе' : null,
            'лидер' => $place === 1,
        );
    }
    $above = $sorted[$place - 2];
    $aboveTotal = isset($above['total']) ? (int) $above['total'] : 0;
    $targetPlace = $place - 1;
    $gapPoints = max(1, $aboveTotal - $myTotal + 1);
    return array(
        'место' => $place,
        'целевое_место' => $targetPlace,
        'текст' => 'до ' . $targetPlace . '-го общего места',
        'разрыв_баллов' => $gapPoints,
        'баллы_впереди' => $aboveTotal,
    );
}

function legion_dossier_rank_progress(array $marks) {
    $clubRank = legion_dossier_club_rank($marks);
    $cfg = legion_rank_leagues_config();
    $league = (int) $clubRank['league'];
    $completed = (int) $clubRank['completed'];
    $total = (int) $clubRank['total'];
    $names = $cfg['league' . $league . 'Names'];
    $remainingInLeague = max(0, $total - $completed);
    $nextRankName = null;
    $normsToNext = 0;
    if ($completed < $total && isset($names[$completed])) {
        $nextRankName = $names[$completed];
        $normsToNext = 1;
    }
    $normWord = legion_dossier_plural_ru($normsToNext, array('норматив', 'норматива', 'нормативов'));
    $text = null;
    if ($nextRankName !== null && $normsToNext > 0) {
        $text = 'до звания «' . $nextRankName . '» осталось ' . $normsToNext . ' ' . $normWord;
    } elseif ($remainingInLeague > 0) {
        $normWord = legion_dossier_plural_ru($remainingInLeague, array('норматив', 'норматива', 'нормативов'));
        $text = 'до завершения лиги «' . $clubRank['leagueLabel'] . '» осталось ' . $remainingInLeague . ' ' . $normWord;
    }
    return array(
        'completed' => $completed,
        'total' => $total,
        'leagueLabel' => $clubRank['leagueLabel'],
        'percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
        'nextRankName' => $nextRankName,
        'normsToNext' => $normsToNext,
        'remainingInLeague' => $remainingInLeague,
        'text' => $text,
        'summary' => $completed . ' из ' . $total . ' нормативов',
    );
}

function legion_dossier_format_club_place($place) {
    $place = (int) $place;
    if ($place < 1) {
        return '—';
    }
    $mod10 = $place % 10;
    $mod100 = $place % 100;
    if ($mod10 === 1 && $mod100 !== 11) {
        return $place . '-е в клубе';
    }
    if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 10 || $mod100 >= 20)) {
        return $place . '-е в клубе';
    }
    return $place . '-е в клубе';
}

function legion_dossier_format_ai_html($text) {
    $text = trim((string) $text);
    if ($text === '') {
        return '';
    }
    $text = preg_replace("/\r\n?/", "\n", $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    $parts = preg_split('/\n\s*\n/', $text);
    $html = '';
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        $html .= '<p>' . nl2br(legion_report_h($part), false) . '</p>';
    }
    if ($html === '') {
        $html = '<p>' . nl2br(legion_report_h($text), false) . '</p>';
    }
    return $html;
}

function legion_dossier_history_day_key($dateStr) {
    if (preg_match('/(\d{1,2}\.\d{1,2}\.\d{4})/', (string) $dateStr, $m)) {
        return $m[1];
    }
    return '';
}

function legion_dossier_parse_history_ts($dateStr) {
    if (!preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})(?:,\s*(\d{1,2}):(\d{2}):(\d{2}))?/', (string) $dateStr, $m)) {
        return 0;
    }
    $h = isset($m[4]) ? (int) $m[4] : 0;
    $min = isset($m[5]) ? (int) $m[5] : 0;
    $s = isset($m[6]) ? (int) $m[6] : 0;
    return mktime($h, $min, $s, (int) $m[2], (int) $m[1], (int) $m[3]);
}

function legion_dossier_plural_ru($n, array $forms) {
    $abs = abs((int) round($n));
    $mod10 = $abs % 10;
    $mod100 = $abs % 100;
    if ($mod10 === 1 && $mod100 !== 11) {
        return $forms[0];
    }
    if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 10 || $mod100 >= 20)) {
        return $forms[1];
    }
    return $forms[2];
}

function legion_dossier_history_word($exerciseKey, $diff) {
    $forms = array(
        'push' => array('отжимание', 'отжимания', 'отжиманий'),
        'pull' => array('подтягивание', 'подтягивания', 'подтягиваний'),
        'hang' => array('секунду виса', 'секунды виса', 'секунд виса'),
        'burpee' => array('бёрпи', 'бёрпи', 'бёрпи'),
        'crunch' => array('скручивание', 'скручивания', 'скручиваний'),
        'jump' => array('см прыжка', 'см прыжка', 'см прыжка'),
    );
    $f = isset($forms[$exerciseKey]) ? $forms[$exerciseKey] : array('результат', 'результата', 'результатов');
    return legion_dossier_plural_ru($diff, $f);
}

function legion_dossier_format_progress_line(array $entry) {
    $date = legion_dossier_history_day_key(isset($entry['date']) ? $entry['date'] : '');
    $diff = isset($entry['diff']) ? (float) $entry['diff'] : 0;
    $exercise = isset($entry['exercise']) ? $entry['exercise'] : '';
    if ($diff > 0) {
        $word = legion_dossier_history_word($exercise, $diff);
        return $date . ' — +' . $diff . ' ' . $word;
    }
    $labels = legion_dossier_exercise_labels();
    $label = isset($labels[$exercise]) ? $labels[$exercise] : $exercise;
    return $date . ' — ' . $label . ': ' . (isset($entry['oldVal']) ? $entry['oldVal'] : '—')
        . ' → ' . (isset($entry['newVal']) ? $entry['newVal'] : '—');
}

function legion_dossier_aggregate_history_by_day(array $history, $name) {
    $norm = legion_normalize_person_name($name);
    $groups = array();
    foreach ($history as $entry) {
        if (!is_array($entry) || empty($entry['name'])) {
            continue;
        }
        if (legion_normalize_person_name($entry['name']) !== $norm) {
            continue;
        }
        $day = legion_dossier_history_day_key(isset($entry['date']) ? $entry['date'] : '');
        $exercise = isset($entry['exercise']) ? $entry['exercise'] : '';
        if ($day === '' || $exercise === '') {
            continue;
        }
        $key = $day . "\0" . $exercise;
        $ts = legion_dossier_parse_history_ts(isset($entry['date']) ? $entry['date'] : '');
        $diff = isset($entry['diff']) ? (float) $entry['diff'] : 0;
        if (!isset($groups[$key])) {
            $groups[$key] = array(
                'name' => $entry['name'],
                'exercise' => $exercise,
                'date' => $entry['date'],
                'diff' => 0,
                'sortTs' => $ts,
                'firstTs' => $ts,
                'oldVal' => isset($entry['oldVal']) ? $entry['oldVal'] : null,
                'newVal' => isset($entry['newVal']) ? $entry['newVal'] : null,
            );
        }
        $groups[$key]['diff'] += $diff;
        if ($ts < $groups[$key]['firstTs']) {
            $groups[$key]['firstTs'] = $ts;
            $groups[$key]['oldVal'] = isset($entry['oldVal']) ? $entry['oldVal'] : null;
        }
        if ($ts >= $groups[$key]['sortTs']) {
            $groups[$key]['sortTs'] = $ts;
            $groups[$key]['date'] = $entry['date'];
            $groups[$key]['newVal'] = isset($entry['newVal']) ? $entry['newVal'] : null;
        }
    }

    $out = array();
    foreach ($groups as $group) {
        if ($group['diff'] > 0) {
            $out[] = $group;
        }
    }
    usort($out, function ($a, $b) {
        return $b['sortTs'] - $a['sortTs'];
    });
    return $out;
}

function legion_dossier_build($coachSlug, $athleteName) {
    $coachSlug = legion_coach_normalize_slug($coachSlug);
    $data = legion_pilot_load_dataset($coachSlug);
    $meta = legion_coach_meta($coachSlug);
    $idx = legion_pilot_find_athlete_index($data['athletes'], $athleteName);
    if ($idx < 0) {
        return null;
    }

    $athletes = $data['athletes'];
    legion_rating_calculate_all($athletes);
    $sorted = legion_rating_sort_by_total($athletes);
    $groupPlace = null;
    foreach ($sorted as $i => $row) {
        if (legion_normalize_person_name($row['name']) === legion_normalize_person_name($athleteName)) {
            $groupPlace = $i + 1;
            break;
        }
    }

    $athlete = $athletes[$idx];
    $marks = legion_pilot_athlete_marks($athlete);
    $clubRank = legion_dossier_club_rank($marks);
    $labels = legion_dossier_exercise_labels();
    $keys = legion_pilot_exercise_keys();

    $clubAthletes = legion_dossier_load_club_athletes_rated();
    $clubAthlete = legion_dossier_find_club_athlete($clubAthletes, $coachSlug, $athleteName);
    $groupAverages = legion_dossier_group_averages($athletes, $keys);
    $groupMaxes = legion_dossier_group_maxes($athletes, $keys);

    $results = array();
    foreach ($keys as $ex) {
        $val = isset($athlete[$ex]) ? (float) $athlete[$ex] : 0;
        $clubExRank = 0;
        if ($clubAthlete !== null && isset($clubAthlete[$ex . '_rank'])) {
            $clubExRank = (int) $clubAthlete[$ex . '_rank'];
        }
        $results[] = array(
            'key' => $ex,
            'label' => isset($labels[$ex]) ? $labels[$ex] : $ex,
            'value' => $val,
            'rank' => isset($athlete[$ex . '_rank']) ? (int) $athlete[$ex . '_rank'] : 0,
            'clubRank' => $clubExRank,
            'groupAverage' => isset($groupAverages[$ex]) ? $groupAverages[$ex] : 0,
        );
    }

    $clubExerciseRankContext = legion_dossier_build_exercise_rank_context($clubAthletes, $clubAthlete ?: $athlete, $keys, $labels);
    $exerciseRankContext = legion_dossier_build_exercise_rank_context($athletes, $athlete, $keys, $labels);

    $history = isset($data['history']) && is_array($data['history']) ? $data['history'] : array();
    $historyCount = 0;
    $normName = legion_normalize_person_name($athleteName);
    foreach ($history as $entry) {
        if (is_array($entry) && !empty($entry['name'])
            && legion_normalize_person_name($entry['name']) === $normName) {
            $historyCount++;
        }
    }
    $progress = array_slice(legion_dossier_aggregate_history_by_day($history, $athleteName), 0, 12);
    $progressLines = array();
    foreach ($progress as $entry) {
        $progressLines[] = legion_dossier_format_progress_line($entry);
    }

    $birthdate = isset($athlete['birthdate']) ? $athlete['birthdate'] : null;
    $age = legion_pilot_compute_age($birthdate);
    $photo = legion_pilot_resolve_photo_url(
        $athlete['name'],
        isset($athlete['photo']) ? $athlete['photo'] : '',
        $coachSlug
    );

    $strong = array();
    $weak = array();
    foreach ($results as $row) {
        if ($row['rank'] === 1 && $row['value'] > 0) {
            $strong[] = $row['label'];
        }
        if ($row['rank'] >= 4 && $row['value'] > 0) {
            $weak[] = $row['label'];
        }
    }

    $overall = legion_dossier_resolve_overall_rank($coachSlug, $athleteName);
    $overallGap = legion_dossier_overall_rank_gap($coachSlug, $athleteName);
    $rankProgress = legion_dossier_rank_progress($marks);
    $isElite = legion_dossier_is_elite_member($coachSlug, $athleteName);
    $periodSummary = legion_dossier_period_improvements_summary($history, $athleteName, 30);

    $dossierCore = array(
        'meta' => array(
            'coachSlug' => $coachSlug,
            'coachName' => $meta['name'],
            'generatedAt' => date('d.m.Y, H:i:s'),
            'dataUpdatedAt' => isset($data['updatedAt']) ? $data['updatedAt'] : '',
            'groupSize' => count($data['athletes']),
            'clubRankedTotal' => $overall['total'],
        ),
        'athlete' => array(
            'name' => $athlete['name'],
            'age' => $age,
            'birthdate' => $birthdate,
            'photo' => $photo,
            'groupPlace' => $groupPlace,
            'overallPlace' => $overall['place'],
            'isElite' => $isElite,
            'overallGapText' => isset($overallGap['текст']) ? $overallGap['текст'] : null,
        ),
        'rank' => $clubRank,
        'rankProgress' => $rankProgress,
        'rankSummary' => legion_dossier_rank_summary($marks),
        'completedNorms' => legion_dossier_completed_norms($marks),
        'nextNorms' => legion_dossier_next_norms($marks, 3),
        'results' => $results,
        'groupAverages' => $groupAverages,
        'exerciseRankContext' => $exerciseRankContext,
        'clubExerciseRankContext' => $clubExerciseRankContext,
        'overallRankGap' => $overallGap,
        'progress' => $progressLines,
        'insights' => array(
            'strong' => $strong,
            'weak' => $weak,
        ),
        'stats' => array(
            'historyCount' => $historyCount,
        ),
    );
    if ($periodSummary !== null) {
        $dossierCore['periodSummary'] = $periodSummary;
    }

    require_once __DIR__ . '/athlete_dossier_charts_lib.php';
    $dossierCore['charts'] = legion_dossier_build_charts($dossierCore, $history, $athletes, $groupMaxes);

    return $dossierCore;
}

function legion_dossier_ai_prompt_text(array $dossier) {
    $payload = array(
        'спортсмен' => $dossier['athlete']['name'],
        'возраст' => $dossier['athlete']['age'],
        'группа' => $dossier['meta']['coachName'],
        'элита' => !empty($dossier['athlete']['isElite']),
        'место_в_группе' => $dossier['athlete']['groupPlace'],
        'общее_место' => $dossier['athlete']['overallPlace'],
        'всего_в_общем_рейтинге' => $dossier['meta']['clubRankedTotal'],
        'размер_группы' => $dossier['meta']['groupSize'],
        'ранг' => $dossier['rank'],
        'прогресс_ранга' => $dossier['rankProgress'],
        'прогресс_рангов' => $dossier['rankSummary'],
        'сданные_нормативы' => $dossier['completedNorms'],
        'ближайшие_нормативы' => $dossier['nextNorms'],
        'результаты' => $dossier['results'],
        'средние_по_группе' => isset($dossier['groupAverages']) ? $dossier['groupAverages'] : array(),
        'конкуренция_по_упражнениям_в_группе' => $dossier['exerciseRankContext'],
        'конкуренция_по_упражнениям_в_клубе' => isset($dossier['clubExerciseRankContext'])
            ? $dossier['clubExerciseRankContext']
            : array(),
        'дистанция_в_общем_рейтинге' => isset($dossier['overallRankGap']) ? $dossier['overallRankGap'] : null,
        'профиль_упражнений' => isset($dossier['charts']['radar']) ? $dossier['charts']['radar'] : null,
        'прогресс' => $dossier['progress'],
        'сильные_упражнения' => $dossier['insights']['strong'],
        'слабые_упражнения' => $dossier['insights']['weak'],
    );
    if (!empty($dossier['periodSummary'])) {
        $payload['итог_за_период'] = $dossier['periodSummary'];
    }
    return "Данные спортсмена для аналитического отчёта (JSON):\n"
        . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

function legion_dossier_render_html(array $dossier, $aiText = null, array $options = array()) {
    require_once __DIR__ . '/athlete_dossier_charts_lib.php';
    $cssVer = isset($options['cssVersion']) ? (int) $options['cssVersion'] : 1;
    $withAi = !empty($options['withAi']);
    $aiError = isset($options['aiError']) ? (string) $options['aiError'] : '';

    $a = $dossier['athlete'];
    $rank = $dossier['rank'];
    $rankTitle = $rank['rankName']
        ? $rank['rankName'] . ' (' . $rank['leagueLabel'] . ', ' . $rank['completed'] . '/20)'
        : ($rank['leagueLabel'] . ' — ' . $rank['completed'] . '/20');

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <title>Отчёт — <?php echo legion_report_h($a['name']); ?></title>
    <link rel="stylesheet" href="/css/athlete-report.css?v=<?php echo $cssVer; ?>">
</head>
<body class="athlete-report">
    <div class="report-toolbar no-print">
        <button type="button" class="report-btn" onclick="window.print()">Сохранить PDF / Печать</button>
        <?php if (!$withAi && empty($options['aiAuto'])) : ?>
        <span class="report-hint">ИИ отключён (<code>&amp;ai=0</code>). Добавьте <code>&amp;ai=1</code> для рекомендаций.</span>
        <?php endif; ?>
    </div>

    <article class="report-sheet">
        <header class="report-header">
            <div class="report-brand">
                <p class="report-brand-name">Легион Самара</p>
                <p class="report-brand-sub">Досье спортсмена · <?php echo legion_report_h($dossier['meta']['coachName']); ?></p>
            </div>
            <div class="report-meta">
                <p>Сформировано: <?php echo legion_report_h($dossier['meta']['generatedAt']); ?></p>
                <?php if (!empty($dossier['meta']['dataUpdatedAt'])) : ?>
                <p>Данные на: <?php echo legion_report_h($dossier['meta']['dataUpdatedAt']); ?></p>
                <?php endif; ?>
            </div>
        </header>

        <section class="report-hero-card">
            <div class="report-hero">
                <?php if (!empty($a['photo'])) : ?>
                <div class="report-hero-photo">
                    <img class="report-photo" src="<?php echo legion_report_h($a['photo']); ?>" alt="">
                </div>
                <?php endif; ?>
                <div class="report-hero-main">
                    <div class="report-hero-head">
                        <h1><?php echo legion_report_h($a['name']); ?>
                            <?php if (!empty($a['isElite'])) : ?>
                            <span class="report-elite-badge" title="Элита Легиона Силы · ТОП-25">Элита</span>
                            <?php endif; ?>
                        </h1>
                        <?php if ($a['age'] !== null) : ?>
                        <p class="report-age">Возраст: <?php echo (int) $a['age']; ?> лет</p>
                        <?php endif; ?>
                    </div>
                    <div class="report-stats-grid">
                        <div class="report-stat">
                            <span class="report-stat-label">Место в группе</span>
                            <span class="report-stat-value"><?php echo $a['groupPlace'] ? (int) $a['groupPlace'] : '—'; ?><span class="report-stat-sub"> из <?php echo (int) $dossier['meta']['groupSize']; ?></span></span>
                        </div>
                        <div class="report-stat">
                            <span class="report-stat-label">Общее место</span>
                            <span class="report-stat-value"><?php
                            if ($a['overallPlace']) {
                                echo (int) $a['overallPlace'];
                                if (!empty($dossier['meta']['clubRankedTotal'])) {
                                    echo '<span class="report-stat-sub"> из ' . (int) $dossier['meta']['clubRankedTotal'] . '</span>';
                                }
                            } else {
                                echo '—';
                            }
                            ?></span>
                            <?php if (!empty($a['overallGapText'])) : ?>
                            <span class="report-stat-hint"><?php echo legion_report_h($a['overallGapText']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="report-stat report-stat--rank">
                            <span class="report-stat-label">Ранг</span>
                            <span class="report-stat-value report-stat-value--text"><?php echo legion_report_h($rankTitle); ?></span>
                        </div>
                    </div>
                    <?php if (!empty($dossier['rankProgress'])) :
                        $rp = $dossier['rankProgress']; ?>
                    <div class="report-rank-progress">
                        <div class="report-rank-progress-head">
                            <span class="report-rank-progress-summary"><?php echo legion_report_h($rp['summary']); ?> <em>(<?php echo legion_report_h($rp['leagueLabel']); ?>)</em></span>
                            <?php if (!empty($rp['text'])) : ?>
                            <span class="report-rank-progress-note"><?php echo legion_report_h($rp['text']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="report-rank-progress-bar" role="progressbar" aria-valuenow="<?php echo (int) $rp['percent']; ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="report-rank-progress-fill" style="width:<?php echo (int) $rp['percent']; ?>%"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <?php if ($withAi) : ?>
        <section class="report-section report-ai report-ai--lead">
            <h2 class="report-section-title">Анализ для родителей</h2>
            <?php if ($aiError !== '') : ?>
            <p class="report-ai-error"><?php echo legion_report_h($aiError); ?></p>
            <?php elseif ($aiText !== null && $aiText !== '') : ?>
            <div class="report-ai-text"><?php echo legion_dossier_format_ai_html($aiText); ?></div>
            <?php else : ?>
            <p class="muted">ИИ-блок не сформирован.</p>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php
        if (!empty($dossier['charts'])) {
            echo legion_dossier_render_charts_html(
                $dossier['charts'],
                isset($dossier['progress']) ? $dossier['progress'] : array()
            );
        }
        ?>

        <section class="report-section">
            <h2 class="report-section-title">Результаты и места</h2>
            <div class="report-table-wrap">
            <table class="report-table">
                <thead>
                    <tr><th>Упражнение</th><th>Результат</th><th>В группе</th><th>В клубе</th></tr>
                </thead>
                <tbody>
                <?php foreach ($dossier['results'] as $row) : ?>
                    <tr>
                        <td><?php echo legion_report_h($row['label']); ?></td>
                        <td><?php echo $row['value'] > 0 ? legion_report_h($row['value']) : '—'; ?></td>
                        <td><?php echo $row['rank'] > 0 ? (int) $row['rank'] : '—'; ?></td>
                        <td><?php echo !empty($row['clubRank']) ? legion_report_h(legion_dossier_format_club_place($row['clubRank'])) : '—'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </section>

        <?php if (!empty($dossier['completedNorms'])) : ?>
        <section class="report-section report-completed-norms">
            <h2 class="report-section-title">Уже сдано</h2>
            <?php if (!empty($dossier['rankSummary'])) : ?>
            <p class="report-norms-summary muted">
                Бронза <?php echo legion_report_h($dossier['rankSummary']['бронза']); ?> ·
                Серебро <?php echo legion_report_h($dossier['rankSummary']['серебро']); ?> ·
                Золото <?php echo legion_report_h($dossier['rankSummary']['золото']); ?>
            </p>
            <?php endif; ?>
            <ul class="report-norms-checklist">
                <?php foreach ($dossier['completedNorms'] as $norm) :
                    $leagueClass = 'report-norm-league--bronze';
                    if ($norm['лига'] === 'Серебро') {
                        $leagueClass = 'report-norm-league--silver';
                    } elseif ($norm['лига'] === 'Золото') {
                        $leagueClass = 'report-norm-league--gold';
                    }
                    ?>
                <li class="report-norm-done <?php echo $leagueClass; ?>">
                    <span class="report-norm-check" aria-hidden="true">✓</span>
                    <span class="report-norm-league"><?php echo legion_report_h($norm['лига']); ?></span>
                    <strong><?php echo legion_report_h($norm['норматив']); ?></strong>
                    <span class="report-norm-desc"><?php echo legion_report_h($norm['описание']); ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <?php if (!empty($dossier['nextNorms'])) : ?>
        <section class="report-section">
            <h2 class="report-section-title">Ближайшие нормативы ранга</h2>
            <ol class="report-norms">
                <?php foreach ($dossier['nextNorms'] as $norm) : ?>
                <li><strong><?php echo legion_report_h($norm['short']); ?></strong> — <?php echo legion_report_h($norm['desc']); ?></li>
                <?php endforeach; ?>
            </ol>
        </section>
        <?php endif; ?>

        <footer class="report-footer">
            <p>Закрытый отчёт · доступ по ссылке с токеном · legion-samara.ru</p>
        </footer>
    </article>
</body>
</html>
    <?php
    return ob_get_clean();
}

function legion_dossier_render_picker_html($coachSlug, $token, array $athletes, array $options = array()) {
    $cssVer = isset($options['cssVersion']) ? (int) $options['cssVersion'] : 1;
    $reportBase = isset($options['reportBase']) ? (string) $options['reportBase'] : '/report.php';
    $omitCoachInUrl = !empty($options['omitCoachInUrl']);
    $meta = legion_coach_meta($coachSlug);
    $tokenQ = rawurlencode($token);
    $coachQ = rawurlencode($coachSlug);
    ob_start();
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Отчёты — <?php echo legion_report_h($meta['name']); ?></title>
    <link rel="stylesheet" href="/css/athlete-report.css?v=<?php echo $cssVer; ?>">
</head>
<body class="athlete-report">
    <article class="report-sheet report-picker">
        <header class="report-header">
            <div class="report-brand">
                <p class="report-brand-name">Легион Самара</p>
                <p class="report-brand-sub">Закрытые отчёты · <?php echo legion_report_h($meta['name']); ?></p>
            </div>
        </header>
        <section class="report-section">
            <h1>Выберите спортсмена</h1>
            <p class="muted">Ссылка с токеном не индексируется и не отображается на сайте. Не передавайте её публично.</p>
            <ul class="report-picker-list">
                <?php foreach ($athletes as $row) :
                    if (!is_array($row) || empty($row['name'])) {
                        continue;
                    }
                    $nameQ = rawurlencode($row['name']);
                    $base = $reportBase . '?token=' . $tokenQ;
                    if (!$omitCoachInUrl) {
                        $base .= '&coach=' . $coachQ;
                    }
                    $base .= '&name=' . $nameQ;
                    ?>
                <li>
                    <a href="<?php echo legion_report_h($base); ?>"><?php echo legion_report_h($row['name']); ?></a>
                    <span class="report-picker-links">
                        <a href="<?php echo legion_report_h($base); ?>">отчёт</a>
                        <a href="<?php echo legion_report_h($base . '&ai=0'); ?>">без ИИ</a>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </article>
</body>
</html>
    <?php
    return ob_get_clean();
}
