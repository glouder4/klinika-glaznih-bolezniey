<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$arComponentParameters = [
    'GROUPS' => [
        'SETTINGS' => [
            'NAME' => Loc::getMessage('ARTMAX_CALENDAR_PARAM_GROUP_SETTINGS'),
            'SORT' => 100,
        ],
        'CACHE_SETTINGS' => [
            'NAME' => Loc::getMessage('ARTMAX_CALENDAR_PARAM_GROUP_CACHE'),
            'SORT' => 200,
        ],
    ],
    'PARAMETERS' => [
        'BRANCH_ID' => [
            'PARENT' => 'SETTINGS',
            'NAME' => Loc::getMessage('ARTMAX_CALENDAR_PARAM_BRANCH_ID'),
            'TYPE' => 'STRING',
            'DEFAULT' => '1',
        ],
        'EVENTS_COUNT' => [
            'PARENT' => 'SETTINGS',
            'NAME' => Loc::getMessage('ARTMAX_CALENDAR_PARAM_EVENTS_COUNT'),
            'TYPE' => 'STRING',
            'DEFAULT' => '20',
        ],
        'SHOW_FORM' => [
            'PARENT' => 'SETTINGS',
            'NAME' => Loc::getMessage('ARTMAX_CALENDAR_PARAM_SHOW_FORM'),
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y',
        ],
        'CACHE_TYPE' => [
            'PARENT' => 'CACHE_SETTINGS',
            'NAME' => Loc::getMessage('ARTMAX_CALENDAR_PARAM_CACHE_TYPE'),
            'TYPE' => 'LIST',
            'VALUES' => [
                'A' => Loc::getMessage('ARTMAX_CALENDAR_PARAM_CACHE_TYPE_AUTO'),
                'Y' => Loc::getMessage('ARTMAX_CALENDAR_PARAM_CACHE_TYPE_YES'),
                'N' => Loc::getMessage('ARTMAX_CALENDAR_PARAM_CACHE_TYPE_NO'),
            ],
            'DEFAULT' => 'A',
        ],
        'CACHE_TIME' => [
            'PARENT' => 'CACHE_SETTINGS',
            'NAME' => Loc::getMessage('ARTMAX_CALENDAR_PARAM_CACHE_TIME'),
            'TYPE' => 'STRING',
            'DEFAULT' => '3600',
        ],
    ],
]; 