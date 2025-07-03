<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php');

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$APPLICATION->SetTitle('Отладка календаря ArtMax');

if (!CModule::IncludeModule('artmax.calendar')) {
    ShowError('Модуль artmax.calendar не установлен');
    require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
    die();
}

echo '<h2>Проверка настраиваемых разделов</h2>';

// Проверяем таблицы настраиваемых разделов
if (class_exists('\Bitrix\Intranet\CustomSection\Entity\CustomSectionTable')) {
    echo '<p>✅ Класс CustomSectionTable найден</p>';
    
    // Проверяем существующие разделы
    $sections = \Bitrix\Intranet\CustomSection\Entity\CustomSectionTable::getList([
        'filter' => ['MODULE_ID' => 'artmax.calendar']
    ])->fetchAll();
    
    echo '<h3>Найденные разделы:</h3>';
    if (empty($sections)) {
        echo '<p>❌ Разделы не найдены</p>';
        
        // Пытаемся создать раздел
        echo '<h3>Попытка создания раздела:</h3>';
        $result = \Artmax\Calendar\EventHandlers::createCustomSection();
        if ($result) {
            echo '<p>✅ Раздел создан успешно</p>';
        } else {
            echo '<p>❌ Ошибка создания раздела</p>';
        }
    } else {
        echo '<ul>';
        foreach ($sections as $section) {
            echo '<li>ID: ' . $section['ID'] . ', Код: ' . $section['CODE'] . ', Название: ' . $section['TITLE'] . '</li>';
        }
        echo '</ul>';
        
        // Проверяем страницы раздела
        foreach ($sections as $section) {
            echo '<h3>Страницы раздела ' . $section['TITLE'] . ':</h3>';
            $pages = \Bitrix\Intranet\CustomSection\Entity\CustomSectionPageTable::getList([
                'filter' => ['CUSTOM_SECTION_ID' => $section['ID']]
            ])->fetchAll();
            
            if (empty($pages)) {
                echo '<p>❌ Страницы не найдены</p>';
            } else {
                echo '<ul>';
                foreach ($pages as $page) {
                    echo '<li>Код: ' . $page['CODE'] . ', Название: ' . $page['TITLE'] . ', Настройки: ' . $page['SETTINGS'] . '</li>';
                }
                echo '</ul>';
            }
        }
    }
} else {
    echo '<p>❌ Класс CustomSectionTable не найден</p>';
}

echo '<h2>Проверка филиалов</h2>';
$branchObj = new \Artmax\Calendar\Branch();
$branches = $branchObj->getBranches();

if (empty($branches)) {
    echo '<p>❌ Филиалы не найдены</p>';
} else {
    echo '<p>✅ Найдено филиалов: ' . count($branches) . '</p>';
    echo '<ul>';
    foreach ($branches as $branch) {
        echo '<li>ID: ' . $branch['ID'] . ', Название: ' . $branch['NAME'] . '</li>';
    }
    echo '</ul>';
}

echo '<h2>Проверка настроек модуля</h2>';
$customSectionId = \Bitrix\Main\Config\Option::get('artmax.calendar', 'custom_section_id', '');
if ($customSectionId) {
    echo '<p>✅ ID раздела в настройках: ' . $customSectionId . '</p>';
} else {
    echo '<p>❌ ID раздела в настройках не найден</p>';
}

echo '<h2>Проверка провайдера</h2>';
if (class_exists('\Artmax\Calendar\Integration\Intranet\CustomSectionProvider')) {
    echo '<p>✅ Провайдер найден</p>';
} else {
    echo '<p>❌ Провайдер не найден</p>';
}

require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
?> 