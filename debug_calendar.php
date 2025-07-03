<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

echo '<h1>Отладка календаря ArtMax</h1>';

// Проверяем модуль
if (!CModule::IncludeModule('artmax.calendar')) {
    echo '<p style="color: red;">❌ Модуль artmax.calendar не установлен</p>';
} else {
    echo '<p style="color: green;">✅ Модуль artmax.calendar установлен</p>';
}

// Проверяем филиалы
echo '<h2>Проверка филиалов</h2>';
try {
    $branchObj = new \Artmax\Calendar\Branch();
    $branches = $branchObj->getBranches();
    
    if (empty($branches)) {
        echo '<p style="color: red;">❌ Филиалы не найдены</p>';
    } else {
        echo '<p style="color: green;">✅ Найдено филиалов: ' . count($branches) . '</p>';
        echo '<ul>';
        foreach ($branches as $branch) {
            echo '<li>ID: ' . $branch['ID'] . ', Название: ' . $branch['NAME'] . '</li>';
        }
        echo '</ul>';
    }
} catch (Exception $e) {
    echo '<p style="color: red;">❌ Ошибка при получении филиалов: ' . $e->getMessage() . '</p>';
}

// Проверяем настраиваемые разделы
echo '<h2>Проверка настраиваемых разделов</h2>';
if (class_exists('\Bitrix\Intranet\CustomSection\Entity\CustomSectionTable')) {
    echo '<p style="color: green;">✅ Класс CustomSectionTable найден</p>';
    
    try {
        $sections = \Bitrix\Intranet\CustomSection\Entity\CustomSectionTable::getList([
            'filter' => ['MODULE_ID' => 'artmax.calendar']
        ])->fetchAll();
        
        if (empty($sections)) {
            echo '<p style="color: orange;">⚠️ Разделы не найдены, пытаемся создать...</p>';
            $result = \Artmax\Calendar\EventHandlers::createCustomSection();
            if ($result) {
                echo '<p style="color: green;">✅ Раздел создан успешно</p>';
            } else {
                echo '<p style="color: red;">❌ Ошибка создания раздела</p>';
            }
        } else {
            echo '<p style="color: green;">✅ Найдено разделов: ' . count($sections) . '</p>';
            foreach ($sections as $section) {
                echo '<p>Раздел: ' . $section['TITLE'] . ' (ID: ' . $section['ID'] . ')</p>';
            }
        }
    } catch (Exception $e) {
        echo '<p style="color: red;">❌ Ошибка при работе с разделами: ' . $e->getMessage() . '</p>';
    }
} else {
    echo '<p style="color: red;">❌ Класс CustomSectionTable не найден (возможно, старая версия Bitrix)</p>';
}

// Проверяем настройки
echo '<h2>Проверка настроек модуля</h2>';
$customSectionId = \Bitrix\Main\Config\Option::get('artmax.calendar', 'custom_section_id', '');
if ($customSectionId) {
    echo '<p style="color: green;">✅ ID раздела в настройках: ' . $customSectionId . '</p>';
} else {
    echo '<p style="color: orange;">⚠️ ID раздела в настройках не найден</p>';
}

// Проверяем провайдер
echo '<h2>Проверка провайдера</h2>';
if (class_exists('\Artmax\Calendar\Integration\Intranet\CustomSectionProvider')) {
    echo '<p style="color: green;">✅ Провайдер найден</p>';
} else {
    echo '<p style="color: red;">❌ Провайдер не найден</p>';
}

// Проверяем файлы
echo '<h2>Проверка файлов</h2>';
$files = [
    '/local/modules/artmax.calendar/.settings.php' => '.settings.php',
    '/bitrix/admin/artmax_calendar_menu.php' => 'Файл меню',
    '/artmax-calendar.php' => 'Страница календаря',
];

foreach ($files as $path => $name) {
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . $path)) {
        echo '<p style="color: green;">✅ ' . $name . ' найден</p>';
    } else {
        echo '<p style="color: red;">❌ ' . $name . ' не найден</p>';
    }
}

// Проверяем таблицы БД
echo '<h2>Проверка таблиц базы данных</h2>';
$connection = \Bitrix\Main\Application::getConnection();
$tables = ['artmax_calendar_events', 'artmax_calendar_branches'];

foreach ($tables as $table) {
    try {
        $result = $connection->query("SHOW TABLES LIKE '$table'");
        if ($result->fetch()) {
            echo '<p style="color: green;">✅ Таблица ' . $table . ' существует</p>';
        } else {
            echo '<p style="color: red;">❌ Таблица ' . $table . ' не существует</p>';
        }
    } catch (Exception $e) {
        echo '<p style="color: red;">❌ Ошибка проверки таблицы ' . $table . ': ' . $e->getMessage() . '</p>';
    }
}

echo '<h2>Рекомендации</h2>';
echo '<p>1. Если настраиваемые разделы не работают, проверьте административное меню</p>';
echo '<p>2. Проверьте логи в админке: Настройки → Журнал событий</p>';
echo '<p>3. Попробуйте переустановить модуль</p>';

require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
?> 