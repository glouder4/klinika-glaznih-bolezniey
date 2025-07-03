<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php');

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$APPLICATION->SetTitle('Тест провайдера календаря ArtMax');

if (!CModule::IncludeModule('artmax.calendar')) {
    ShowError('Модуль artmax.calendar не установлен');
    require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
    die();
}

echo '<h2>Тест провайдера настраиваемого раздела</h2>';

// Проверяем наличие провайдера
if (class_exists('\Artmax\Calendar\Integration\Intranet\CustomSectionProvider')) {
    echo '<p>✅ Класс провайдера найден</p>';
    
    try {
        $provider = new \Artmax\Calendar\Integration\Intranet\CustomSectionProvider();
        echo '<p>✅ Провайдер создан успешно</p>';
        
        // Тестируем методы провайдера
        echo '<h3>Тест методов провайдера</h3>';
        
        // Тест getComponentName
        try {
            $componentName = $provider->getComponentName();
            echo '<p>✅ getComponentName(): ' . $componentName . '</p>';
        } catch (Exception $e) {
            echo '<p style="color: red;">❌ getComponentName(): ' . $e->getMessage() . '</p>';
        }
        
        // Тест getComponentTemplate
        try {
            $template = $provider->getComponentTemplate();
            echo '<p>✅ getComponentTemplate(): ' . $template . '</p>';
        } catch (Exception $e) {
            echo '<p style="color: red;">❌ getComponentTemplate(): ' . $e->getMessage() . '</p>';
        }
        
        // Тест isAvailable
        try {
            $isAvailable = $provider->isAvailable('1~Главный офис', 1);
            echo '<p>✅ isAvailable(): ' . ($isAvailable ? 'true' : 'false') . '</p>';
        } catch (Exception $e) {
            echo '<p style="color: red;">❌ isAvailable(): ' . $e->getMessage() . '</p>';
        }
        
        // Тест resolveComponent
        try {
            $component = $provider->resolveComponent('1~Главный офис', new \Bitrix\Main\Web\Uri('/test'));
            if ($component) {
                echo '<p>✅ resolveComponent(): компонент создан</p>';
            } else {
                echo '<p style="color: orange;">⚠️ resolveComponent(): компонент не создан</p>';
            }
        } catch (Exception $e) {
            echo '<p style="color: red;">❌ resolveComponent(): ' . $e->getMessage() . '</p>';
        }
        
        // Тест getCounterId
        try {
            $counterId = $provider->getCounterId('1~Главный офис');
            echo '<p>✅ getCounterId(): ' . ($counterId ?? 'null') . '</p>';
        } catch (Exception $e) {
            echo '<p style="color: red;">❌ getCounterId(): ' . $e->getMessage() . '</p>';
        }
        
        // Тест getCounterValue
        try {
            $counterValue = $provider->getCounterValue('1~Главный офис');
            echo '<p>✅ getCounterValue(): ' . ($counterValue ?? 'null') . '</p>';
        } catch (Exception $e) {
            echo '<p style="color: red;">❌ getCounterValue(): ' . $e->getMessage() . '</p>';
        }
        
    } catch (Exception $e) {
        echo '<p style="color: red;">❌ Ошибка создания провайдера: ' . $e->getMessage() . '</p>';
    }
} else {
    echo '<p style="color: red;">❌ Класс провайдера не найден</p>';
}

echo '<h3>Проверка компонента</h3>';
$componentPath = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/components/artmax/calendar/class.php';
if (file_exists($componentPath)) {
    echo '<p>✅ Компонент найден</p>';
} else {
    echo '<p style="color: red;">❌ Компонент не найден: ' . $componentPath . '</p>';
}

$templatePath = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/components/artmax/calendar/templates/.default/template.php';
if (file_exists($templatePath)) {
    echo '<p>✅ Шаблон компонента найден</p>';
} else {
    echo '<p style="color: red;">❌ Шаблон компонента не найден: ' . $templatePath . '</p>';
}

echo '<h3>Действия</h3>';
echo '<form method="post">';
echo '<input type="submit" name="action" value="Зарегистрировать провайдер" style="background: #5cb85c; color: white; padding: 10px; margin: 5px; border: none; cursor: pointer;">';
echo '<input type="submit" name="action" value="Очистить кеш" style="background: #f0ad4e; color: white; padding: 10px; margin: 5px; border: none; cursor: pointer;">';
echo '</form>';

if ($_POST['action']) {
    echo '<h3>Результат действия</h3>';
    
    switch ($_POST['action']) {
        case 'Зарегистрировать провайдер':
            try {
                if (CModule::IncludeModule('intranet')) {
                    if (class_exists('\Bitrix\Intranet\CustomSection\Provider\Manager')) {
                        \Bitrix\Intranet\CustomSection\Provider\Manager::registerProvider(
                            'artmax.calendar',
                            \Artmax\Calendar\Integration\Intranet\CustomSectionProvider::class
                        );
                        echo '<p style="color: green;">✅ Провайдер зарегистрирован через Manager</p>';
                    } elseif (class_exists('\Bitrix\Intranet\CustomSection\ProviderManager')) {
                        \Bitrix\Intranet\CustomSection\ProviderManager::registerProvider(
                            'artmax.calendar',
                            \Artmax\Calendar\Integration\Intranet\CustomSectionProvider::class
                        );
                        echo '<p style="color: green;">✅ Провайдер зарегистрирован через ProviderManager</p>';
                    } else {
                        echo '<p style="color: red;">❌ Класс менеджера провайдеров не найден</p>';
                    }
                } else {
                    echo '<p style="color: red;">❌ Модуль intranet не подключен</p>';
                }
            } catch (Exception $e) {
                echo '<p style="color: red;">❌ Ошибка регистрации: ' . $e->getMessage() . '</p>';
            }
            break;
            
        case 'Очистить кеш':
            $GLOBALS['CACHE_MANAGER']->CleanAll();
            echo '<p style="color: green;">✅ Кеш очищен</p>';
            break;
    }
}

echo '<h3>Рекомендации</h3>';
echo '<p>1. Убедитесь, что все методы провайдера работают корректно</p>';
echo '<p>2. Проверьте, что компонент и шаблон существуют</p>';
echo '<p>3. Зарегистрируйте провайдер через кнопку выше</p>';
echo '<p>4. Очистите кеш Bitrix</p>';
echo '<p>5. Проверьте настраиваемый раздел в интранете</p>';

echo '<h3>Ссылки</h3>';
echo '<p><a href="/bitrix/admin/artmax_calendar_classes.php" target="_blank">Проверка классов</a></p>';
echo '<p><a href="/bitrix/admin/artmax_calendar_register.php" target="_blank">Страница регистрации</a></p>';
echo '<p><a href="/bitrix/admin/artmax_calendar_fix.php" target="_blank">Страница исправления</a></p>';

require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
?> 