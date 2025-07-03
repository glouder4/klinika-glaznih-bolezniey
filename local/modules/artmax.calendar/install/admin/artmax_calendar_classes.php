<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php');

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$APPLICATION->SetTitle('Проверка классов календаря ArtMax');

echo '<h2>Проверка доступных классов</h2>';

echo '<h3>Проверка модулей</h3>';
if (CModule::IncludeModule('intranet')) {
    echo '<p>✅ Модуль intranet подключен</p>';
} else {
    echo '<p style="color: red;">❌ Модуль intranet не подключен</p>';
}

if (CModule::IncludeModule('artmax.calendar')) {
    echo '<p>✅ Модуль artmax.calendar подключен</p>';
} else {
    echo '<p style="color: red;">❌ Модуль artmax.calendar не подключен</p>';
}

echo '<h3>Проверка классов настраиваемых разделов</h3>';

$classesToCheck = [
    '\Bitrix\Intranet\CustomSection\Provider\Manager',
    '\Bitrix\Intranet\CustomSection\ProviderManager',
    '\Bitrix\Intranet\CustomSection\Entity\CustomSectionTable',
    '\Bitrix\Intranet\CustomSection\Entity\CustomSectionPageTable',
    '\Artmax\Calendar\Integration\Intranet\CustomSectionProvider',
    '\Artmax\Calendar\Events',
    '\Artmax\Calendar\EventHandlers',
    '\Artmax\Calendar\Calendar',
    '\Artmax\Calendar\Branch'
];

foreach ($classesToCheck as $class) {
    if (class_exists($class)) {
        echo '<p>✅ Класс ' . $class . ' найден</p>';
    } else {
        echo '<p style="color: red;">❌ Класс ' . $class . ' не найден</p>';
    }
}

echo '<h3>Проверка методов</h3>';

if (class_exists('\Bitrix\Intranet\CustomSection\Provider\Manager')) {
    $methods = get_class_methods('\Bitrix\Intranet\CustomSection\Provider\Manager');
    echo '<p>Методы Manager:</p><ul>';
    foreach ($methods as $method) {
        echo '<li>' . $method . '</li>';
    }
    echo '</ul>';
}

if (class_exists('\Bitrix\Intranet\CustomSection\ProviderManager')) {
    $methods = get_class_methods('\Bitrix\Intranet\CustomSection\ProviderManager');
    echo '<p>Методы ProviderManager:</p><ul>';
    foreach ($methods as $method) {
        echo '<li>' . $method . '</li>';
    }
    echo '</ul>';
}

echo '<h3>Проверка событий</h3>';
echo '<form method="post">';
echo '<input type="submit" name="action" value="Зарегистрировать события" style="background: #5cb85c; color: white; padding: 10px; margin: 5px; border: none; cursor: pointer;">';
echo '<input type="submit" name="action" value="Отменить регистрацию событий" style="background: #d9534f; color: white; padding: 10px; margin: 5px; border: none; cursor: pointer;">';
echo '</form>';

if ($_POST['action']) {
    echo '<h3>Результат действия</h3>';
    
    switch ($_POST['action']) {
        case 'Зарегистрировать события':
            try {
                if (class_exists('\Artmax\Calendar\Events')) {
                    \Artmax\Calendar\Events::registerEvents();
                    echo '<p style="color: green;">✅ События зарегистрированы</p>';
                } else {
                    echo '<p style="color: red;">❌ Класс Events не найден</p>';
                }
            } catch (Exception $e) {
                echo '<p style="color: red;">❌ Ошибка: ' . $e->getMessage() . '</p>';
            }
            break;
            
        case 'Отменить регистрацию событий':
            try {
                if (class_exists('\Artmax\Calendar\Events')) {
                    \Artmax\Calendar\Events::unregisterEvents();
                    echo '<p style="color: green;">✅ Регистрация событий отменена</p>';
                } else {
                    echo '<p style="color: red;">❌ Класс Events не найден</p>';
                }
            } catch (Exception $e) {
                echo '<p style="color: red;">❌ Ошибка: ' . $e->getMessage() . '</p>';
            }
            break;
    }
}

echo '<h3>Ссылки</h3>';
echo '<p><a href="/bitrix/admin/artmax_calendar_register.php" target="_blank">Страница регистрации</a></p>';
echo '<p><a href="/bitrix/admin/artmax_calendar_fix.php" target="_blank">Страница исправления</a></p>';
echo '<p><a href="/bitrix/admin/artmax_calendar_test.php" target="_blank">Страница тестирования</a></p>';

require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
?> 