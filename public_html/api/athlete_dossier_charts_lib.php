<?php

require_once __DIR__ . '/athlete_dossier_lib.php';

function legion_dossier_build_charts(array $dossier, array $history, array $groupAthletes = array(), array $groupMaxes = array()) {
    $labels = legion_dossier_exercise_labels();
    $keys = legion_pilot_exercise_keys();
    $name = $dossier['athlete']['name'];
    $athleteId = !empty($dossier['athlete']['id']) ? (int) $dossier['athlete']['id'] : 0;

    $groupCompare = array();
    $compareMax = 1.0;
    foreach ($dossier['results'] as $row) {
        $avg = isset($row['groupAverage']) ? (float) $row['groupAverage'] : 0;
        $val = (float) $row['value'];
        $compareMax = max($compareMax, $val, $avg);
        $groupCompare[] = array(
            'label' => $row['label'],
            'athlete' => $val,
            'average' => $avg,
        );
    }

    $radar = legion_dossier_build_radar_data($dossier['results'], $groupMaxes);

    $timelines = legion_dossier_build_exercise_timelines($history, $name, $keys, $labels, $dossier['results'], $athleteId);

    return array(
        'groupCompare' => $groupCompare,
        'compareMax' => $compareMax,
        'radar' => $radar,
        'timelines' => $timelines,
    );
}

function legion_dossier_build_radar_data(array $results, array $groupMaxes) {
    $axes = array();
    foreach ($results as $row) {
        $key = $row['key'];
        $val = (float) $row['value'];
        $max = isset($groupMaxes[$key]) ? (float) $groupMaxes[$key] : 0;
        $normalized = 0;
        if ($val > 0 && $max > 0) {
            $normalized = (int) round(min(100, ($val / $max) * 100));
        }
        $axes[] = array(
            'key' => $key,
            'label' => $row['label'],
            'value' => $val,
            'normalized' => $normalized,
            'groupMax' => $max,
        );
    }
    return array(
        'axes' => $axes,
        'mode' => 'group_max',
    );
}

function legion_dossier_build_exercise_timelines(array $history, $name, array $keys, array $labels, array $results, $athleteId = 0) {
    $historyByExercise = array();
    foreach ($keys as $key) {
        $historyByExercise[$key] = array();
    }

    foreach ($history as $entry) {
        if (!is_array($entry) || empty($entry['exercise'])) {
            continue;
        }
        if (!legion_dossier_history_entry_matches($entry, $name, $athleteId)) {
            continue;
        }
        $ex = $entry['exercise'];
        if (!isset($historyByExercise[$ex])) {
            continue;
        }
        $newVal = isset($entry['newVal']) ? (float) $entry['newVal'] : 0;
        if ($newVal <= 0) {
            continue;
        }
        $historyByExercise[$ex][] = $entry;
    }

    $currentByKey = array();
    foreach ($results as $row) {
        $currentByKey[$row['key']] = (float) $row['value'];
    }

    $out = array();
    foreach ($keys as $key) {
        $entries = $historyByExercise[$key];
        if (empty($entries)) {
            continue;
        }

        usort($entries, function ($a, $b) {
            $ta = legion_dossier_parse_history_ts(isset($a['date']) ? $a['date'] : '');
            $tb = legion_dossier_parse_history_ts(isset($b['date']) ? $b['date'] : '');
            return $ta - $tb;
        });

        $points = array();
        $first = $entries[0];
        $oldVal = isset($first['oldVal']) ? (float) $first['oldVal'] : 0;
        if ($oldVal > 0) {
            $points[] = array(
                'ts' => legion_dossier_parse_history_ts(isset($first['date']) ? $first['date'] : '') - 1,
                'day' => legion_dossier_history_day_key(isset($first['date']) ? $first['date'] : ''),
                'value' => $oldVal,
            );
        }
        foreach ($entries as $entry) {
            $newVal = isset($entry['newVal']) ? (float) $entry['newVal'] : 0;
            if ($newVal <= 0) {
                continue;
            }
            $points[] = array(
                'ts' => legion_dossier_parse_history_ts(isset($entry['date']) ? $entry['date'] : ''),
                'day' => legion_dossier_history_day_key(isset($entry['date']) ? $entry['date'] : ''),
                'value' => $newVal,
            );
        }

        $currentVal = isset($currentByKey[$key]) ? $currentByKey[$key] : 0;
        if ($currentVal > 0) {
            $lastVal = $points[count($points) - 1]['value'];
            if ($currentVal !== $lastVal) {
                $points[] = array(
                    'ts' => time(),
                    'day' => 'сейчас',
                    'value' => $currentVal,
                );
            }
        }

        if (count($points) < 2) {
            continue;
        }
        $values = array();
        foreach ($points as $pt) {
            $values[] = (float) $pt['value'];
        }
        if (min($values) === max($values)) {
            continue;
        }

        usort($points, function ($a, $b) {
            return $a['ts'] - $b['ts'];
        });
        $out[] = array(
            'key' => $key,
            'label' => isset($labels[$key]) ? $labels[$key] : $key,
            'points' => $points,
        );
    }
    return $out;
}

