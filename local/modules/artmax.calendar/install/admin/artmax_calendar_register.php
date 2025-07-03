<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php');

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$APPLICATION->SetTitle('Регистрация провайдера календаря ArtMax');

if (!CModule::IncludeModule('artmax.calendar')) {
    ShowError('Модуль artmax.calendar не установлен');
    require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
    die();
}

echo '<h2>Регистрация провайдера настраиваемого раздела</h2>';

// Проверяем наличие модуля intranet
if (!CModule::IncludeModule('intranet')) {
    echo '<p style="color: red;">❌ Модуль intranet не установлен</p>';
    echo '<p>Настраиваемые разделы требуют модуль intranet</p>';
} else {
    echo '<p>✅ Модуль intranet найден</p>';
}

// Проверяем наличие класса провайдера
if (class_exists('\Artmax\Calendar\Integration\Intranet\CustomSectionProvider')) {
    echo '<p>✅ Класс провайдера найден</p>';
} else {
    echo '<p style="color: red;">❌ Класс провайдера не найден</p>';
}

// Проверяем наличие класса менеджера провайдеров
if (class_exists('\Bitrix\Intranet\CustomSection\Provider\Manager')) {
    echo '<p>✅ Класс менеджера провайдеров найден</p>';
} else {
    echo '<p style="color: red;">❌ Класс менеджера провайдеров не найден</p>';
}

echo '<h3>Действия</h3>';
echo '<form method="post">';
echo '<input type="submit" name="action" value="Зарегистрировать провайдер" style="background: #5cb85c; color: white; padding: 10px; margin: 5px; border: none; cursor: pointer;">';
echo '<input type="submit" name="action" value="Проверить регистрацию" style="background: #17a2b8; color: white; padding: 10px; margin: 5px; border: none; cursor: pointer;">';
echo '<input type="submit" name="action" value="Очистить кеш" style="background: #f0ad4e; color: white; padding: 10px; margin: 5px; border: none; cursor: pointer;">';
echo '</form>';

// Обработка действий
if ($_POST['action']) {
    echo '<h3>Результат действия</h3>';
    
    switch ($_POST['action']) {
        case 'Зарегистрировать провайдер':
            try {
                if (CModule::IncludeModule('intranet') && CModule::IncludeModule('artmax.calendar')) {
                    \Bitrix\Intranet\CustomSection\Provider\Manager::registerProvider(
                        'artmax.calendar',
                        \Artmax\Calendar\Integration\Intranet\CustomSectionProvider::class
                    );
                    echo '<p style="color: green;">✅ Провайдер зарегистрирован</p>';
                } else {
                    echo '<p style="color: red;">❌ Не удалось подключить модули</p>';
                }
            } catch (Exception $e) {
                echo '<p style="color: red;">❌ Ошибка регистрации: ' . $e->getMessage() . '</p>';
            }
            break;
            
        case 'Проверить регистрацию':
            try {
                if (class_exists('\Bitrix\Intranet\CustomSection\Provider\Manager')) {
                    // Попробуем получить зарегистрированные провайдеры
                    $reflection = new ReflectionClass('\Bitrix\Intranet\CustomSection\Provider\Manager');
                    $providers = $reflection->getStaticProperties();
                    echo '<p>✅ Менеджер провайдеров доступен</p>';
                    
                    // Проверяем, есть ли наш провайдер в списке
                    if (isset($providers['providers']) && is_array($providers['providers'])) {
                        if (isset($providers['providers']['artmax.calendar'])) {
                            echo '<p style="color: green;">✅ Провайдер artmax.calendar зарегистрирован</p>';
                        } else {
                            echo '<p style="color: orange;">⚠️ Провайдер artmax.calendar не найден в списке</p>';
                        }
                    } else {
                        echo '<p style="color: orange;">⚠️ Не удалось получить список провайдеров</p>';
                    }
                } else {
                    echo '<p style="color: red;">❌ Менеджер провайдеров недоступен</p>';
                }
            } catch (Exception $e) {
                echo '<p style="color: red;">❌ Ошибка проверки: ' . $e->getMessage() . '</p>';
            }
            break;
            
        case 'Очистить кеш':
            $GLOBALS['CACHE_MANAGER']->CleanAll();
            echo '<p style="color: green;">✅ Кеш очищен</p>';
            break;
    }
}

echo '<h3>Проверка компонента</h3>';
if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/bitrix/components/artmax/calendar/class.php')) {
    echo '<p>✅ Компонент найден</p>';
} else {
    echo '<p style="color: red;">❌ Компонент не найден</p>';
}

echo '<h3>Проверка шаблона</h3>';
if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/bitrix/components/artmax/calendar/templates/.default/template.php')) {
    echo '<p>✅ Шаблон компонента найден</p>';
} else {
    echo '<p style="color: red;">❌ Шаблон компонента не найден</p>';
}

echo '<h3>Проверка автозагрузки модуля</h3>';
if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/local/modules/artmax.calendar/include.php')) {
    echo '<p>✅ Файл include.php модуля найден</p>';
} else {
    echo '<p style="color: red;">❌ Файл include.php модуля не найден</p>';
}

if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/local/modules/artmax.calendar/lib/autoload.php')) {
    echo '<p>✅ Файл автозагрузки найден</p>';
} else {
    echo '<p style="color: red;">❌ Файл автозагрузки не найден</p>';
}

echo '<h3>Рекомендации</h3>';
echo '<p>1. Убедитесь, что модуль intranet установлен и активен</p>';
echo '<p>2. Модуль автоматически регистрирует провайдер при подключении</p>';
echo '<p>3. Очистите кеш Bitrix для применения изменений</p>';
echo '<p>4. Проверьте настраиваемый раздел в левом меню интранета</p>';
echo '<p>5. Если настраиваемые разделы не работают, используйте административное меню</p>';

echo '<h3>Ссылки</h3>';
echo '<p><a href="/bitrix/admin/artmax_calendar_fix.php" target="_blank">Страница исправления</a></p>';
echo '<p><a href="/bitrix/admin/artmax_calendar_test.php" target="_blank">Страница тестирования</a></p>';
echo '<p><a href="/bitrix/admin/artmax_calendar_menu.php" target="_blank">Административное меню</a></p>';

require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
?> 