<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$arComponentDescription = [
    'NAME' => 'Календарь ArtMax',
    'DESCRIPTION' => 'Компонент для отображения и управления событиями календаря по филиалам',
    'ICON' => '/images/icon.gif',
    'CACHE_PATH' => 'Y',
    'SORT' => 10,
    'PATH' => [
        'ID' => 'artmax',
        'NAME' => 'ArtMax',
        'CHILD' => [
            'ID' => 'calendar',
            'NAME' => 'Календарь',
        ],
    ],
]; 