function legion_dossier_svg_group_compare_chart(array $items, $maxValue, $width = 520, $rowHeight = 30) {
    if (empty($items)) {
        return '';
    }
    if ($maxValue <= 0) {
        $maxValue = 1;
    }
    $padL = 148;
    $padR = 12;
    $padT = 8;
    $barH = 10;
    $gap = 4;
    $chartW = $width - $padL - $padR;
    $height = $padT + count($items) * $rowHeight + 8;
    $svg = '<svg class="report-chart-svg" viewBox="0 0 ' . (int) $width . ' ' . (int) $height . '" role="img" aria-hidden="true">';
    foreach ($items as $i => $item) {
        $y = $padT + $i * $rowHeight;
        $aVal = (float) $item['athlete'];
        $gVal = (float) $item['average'];
        $aW = $aVal > 0 ? max(2, ($aVal / $maxValue) * $chartW) : 0;
        $gW = $gVal > 0 ? max(2, ($gVal / $maxValue) * $chartW) : 0;
        $svg .= '<text x="0" y="' . ($y + 18) . '" class="report-chart-label">' . legion_report_h($item['label']) . '</text>';
        $svg .= '<rect x="' . $padL . '" y="' . ($y + 2) . '" width="' . round($aW, 1) . '" height="' . $barH . '" rx="3" fill="#c8102e"></rect>';
        $svg .= '<rect x="' . $padL . '" y="' . ($y + 2 + $barH + $gap) . '" width="' . round($gW, 1) . '" height="' . $barH . '" rx="3" fill="#9ca3af"></rect>';
    }
    $svg .= '<text x="' . $padL . '" y="' . ($height - 2) . '" class="report-chart-legend">■ спортсмен  ■ среднее по группе</text>';
    $svg .= '</svg>';
    return $svg;
}

