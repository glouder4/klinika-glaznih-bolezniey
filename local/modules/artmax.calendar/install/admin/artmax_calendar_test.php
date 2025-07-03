<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php');

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$APPLICATION->SetTitle('Тест календаря ArtMax');

if (!CModule::IncludeModule('artmax.calendar')) {
    ShowError('Модуль artmax.calendar не установлен');
    require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
    die();
}

echo '<h2>Тест календаря ArtMax</h2>';

// Тестируем класс Branch
echo '<h3>Тест класса Branch</h3>';
try {
    $branchObj = new \Artmax\Calendar\Branch();
    $branches = $branchObj->getBranches();
    echo '<p>✅ Класс Branch работает. Найдено филиалов: ' . count($branches) . '</p>';
    
    if (!empty($branches)) {
        echo '<ul>';
        foreach ($branches as $branch) {
            echo '<li>' . $branch['NAME'] . ' (ID: ' . $branch['ID'] . ')</li>';
        }
        echo '</ul>';
    }
} catch (Exception $e) {
    echo '<p style="color: red;">❌ Ошибка в классе Branch: ' . $e->getMessage() . '</p>';
}

// Тестируем класс Calendar
echo '<h3>Тест класса Calendar</h3>';
try {
    $calendarObj = new \Artmax\Calendar\Calendar();
    
    // Тестируем получение событий
    $events = $calendarObj->getAllEvents(5);
    echo '<p>✅ Класс Calendar работает. Найдено событий: ' . count($events) . '</p>';
    
    if (!empty($events)) {
        echo '<ul>';
        foreach ($events as $event) {
            echo '<li>' . $event['TITLE'] . ' - ' . $event['DATE_FROM'] . ' (Филиал: ' . ($event['BRANCH_NAME'] ?? 'Не указан') . ')</li>';
        }
        echo '</ul>';
    }
    
    // Тестируем статистику
    $stats = $calendarObj->getEventsStats();
    echo '<p>Статистика: Всего событий - ' . $stats['total_events'] . ', Пользователей - ' . $stats['unique_users'] . ', Филиалов - ' . $stats['branches_used'] . '</p>';
    
} catch (Exception $e) {
    echo '<p style="color: red;">❌ Ошибка в классе Calendar: ' . $e->getMessage() . '</p>';
}

// Тестируем добавление тестового события
echo '<h3>Тест добавления события</h3>';
echo '<form method="post">';
echo '<input type="submit" name="add_test_event" value="Добавить тестовое событие" style="background: #5cb85c; color: white; padding: 10px; margin: 5px; border: none; cursor: pointer;">';
echo '</form>';

if ($_POST['add_test_event']) {
    try {
        $calendarObj = new \Artmax\Calendar\Calendar();
        $branchObj = new \Artmax\Calendar\Branch();
        $branches = $branchObj->getBranches();
        
        if (!empty($branches)) {
            $branchId = $branches[0]['ID'];
            $result = $calendarObj->addEvent(
                'Тестовое событие ' . date('Y-m-d H:i:s'),
                'Это тестовое событие для проверки работы календаря',
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s', strtotime('+1 hour')),
                1, // ID пользователя
                $branchId
            );
            
            if ($result) {
                echo '<p style="color: green;">✅ Тестовое событие добавлено с ID: ' . $result . '</p>';
            } else {
                echo '<p style="color: red;">❌ Ошибка добавления тестового события</p>';
            }
        } else {
            echo '<p style="color: orange;">⚠️ Нет филиалов для добавления события</p>';
        }
    } catch (Exception $e) {
        echo '<p style="color: red;">❌ Ошибка добавления события: ' . $e->getMessage() . '</p>';
    }
}

echo '<h3>Проверка таблиц</h3>';
$connection = \Bitrix\Main\Application::getConnection();

// Проверяем таблицу филиалов
try {
    $result = $connection->query("SHOW TABLES LIKE 'artmax_calendar_branches'");
    if ($result->fetch()) {
        echo '<p>✅ Таблица artmax_calendar_branches существует</p>';
        
        $result = $connection->query("SELECT COUNT(*) as count FROM artmax_calendar_branches");
        $row = $result->fetch();
        echo '<p>Записей в таблице филиалов: ' . $row['count'] . '</p>';
    } else {
        echo '<p style="color: red;">❌ Таблица artmax_calendar_branches не существует</p>';
    }
} catch (Exception $e) {
    echo '<p style="color: red;">❌ Ошибка проверки таблицы филиалов: ' . $e->getMessage() . '</p>';
}

// Проверяем таблицу событий
try {
    $result = $connection->query("SHOW TABLES LIKE 'artmax_calendar_events'");
    if ($result->fetch()) {
        echo '<p>✅ Таблица artmax_calendar_events существует</p>';
        
        $result = $connection->query("SELECT COUNT(*) as count FROM artmax_calendar_events");
        $row = $result->fetch();
        echo '<p>Записей в таблице событий: ' . $row['count'] . '</p>';
    } else {
        echo '<p style="color: red;">❌ Таблица artmax_calendar_events не существует</p>';
    }
} catch (Exception $e) {
    echo '<p style="color: red;">❌ Ошибка проверки таблицы событий: ' . $e->getMessage() . '</p>';
}

echo '<h3>Ссылки</h3>';
echo '<p><a href="/bitrix/admin/artmax_calendar_fix.php" target="_blank">Страница исправления</a></p>';
echo '<p><a href="/bitrix/admin/artmax_calendar_debug.php" target="_blank">Страница отладки</a></p>';
echo '<p><a href="/bitrix/admin/artmax_calendar_menu.php" target="_blank">Административное меню</a></p>';

require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
?> 