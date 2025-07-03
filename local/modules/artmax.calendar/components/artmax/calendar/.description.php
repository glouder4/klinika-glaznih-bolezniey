<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

$arComponentDescription = [
    'NAME' => 'Artmax Calendar',
    'DESCRIPTION' => 'Компонент для отображения и управления событиями календаря',
    'ICON' => '/bitrix/images/artmax.calendar/calendar_icon.gif',
    'CACHE_PATH' => 'Y',
    'SORT' => 10,
    'PATH' => [
        'ID' => 'artmax',
        'NAME' => 'Artmax',
        'CHILD' => [
            'ID' => 'calendar',
            'NAME' => 'Календарь',
            'SORT' => 10,
        ],
    ],
]; 