function legion_dossier_svg_radar_chart(array $radar, $size = 300) {
    if (empty($radar['axes']) || !is_array($radar['axes'])) {
        return '';
    }
    $axes = $radar['axes'];
    $count = count($axes);
    if ($count < 3) {
        return '';
    }

    $cx = $size / 2;
    $cy = $size / 2;
    $radius = $size * 0.34;
    $labelR = $radius + 28;
    $angles = array();
    for ($i = 0; $i < $count; $i++) {
        $angles[$i] = (-M_PI / 2) + (2 * M_PI * $i / $count);
    }

    $svg = '<svg class="report-chart-svg report-chart-svg--radar" viewBox="0 0 ' . (int) $size . ' ' . (int) $size . '" role="img" aria-hidden="true">';

    for ($ring = 1; $ring <= 4; $ring++) {
        $r = $radius * ($ring / 4);
        $points = array();
        for ($i = 0; $i < $count; $i++) {
            $points[] = round($cx + cos($angles[$i]) * $r, 1) . ',' . round($cy + sin($angles[$i]) * $r, 1);
        }
        $svg .= '<polygon points="' . implode(' ', $points) . '" fill="none" stroke="#e5e7eb" stroke-width="1"></polygon>';
    }

    for ($i = 0; $i < $count; $i++) {
        $x2 = $cx + cos($angles[$i]) * $radius;
        $y2 = $cy + sin($angles[$i]) * $radius;
        $svg .= '<line x1="' . round($cx, 1) . '" y1="' . round($cy, 1) . '" x2="' . round($x2, 1) . '" y2="' . round($y2, 1) . '" stroke="#e5e7eb" stroke-width="1"></line>';
    }

    $dataPoints = array();
    foreach ($axes as $i => $axis) {
        $norm = isset($axis['normalized']) ? (float) $axis['normalized'] : 0;
        $r = $radius * ($norm / 100);
        $dataPoints[] = round($cx + cos($angles[$i]) * $r, 1) . ',' . round($cy + sin($angles[$i]) * $r, 1);
    }
    $svg .= '<polygon points="' . implode(' ', $dataPoints) . '" fill="rgba(200,16,46,0.2)" stroke="#c8102e" stroke-width="2"></polygon>';

    foreach ($axes as $i => $axis) {
        $lx = $cx + cos($angles[$i]) * $labelR;
        $ly = $cy + sin($angles[$i]) * $labelR;
        $anchor = 'middle';
        if (cos($angles[$i]) > 0.25) {
            $anchor = 'start';
        } elseif (cos($angles[$i]) < -0.25) {
            $anchor = 'end';
        }
        $short = $axis['label'];
        if (mb_strlen($short) > 14) {
            $short = mb_substr($short, 0, 12) . '…';
        }
        $svg .= '<text x="' . round($lx, 1) . '" y="' . round($ly, 1) . '" class="report-chart-label report-chart-label--radar" text-anchor="' . $anchor . '">' . legion_report_h($short) . '</text>';
    }

    $svg .= '</svg>';
    return $svg;
}

function legion_dossier_svg_line_chart(array $series, $width = 520, $height = 140) {
    if (empty($series)) {
        return '';
    }
    $pad = array('l' => 28, 'r' => 8, 't' => 18, 'b' => 24);
    $plotW = $width - $pad['l'] - $pad['r'];
    $plotH = $height - $pad['t'] - $pad['b'];

    $values = array();
    foreach ($series as $pt) {
        $values[] = (float) $pt['value'];
    }
    $minV = min($values);
    $maxV = max($values);
    if ($maxV <= $minV) {
        $maxV = $minV + 1;
    }

    $count = count($series);
    $coords = array();
    foreach ($series as $i => $pt) {
        $x = $pad['l'] + ($count > 1 ? ($i / ($count - 1)) * $plotW : $plotW / 2);
        $y = $pad['t'] + (1 - (((float) $pt['value'] - $minV) / ($maxV - $minV))) * $plotH;
        $coords[] = round($x, 1) . ',' . round($y, 1);
    }

    $svg = '<svg class="report-chart-svg report-chart-svg--line" viewBox="0 0 ' . (int) $width . ' ' . (int) $height . '" role="img" aria-hidden="true">';
    $svg .= '<polyline points="' . implode(' ', $coords) . '" fill="none" stroke="#c8102e" stroke-width="2"></polyline>';
    foreach ($series as $i => $pt) {
        $parts = explode(',', $coords[$i]);
        $svg .= '<circle cx="' . $parts[0] . '" cy="' . $parts[1] . '" r="3" fill="#c8102e"></circle>';
    }
    $firstDay = $series[0]['day'];
    $lastDay = $series[$count - 1]['day'];
    $svg .= '<text x="' . $pad['l'] . '" y="' . ($height - 4) . '" class="report-chart-axis">' . legion_report_h($firstDay) . '</text>';
    $svg .= '<text x="' . ($width - $pad['r']) . '" y="' . ($height - 4) . '" class="report-chart-axis" text-anchor="end">' . legion_report_h($lastDay) . '</text>';
    $svg .= '</svg>';
    return $svg;
}

