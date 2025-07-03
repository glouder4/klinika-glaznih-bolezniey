<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php');

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$APPLICATION->SetTitle('Безопасное удаление календаря ArtMax');

if (!CModule::IncludeModule('artmax.calendar')) {
    ShowError('Модуль artmax.calendar не установлен');
    require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
    die();
}

echo '<h2>Безопасное удаление модуля календаря ArtMax</h2>';

echo '<h3>Предупреждение</h3>';
echo '<p style="color: red; font-weight: bold;">⚠️ Это действие удалит модуль календаря и все связанные с ним данные!</p>';
echo '<p>Перед удалением убедитесь, что вы сохранили все важные данные.</p>';

echo '<h3>Действия</h3>';
echo '<form method="post" onsubmit="return confirm(\'Вы уверены, что хотите удалить модуль календаря ArtMax? Это действие нельзя отменить.\');">';
echo '<input type="submit" name="action" value="Безопасно удалить модуль" style="background: #d9534f; color: white; padding: 15px; margin: 10px; border: none; cursor: pointer; font-size: 16px;">';
echo '</form>';

if ($_POST['action'] === 'Безопасно удалить модуль') {
    echo '<h3>Процесс удаления</h3>';
    
    try {
        // Шаг 1: Отменяем регистрацию событий
        echo '<p>1. Отмена регистрации событий...</p>';
        try {
            \Artmax\Calendar\Events::unregisterEvents();
            echo '<p style="color: green;">✅ События отменены</p>';
        } catch (Exception $e) {
            echo '<p style="color: orange;">⚠️ Ошибка отмены событий: ' . $e->getMessage() . '</p>';
        }
        
        // Шаг 2: Удаляем настраиваемый раздел
        echo '<p>2. Удаление настраиваемого раздела...</p>';
        try {
            \Artmax\Calendar\EventHandlers::removeCustomSection();
            echo '<p style="color: green;">✅ Настраиваемый раздел удален</p>';
        } catch (Exception $e) {
            echo '<p style="color: orange;">⚠️ Ошибка удаления раздела: ' . $e->getMessage() . '</p>';
        }
        
        // Шаг 3: Удаляем данные из базы
        echo '<p>3. Удаление данных из базы...</p>';
        try {
            $connection = \Bitrix\Main\Application::getConnection();
            $connection->query("DROP TABLE IF EXISTS artmax_calendar_events");
            $connection->query("DROP TABLE IF EXISTS artmax_calendar_branches");
            echo '<p style="color: green;">✅ Таблицы удалены</p>';
        } catch (Exception $e) {
            echo '<p style="color: orange;">⚠️ Ошибка удаления таблиц: ' . $e->getMessage() . '</p>';
        }
        
        // Шаг 4: Удаляем настройки
        echo '<p>4. Удаление настроек...</p>';
        try {
            \Bitrix\Main\Config\Option::delete('artmax.calendar', ['name' => 'menu_item_id']);
            \Bitrix\Main\Config\Option::delete('artmax.calendar', ['name' => 'custom_section_id']);
            echo '<p style="color: green;">✅ Настройки удалены</p>';
        } catch (Exception $e) {
            echo '<p style="color: orange;">⚠️ Ошибка удаления настроек: ' . $e->getMessage() . '</p>';
        }
        
        // Шаг 5: Удаляем файлы
        echo '<p>5. Удаление файлов...</p>';
        $filesToDelete = [
            '/bitrix/admin/artmax_calendar_menu.php',
            '/bitrix/admin/artmax_calendar_debug.php',
            '/bitrix/admin/artmax_calendar_fix.php',
            '/bitrix/admin/artmax_calendar_register.php',
            '/bitrix/admin/artmax_calendar_test.php',
            '/bitrix/admin/artmax_calendar_classes.php',
            '/bitrix/admin/artmax_calendar_provider_test.php',
            '/bitrix/admin/artmax_calendar_safe_uninstall.php',
            '/artmax-calendar.php'
        ];
        
        foreach ($filesToDelete as $file) {
            $fullPath = $_SERVER['DOCUMENT_ROOT'] . $file;
            if (file_exists($fullPath)) {
                if (unlink($fullPath)) {
                    echo '<p style="color: green;">✅ Удален: ' . $file . '</p>';
                } else {
                    echo '<p style="color: orange;">⚠️ Не удалось удалить: ' . $file . '</p>';
                }
            }
        }
        
        // Шаг 6: Удаляем компонент
        echo '<p>6. Удаление компонента...</p>';
        $componentPath = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/components/artmax/calendar';
        if (is_dir($componentPath)) {
            if (self::deleteDirectory($componentPath)) {
                echo '<p style="color: green;">✅ Компонент удален</p>';
            } else {
                echo '<p style="color: orange;">⚠️ Не удалось удалить компонент</p>';
            }
        }
        
        echo '<h3>Удаление завершено</h3>';
        echo '<p style="color: green; font-weight: bold;">✅ Модуль календаря ArtMax успешно удален!</p>';
        echo '<p>Теперь вы можете удалить модуль через административную панель Bitrix.</p>';
        
    } catch (Exception $e) {
        echo '<p style="color: red;">❌ Критическая ошибка: ' . $e->getMessage() . '</p>';
    }
}

/**
 * Рекурсивно удаляет директорию
 */
function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }
    
    return rmdir($dir);
}

echo '<h3>Ссылки</h3>';
echo '<p><a href="/bitrix/admin/partner_modules.php" target="_blank">Управление модулями</a></p>';
echo '<p><a href="/bitrix/admin/artmax_calendar_fix.php" target="_blank">Страница исправления</a></p>';

require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
?> 