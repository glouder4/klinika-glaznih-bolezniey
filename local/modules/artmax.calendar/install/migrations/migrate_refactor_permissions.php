<?php
/**
 * PHP миграция для рефакторинга системы прав доступа
 * Дата: 2026-01-18
 * Выполнить через: php migrate_refactor_permissions.php
 */

// Инициализация Bitrix
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

// Подключаем модуль
if (!\CModule::IncludeModule('artmax.calendar')) {
    echo "Ошибка: не удалось подключить модуль artmax.calendar\n";
    exit(1);
}

use Bitrix\Main\Application;

echo "Начало миграции прав доступа...\n";

$connection = Application::getConnection();
$sqlHelper = $connection->getSqlHelper();

try {
    // ============================================
    // 1. Удаление устаревших прав
    // ============================================
    echo "Удаление устаревших прав...\n";
    
    $obsoletePermissions = ['calendar.view_others', 'calendar.edit', 'calendar.edit_title', 'calendar.edit_others_notes', 'calendar.delete'];
    
    foreach ($obsoletePermissions as $code) {
        // Удаляем связи с группами/пользователями
        $sqlDeleteRights = "DELETE FROM artmax_calendar_access_rights 
                            WHERE PERMISSION_ID IN (SELECT ID FROM artmax_calendar_permissions WHERE CODE = '" . $sqlHelper->forSql($code) . "')";
        $connection->query($sqlDeleteRights);
        
        // Удаляем само право
        $sqlDeletePerm = "DELETE FROM artmax_calendar_permissions WHERE CODE = '" . $sqlHelper->forSql($code) . "'";
        $connection->query($sqlDeletePerm);
        
        echo "  - Удалено право: $code\n";
    }
    
    // ============================================
    // 2. Добавление новых прав
    // ============================================
    echo "Добавление новых прав...\n";
    
    $newPermissions = [
        'calendar.view' => ['Просмотр записей', 'Право на просмотр своих записей в календаре'],
        'calendar.view_all' => ['Просмотр всех записей', 'Право на просмотр записей всех врачей'],
        'calendar.create' => ['Создание записи', 'Право на создание новых записей в календаре'],
        'calendar.edit_own' => ['Редактирование своих записей', 'Право на редактирование только своих записей'],
        'calendar.edit_all' => ['Редактирование всех записей', 'Право на редактирование записей всех врачей'],
        'calendar.move' => ['Перемещение записи', 'Право на перемещение записей в календаре'],
        'calendar.confirm' => ['Подтверждение записи', 'Право на подтверждение записей'],
        'calendar.delete_own' => ['Удаление своих записей', 'Право на удаление только своих записей'],
        'calendar.delete_all' => ['Удаление всех записей', 'Право на удаление записей всех врачей'],
    ];
    
    foreach ($newPermissions as $code => $data) {
        list($name, $description) = $data;
        
        // Проверяем существование
        $sqlCheck = "SELECT ID FROM artmax_calendar_permissions WHERE CODE = '" . $sqlHelper->forSql($code) . "'";
        $result = $connection->query($sqlCheck);
        
        if (!$result->fetch()) {
            $sqlInsert = "INSERT INTO artmax_calendar_permissions (CODE, NAME, DESCRIPTION) 
                          VALUES ('" . $sqlHelper->forSql($code) . "', '" . $sqlHelper->forSql($name) . "', '" . $sqlHelper->forSql($description) . "')";
            $connection->query($sqlInsert);
            echo "  - Добавлено право: $code\n";
        } else {
            echo "  - Право уже существует: $code (пропущено)\n";
        }
    }
    
    // ============================================
    // 3. Проверка результата
    // ============================================
    echo "\nТекущие права в базе данных:\n";
    $sqlSelect = "SELECT CODE, NAME FROM artmax_calendar_permissions ORDER BY CODE";
    $result = $connection->query($sqlSelect);
    
    while ($row = $result->fetch()) {
        echo "  - {$row['CODE']}: {$row['NAME']}\n";
    }
    
    echo "\nМиграция успешно выполнена!\n";
    
} catch (Exception $e) {
    echo "Ошибка миграции: " . $e->getMessage() . "\n";
    exit(1);
}