function legion_dossier_render_charts_html(array $charts, array $progress = array()) {
    if (empty($charts)) {
        return '';
    }

    $compareMax = isset($charts['compareMax']) ? (float) $charts['compareMax'] : 1;

    ob_start();
    ?>
    <section class="report-section report-charts">
        <h2 class="report-section-title">Графики</h2>
        <div class="report-charts-grid">
            <?php if (!empty($charts['groupCompare'])) : ?>
            <div class="report-chart-card report-chart-card--wide">
                <h3>Сравнение со средним по группе</h3>
                <?php echo legion_dossier_svg_group_compare_chart($charts['groupCompare'], $compareMax); ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($charts['radar']['axes'])) : ?>
            <div class="report-chart-card report-chart-card--radar">
                <h3>Профиль: сила и выносливость</h3>
                <p class="muted report-charts-note">Нормализация к лучшему результату в группе (0–100).</p>
                <?php echo legion_dossier_svg_radar_chart($charts['radar']); ?>
            </div>
            <?php endif; ?>
            <?php foreach ($charts['timelines'] as $timeline) : ?>
            <div class="report-chart-card report-chart-card--timeline">
                <h3>Динамика: <?php echo legion_report_h($timeline['label']); ?></h3>
                <?php echo legion_dossier_svg_line_chart($timeline['points']); ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (empty($charts['timelines'])) : ?>
        <p class="muted report-charts-note">Графики динамики по упражнениям появятся после улучшений в истории.</p>
        <?php endif; ?>
        <?php if (!empty($progress)) : ?>
        <div class="report-chart-card report-chart-card--wide report-chart-card--progress">
            <h3>Недавний прогресс</h3>
            <ul class="report-list">
                <?php foreach ($progress as $line) : ?>
                <li><?php echo legion_report_h($line); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </section>
    <?php
    return ob_get_clean();
}

function legion_dossier_data_sources_meta(array $dossier) {
    $historyCount = isset($dossier['stats']['historyCount']) ? (int) $dossier['stats']['historyCount'] : 0;
    $progressCount = isset($dossier['progress']) ? count($dossier['progress']) : 0;

    return array(
        array(
            'title' => 'Профиль спортсмена',
            'detail' => 'ФИО, возраст, фото — MySQL (группа «' . $dossier['meta']['coachName'] . '»)',
        ),
        array(
            'title' => 'Результаты упражнений',
            'detail' => 'Отжимания, подтягивания, вис, бёрпи, скручивания, прыжок — текущие значения из базы группы',
        ),
        array(
            'title' => 'Место в группе',
            'detail' => 'Расчёт на сервере по результатам упражнений в группе',
        ),
        array(
            'title' => 'Ранги и нормативы',
            'detail' => '60 отметок ранга (бронза / серебро / золото), ближайшие несданные нормативы',
        ),
        array(
            'title' => 'История улучшений',
            'detail' => $historyCount . ' записей в базе, в отчёте — ' . $progressCount . ' последних улучшений',
        ),
        array(
            'title' => 'ИИ-рекомендации (YandexGPT)',
            'detail' => 'Генерируются при открытии ссылки из сводки выше; требуют проверки тренером',
        ),
    );
}

function legion_dossier_render_data_sources_html(array $dossier) {
    $sources = legion_dossier_data_sources_meta($dossier);
    ob_start();
    ?>
    <section class="report-section report-data-sources">
        <h2>Какие данные используются</h2>
        <p class="muted">Отчёт собирается при каждом открытии ссылки из актуальной базы MySQL группы.</p>
        <ul class="report-sources-list">
            <?php foreach ($sources as $src) : ?>
            <li>
                <strong><?php echo legion_report_h($src['title']); ?>:</strong>
                <?php echo legion_report_h($src['detail']); ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <p class="report-sources-meta muted">
            Сформировано: <?php echo legion_report_h($dossier['meta']['generatedAt']); ?>
            <?php if (!empty($dossier['meta']['dataUpdatedAt'])) : ?>
            · данные обновлены: <?php echo legion_report_h($dossier['meta']['dataUpdatedAt']); ?>
            <?php endif; ?>
        </p>
    </section>
    <?php
    return ob_get_clean();
}
