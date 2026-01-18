<?php
/**
 * PHP скрипт для выполнения миграции добавления новых прав
 * 
 * Использование:
 * 1. Через админ-панель: /local/modules/artmax.calendar/install/migrations/migrate_add_new_permissions.php
 * 2. Через командную строку: php migrate_add_new_permissions.php
 * 
 * Требования:
 * - Модуль artmax.calendar должен быть установлен
 * - Пользователь должен быть авторизован (для веб-версии)
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Application;

// Проверка авторизации (только для веб-версии)
if (!defined('BX_CRONTAB') && !$USER->IsAuthorized()) {
    die('Необходима авторизация для выполнения миграции');
}

// Проверка установки модуля
if (!CModule::IncludeModule('artmax.calendar')) {
    die('Модуль artmax.calendar не установлен');
}

$connection = Application::getConnection();
$sqlHelper = $connection->getSqlHelper();

echo "Начало выполнения миграции добавления новых прав...\n\n";

try {
    // ============================================
    // 1. Удаление устаревших прав
    // ============================================
    echo "1. Удаление устаревших прав...\n";
    
    $obsoletePermissions = ['calendar.admin', 'calendar.view'];
    
    foreach ($obsoletePermissions as $permissionCode) {
        // Получаем ID права
        $sqlCheck = "SELECT ID FROM artmax_calendar_permissions WHERE CODE = '" . $sqlHelper->forSql($permissionCode) . "'";
        $result = $connection->query($sqlCheck);
        $permission = $result->fetch();
        
        if ($permission) {
            $permissionId = (int)$permission['ID'];
            
            // Удаляем связи с группами/пользователями
            $sqlDeleteLinks = "DELETE FROM artmax_calendar_access_rights WHERE PERMISSION_ID = " . $permissionId;
            $connection->query($sqlDeleteLinks);
            echo "   - Удалены связи права $permissionCode с группами/пользователями\n";
            
            // Удаляем само право
            $sqlDeletePermission = "DELETE FROM artmax_calendar_permissions WHERE ID = " . $permissionId;
            $connection->query($sqlDeletePermission);
            echo "   - Удалено право $permissionCode\n";
        } else {
            $reason = ($permissionCode === 'calendar.admin') ? 'устаревшее' : 'не используется (заменено на calendar.view_others)';
            echo "   - Право $permissionCode не найдено (возможно, уже удалено или $reason)\n";
        }
    }
    
    echo "\n";
    
    // ============================================
    // 2. Добавление новых прав
    // ============================================
    echo "2. Добавление новых прав...\n";
    
    $newPermissions = [
        [
            'CODE' => 'calendar.edit',
            'NAME' => 'Редактирование записи',
            'DESCRIPTION' => 'Право на редактирование существующих записей'
        ],
        [
            'CODE' => 'calendar.delete',
            'NAME' => 'Удаление записи',
            'DESCRIPTION' => 'Право на удаление записей из календаря'
        ],
        [
            'CODE' => 'calendar.move',
            'NAME' => 'Перемещение записи',
            'DESCRIPTION' => 'Право на перемещение записей в календаре'
        ],
        [
            'CODE' => 'calendar.confirm',
            'NAME' => 'Подтверждение записи',
            'DESCRIPTION' => 'Право на подтверждение записей'
        ],
        [
            'CODE' => 'calendar.change_employee',
            'NAME' => 'Смена ответственного врача',
            'DESCRIPTION' => 'Право на смену ответственного врача в записи'
        ],
        [
            'CODE' => 'calendar.edit_title',
            'NAME' => 'Редактирование названия записи',
            'DESCRIPTION' => 'Право на редактирование названия записей'
        ],
        [
            'CODE' => 'calendar.edit_others_notes',
            'NAME' => 'Редактирование заметок чужих записей',
            'DESCRIPTION' => 'Право на редактирование заметок в чужих записях'
        ],
        [
            'CODE' => 'calendar.manage_schedule',
            'NAME' => 'Управление расписанием',
            'DESCRIPTION' => 'Право на управление расписанием врачей'
        ]
    ];
    
    $addedCount = 0;
    $skippedCount = 0;
    
    foreach ($newPermissions as $permission) {
        // Проверяем, существует ли уже право
        $sqlCheck = "SELECT ID FROM artmax_calendar_permissions WHERE CODE = '" . $sqlHelper->forSql($permission['CODE']) . "'";
        $result = $connection->query($sqlCheck);
        
        if ($result->getSelectedRowsCount() == 0) {
            // Добавляем право
            $sqlInsert = "
                INSERT INTO artmax_calendar_permissions (CODE, NAME, DESCRIPTION) 
                VALUES (
                    '" . $sqlHelper->forSql($permission['CODE']) . "',
                    '" . $sqlHelper->forSql($permission['NAME']) . "',
                    '" . $sqlHelper->forSql($permission['DESCRIPTION']) . "'
                )
            ";
            $connection->query($sqlInsert);
            echo "   + Добавлено право: {$permission['CODE']} - {$permission['NAME']}\n";
            $addedCount++;
        } else {
            echo "   - Право уже существует: {$permission['CODE']}\n";
            $skippedCount++;
        }
    }
    
    echo "\n";
    echo "Итого добавлено прав: $addedCount\n";
    echo "Пропущено (уже существуют): $skippedCount\n";
    echo "\n";
    
    // ============================================
    // 3. Вывод списка всех прав для проверки
    // ============================================
    echo "3. Список всех прав в системе:\n";
    $sqlAll = "SELECT CODE, NAME FROM artmax_calendar_permissions ORDER BY CODE ASC";
    $result = $connection->query($sqlAll);
    
    $permissionsList = [];
    while ($row = $result->fetch()) {
        $permissionsList[] = $row;
        echo "   - {$row['CODE']}: {$row['NAME']}\n";
    }
    
    echo "\n";
    echo "Всего прав в системе: " . count($permissionsList) . "\n";
    echo "\n";
    
    // ============================================
    // 4. Рекомендации
    // ============================================
    echo "4. Рекомендации:\n";
    echo "   - Проверьте назначение прав группам пользователей через админ-панель\n";
    echo "   - Убедитесь, что все права корректно отображаются в интерфейсе\n";
    echo "   - Проверьте работу проверок прав в коде\n";
    echo "\n";
    
    echo "Миграция успешно завершена!\n";
    
} catch (\Exception $e) {
    echo "ОШИБКА при выполнении миграции: " . $e->getMessage() . "\n";
    echo "Стек вызовов:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
