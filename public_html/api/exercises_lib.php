<?php

/**
 * Список упражнений рейтинга (полные названия для вкладок и таблиц).
 */
function legion_exercises_config() {
    return array(
        array(
            'key' => 'push',
            'tab' => 'push',
            'label' => 'Отжимания',
            'tableTitle' => 'Отжимания',
            'csvMatch' => 'отжимания',
        ),
        array(
            'key' => 'pull',
            'tab' => 'pull',
            'label' => 'Подтягивания',
            'tableTitle' => 'Подтягивания',
            'csvMatch' => 'подтягивания',
        ),
        array(
            'key' => 'hang',
            'tab' => 'hang',
            'label' => 'Вис (сек)',
            'tableTitle' => 'Вис (сек)',
            'csvMatch' => 'вис',
        ),
        array(
            'key' => 'burpee',
            'tab' => 'burpee',
            'label' => 'Бёрпи за 1 мин',
            'tableTitle' => 'Бёрпи за 1 мин',
            'csvMatch' => 'бёрпи',
        ),
        array(
            'key' => 'crunch',
            'tab' => 'crunch',
            'label' => 'Скручивания',
            'tableTitle' => 'Скручивания',
            'csvMatch' => 'скручиван',
        ),
        array(
            'key' => 'jump',
            'tab' => 'jump',
            'label' => 'Прыжок в длину (см)',
            'tableTitle' => 'Прыжок в длину (см)',
            'csvMatch' => 'прыжок',
        ),
    );
}

function legion_exercise_tab_class($tabId, $activeTab) {
    return $tabId === $activeTab ? 'tab active' : 'tab';
}
