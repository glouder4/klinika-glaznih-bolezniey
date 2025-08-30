<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$arComponentParameters = [
    'GROUPS' => [
        'SETTINGS' => [
            'NAME' => 'Настройки',
            'SORT' => 100,
        ],
        'CACHE_SETTINGS' => [
            'NAME' => 'Настройки кеширования',
            'SORT' => 200,
        ],
    ],
    'PARAMETERS' => [
        'BRANCH_ID' => [
            'PARENT' => 'SETTINGS',
            'NAME' => 'ID филиала',
            'TYPE' => 'STRING',
            'DEFAULT' => '1',
        ],
        'EVENTS_COUNT' => [
            'PARENT' => 'SETTINGS',
            'NAME' => 'Количество событий для отображения',
            'TYPE' => 'STRING',
            'DEFAULT' => '20',
        ],
        'SHOW_FORM' => [
            'PARENT' => 'SETTINGS',
            'NAME' => 'Показывать форму добавления события',
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y',
        ],
        'CACHE_TYPE' => [
            'PARENT' => 'CACHE_SETTINGS',
            'NAME' => 'Тип кеширования',
            'TYPE' => 'LIST',
            'VALUES' => [
                'A' => 'Авто + Управляемое',
                'Y' => 'Кешировать',
                'N' => 'Не кешировать',
            ],
            'DEFAULT' => 'A',
        ],
        'CACHE_TIME' => [
            'PARENT' => 'CACHE_SETTINGS',
            'NAME' => 'Время кеширования (сек.)',
            'TYPE' => 'STRING',
            'DEFAULT' => '3600',
        ],
    ],
]; 