<?php
/**
 * Миграция: добавление прав calendar.edit_title_own и calendar.edit_title_all
 * Редактирование названия записи (своих / всех)
 *
 * Использование: php migrate_add_edit_title_permissions.php
 * или через браузер: /local/modules/artmax.calendar/install/migrations/migrate_add_edit_title_permissions.php
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

if (!defined('BX_CRONTAB') && (isset($GLOBALS['USER']) ? !$GLOBALS['USER']->IsAuthorized() : true)) {
    die('Необходима авторизация');
}

if (!CModule::IncludeModule('artmax.calendar')) {
    die('Модуль artmax.calendar не установлен');
}

$connection = \Bitrix\Main\Application::getConnection();
$sqlHelper = $connection->getSqlHelper();

$permissions = [
    [
        'CODE' => 'calendar.edit_title_own',
        'NAME' => 'Редактирование названия записи (своих)',
        'DESCRIPTION' => 'Право на редактирование названия только своих записей'
    ],
    [
        'CODE' => 'calendar.edit_title_all',
        'NAME' => 'Редактирование названия записи (всех)',
        'DESCRIPTION' => 'Право на редактирование названия у любых записей'
    ]
];

foreach ($permissions as $permission) {
    $sqlCheck = "SELECT ID FROM artmax_calendar_permissions WHERE CODE = '" . $sqlHelper->forSql($permission['CODE']) . "'";
    $result = $connection->query($sqlCheck);
    if ($result->getSelectedRowsCount() == 0) {
        $sqlInsert = "INSERT INTO artmax_calendar_permissions (CODE, NAME, DESCRIPTION) VALUES (
            '" . $sqlHelper->forSql($permission['CODE']) . "',
            '" . $sqlHelper->forSql($permission['NAME']) . "',
            '" . $sqlHelper->forSql($permission['DESCRIPTION']) . "'
        )";
        $connection->query($sqlInsert);
        echo "Добавлено право: {$permission['CODE']} - {$permission['NAME']}\n";
    } else {
        echo "Право уже существует: {$permission['CODE']}\n";
    }
}

echo "Миграция завершена.\n";
