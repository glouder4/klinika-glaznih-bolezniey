<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

$arComponentParameters = [
    'GROUPS' => [
        'SETTINGS' => [
            'NAME' => 'Настройки',
            'SORT' => 100,
        ],
    ],
    'PARAMETERS' => [
        'CACHE_TYPE' => [
            'PARENT' => 'SETTINGS',
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
            'PARENT' => 'SETTINGS',
            'NAME' => 'Время кеширования (сек.)',
            'TYPE' => 'STRING',
            'DEFAULT' => '3600',
        ],
        'EVENTS_COUNT' => [
            'PARENT' => 'SETTINGS',
            'NAME' => 'Количество событий для отображения',
            'TYPE' => 'STRING',
            'DEFAULT' => '10',
        ],
        'SHOW_FORM' => [
            'PARENT' => 'SETTINGS',
            'NAME' => 'Показывать форму добавления события',
            'TYPE' => 'LIST',
            'VALUES' => [
                'Y' => 'Да',
                'N' => 'Нет',
            ],
            'DEFAULT' => 'Y',
        ],
    ],
]; 