<?php
/**
 * Страница настроек модуля календаря
 * Отображается в разделе "Настройки" -> "Настройки модулей"
 */

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");

Loc::loadMessages(__FILE__);

if (!Loader::includeModule('artmax.calendar')) {
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");
    die();
}

// Редирект на страницу настроек модуля
LocalRedirect('/bitrix/admin/artmax.calendar_artmax_calendar_settings.php?lang=' . LANGUAGE_ID);
