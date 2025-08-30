<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$arComponentDescription = [
    'NAME' => Loc::getMessage('ARTMAX_CALENDAR_COMPONENT_NAME'),
    'DESCRIPTION' => Loc::getMessage('ARTMAX_CALENDAR_COMPONENT_DESCRIPTION'),
    'ICON' => '/images/icon.gif',
    'CACHE_PATH' => 'Y',
    'SORT' => 10,
    'PATH' => [
        'ID' => 'artmax',
        'NAME' => 'ArtMax',
        'CHILD' => [
            'ID' => 'calendar',
            'NAME' => Loc::getMessage('ARTMAX_CALENDAR_COMPONENT_PATH_NAME'),
        ],
    ],
]; 