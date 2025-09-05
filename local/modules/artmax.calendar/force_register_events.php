<?php
/**
 * Скрипт для принудительной регистрации событий модуля artmax.calendar
 * Запустите этот файл один раз для активации пункта "Создать филиал" в меню "Еще"
 */

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

if (!CModule::IncludeModule('artmax.calendar')) {
    die('Модуль artmax.calendar не установлен');
}

try {
    // Регистрируем обработчик OnPageStart
    \Artmax\Calendar\EventHandlers::registerPageStartHandler();
    
    // Регистрируем обработчик OnBuildGlobalMenu
    \Artmax\Calendar\Events::registerMenuEventHandler();
    
    echo "События успешно зарегистрированы!<br>";
    echo "Пункт 'Создать филиал' теперь доступен в меню 'Еще'.<br>";
    echo "Обновите страницу для применения изменений.";
    
} catch (Exception $e) {
    echo "Ошибка регистрации событий: " . $e->getMessage();
    echo "<br>Детали: " . $e->getTraceAsString();
}

require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_after.php");
