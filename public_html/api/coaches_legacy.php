<?php

/**
 * Исходный список тренеров (до MySQL). Используется один раз для заполнения legion_coaches.
 */
function legion_coaches_legacy_config() {
    return array(
        'yakutin' => array(
            'name' => 'Якутин Иван',
            'tagline' => 'Группа тренера',
            'storage' => 'mysql',
            'csvUrl' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSf6qfw-eRUUYB0zEnlyCh4y0gq731_RdLUT7AJ54ApwaV3N7_4KbFIbOVLlx5u1mpL0NY7M4JbsDjj/pub?gid=999564821&single=true&output=csv',
            'ranksCsvUrl' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSf6qfw-eRUUYB0zEnlyCh4y0gq731_RdLUT7AJ54ApwaV3N7_4KbFIbOVLlx5u1mpL0NY7M4JbsDjj/pub?gid=2106158359&single=true&output=csv',
        ),
        'nikonov' => array(
            'name' => 'Никонов Никита',
            'tagline' => 'Группа тренера',
            'storage' => 'mysql',
            'csvUrl' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSf6qfw-eRUUYB0zEnlyCh4y0gq731_RdLUT7AJ54ApwaV3N7_4KbFIbOVLlx5u1mpL0NY7M4JbsDjj/pub?gid=2018595165&single=true&output=csv',
            'ranksCsvUrl' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSf6qfw-eRUUYB0zEnlyCh4y0gq731_RdLUT7AJ54ApwaV3N7_4KbFIbOVLlx5u1mpL0NY7M4JbsDjj/pub?gid=1177372140&single=true&output=csv',
        ),
        'kasatkin' => array(
            'name' => 'Касаткин Алексей',
            'tagline' => 'Группа тренера',
            'storage' => 'mysql',
            'csvUrl' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSf6qfw-eRUUYB0zEnlyCh4y0gq731_RdLUT7AJ54ApwaV3N7_4KbFIbOVLlx5u1mpL0NY7M4JbsDjj/pub?gid=0&single=true&output=csv',
            'ranksCsvUrl' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSf6qfw-eRUUYB0zEnlyCh4y0gq731_RdLUT7AJ54ApwaV3N7_4KbFIbOVLlx5u1mpL0NY7M4JbsDjj/pub?gid=2130784782&single=true&output=csv',
        ),
        'parkhaev' => array(
            'name' => 'Пархаев Алексей',
            'tagline' => 'Группа тренера',
            'storage' => 'mysql',
            'csvUrl' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSf6qfw-eRUUYB0zEnlyCh4y0gq731_RdLUT7AJ54ApwaV3N7_4KbFIbOVLlx5u1mpL0NY7M4JbsDjj/pub?gid=1103251903&single=true&output=csv',
            'ranksCsvUrl' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSf6qfw-eRUUYB0zEnlyCh4y0gq731_RdLUT7AJ54ApwaV3N7_4KbFIbOVLlx5u1mpL0NY7M4JbsDjj/pub?gid=1582437394&single=true&output=csv',
        ),
        'makarenkov' => array(
            'name' => 'Макаренков Артём',
            'tagline' => 'Группа тренера',
            'storage' => 'mysql',
            'csvUrl' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSf6qfw-eRUUYB0zEnlyCh4y0gq731_RdLUT7AJ54ApwaV3N7_4KbFIbOVLlx5u1mpL0NY7M4JbsDjj/pub?gid=573257096&single=true&output=csv',
            'ranksCsvUrl' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSf6qfw-eRUUYB0zEnlyCh4y0gq731_RdLUT7AJ54ApwaV3N7_4KbFIbOVLlx5u1mpL0NY7M4JbsDjj/pub?gid=287377539&single=true&output=csv',
        ),
        'kostin' => array(
            'name' => 'Костин Алексей',
            'tagline' => 'Группа тренера',
            'storage' => 'mysql',
            'csvUrl' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSf6qfw-eRUUYB0zEnlyCh4y0gq731_RdLUT7AJ54ApwaV3N7_4KbFIbOVLlx5u1mpL0NY7M4JbsDjj/pub?gid=1438794797&single=true&output=csv',
            'ranksCsvUrl' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vSf6qfw-eRUUYB0zEnlyCh4y0gq731_RdLUT7AJ54ApwaV3N7_4KbFIbOVLlx5u1mpL0NY7M4JbsDjj/pub?gid=1572172467&single=true&output=csv',
        ),
    );
}
