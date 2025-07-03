<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php');

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$APPLICATION->SetTitle('Исправление календаря ArtMax');

if (!CModule::IncludeModule('artmax.calendar')) {
    ShowError('Модуль artmax.calendar не установлен');
    require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
    die();
}

echo '<h2>Исправление настраиваемого раздела</h2>';

if (class_exists('\Bitrix\Intranet\CustomSection\Entity\CustomSectionTable')) {
    echo '<p>✅ Класс CustomSectionTable найден</p>';
    
    // Проверяем существующий раздел
    $existingSection = \Bitrix\Intranet\CustomSection\Entity\CustomSectionTable::getList([
        'filter' => ['MODULE_ID' => 'artmax.calendar', 'CODE' => 'artmax_calendar']
    ])->fetch();
    
    if ($existingSection) {
        echo '<p>✅ Раздел найден (ID: ' . $existingSection['ID'] . ')</p>';
        
        // Проверяем страницы
        $pages = \Bitrix\Intranet\CustomSection\Entity\CustomSectionPageTable::getList([
            'filter' => ['CUSTOM_SECTION_ID' => $existingSection['ID']]
        ])->fetchAll();
        
        echo '<p>Найдено страниц: ' . count($pages) . '</p>';
        
        if (empty($pages)) {
            echo '<p>⚠️ Страницы не найдены, создаем...</p>';
            \Artmax\Calendar\EventHandlers::createCustomSection();
            echo '<p>✅ Страницы созданы</p>';
        } else {
            echo '<ul>';
            foreach ($pages as $page) {
                echo '<li>' . $page['TITLE'] . ' (код: ' . $page['CODE'] . ')</li>';
            }
            echo '</ul>';
        }
        
        // Обновляем настройки
        \Bitrix\Main\Config\Option::set('artmax.calendar', 'custom_section_id', $existingSection['ID']);
        echo '<p>✅ Настройки обновлены</p>';
        
    } else {
        echo '<p>❌ Раздел не найден, создаем новый...</p>';
        $result = \Artmax\Calendar\EventHandlers::createCustomSection();
        if ($result) {
            echo '<p>✅ Раздел создан успешно</p>';
        } else {
            echo '<p>❌ Ошибка создания раздела</p>';
        }
    }
    
    echo '<h3>Проверка провайдера</h3>';
    if (class_exists('\Artmax\Calendar\Integration\Intranet\CustomSectionProvider')) {
        echo '<p>✅ Провайдер найден</p>';
    } else {
        echo '<p>❌ Провайдер не найден</p>';
    }
    
} else {
    echo '<p>❌ Класс CustomSectionTable не найден</p>';
    echo '<p>Возможно, у вас старая версия Bitrix. Используйте административное меню.</p>';
}

echo '<h3>Проверка административного меню</h3>';
if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/artmax_calendar_menu.php')) {
    echo '<p>✅ Файл административного меню найден</p>';
} else {
    echo '<p>❌ Файл административного меню не найден</p>';
}

echo '<h3>Действия</h3>';
echo '<form method="post">';
echo '<input type="submit" name="action" value="Удалить раздел" style="background: #d9534f; color: white; padding: 10px; margin: 5px; border: none; cursor: pointer;">';
echo '<input type="submit" name="action" value="Создать раздел" style="background: #5cb85c; color: white; padding: 10px; margin: 5px; border: none; cursor: pointer;">';
echo '<input type="submit" name="action" value="Очистить кеш" style="background: #f0ad4e; color: white; padding: 10px; margin: 5px; border: none; cursor: pointer;">';
echo '</form>';

// Обработка действий
if ($_POST['action']) {
    echo '<h3>Результат действия</h3>';
    
    switch ($_POST['action']) {
        case 'Удалить раздел':
            $result = \Artmax\Calendar\EventHandlers::removeCustomSection();
            if ($result) {
                echo '<p style="color: green;">✅ Раздел успешно удален</p>';
            } else {
                echo '<p style="color: red;">❌ Ошибка удаления раздела</p>';
            }
            break;
            
        case 'Создать раздел':
            $result = \Artmax\Calendar\EventHandlers::createCustomSection();
            if ($result) {
                echo '<p style="color: green;">✅ Раздел успешно создан</p>';
            } else {
                echo '<p style="color: red;">❌ Ошибка создания раздела</p>';
            }
            break;
            
        case 'Очистить кеш':
            $GLOBALS['CACHE_MANAGER']->CleanAll();
            echo '<p style="color: green;">✅ Кеш очищен</p>';
            break;
    }
}

echo '<h3>Рекомендации</h3>';
echo '<p>1. Очистите кеш Bitrix: Настройки → Настройки продукта → Автокеширование → Очистить кеш</p>';
echo '<p>2. Проверьте левое меню интранета - должен появиться раздел "Календарь ArtMax"</p>';
echo '<p>3. Если настраиваемые разделы не работают, проверьте административное меню</p>';

require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
?> 