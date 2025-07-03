<?php
namespace Artmax\Calendar;

use Bitrix\Main\EventManager;
use Bitrix\Main\Application;

class EventHandlers
{
    public static function onPageStart()
    {
        // Инициализация модуля при загрузке страницы
        if (!\CModule::IncludeModule('artmax.calendar')) {
            return;
        }

        // Подключение CSS и JS файлов
        $asset = \Bitrix\Main\Page\Asset::getInstance();
        $asset->addCss('/bitrix/css/artmax.calendar/style.css');
        $asset->addJs('/bitrix/js/artmax.calendar/script.js');
    }

    public static function onBeforeEventAdd(&$arFields)
    {
        // Обработка перед добавлением события
        if (!empty($arFields['TITLE'])) {
            $arFields['TITLE'] = trim($arFields['TITLE']);
        }
    }

    public static function onAfterEventAdd($ID, $arFields)
    {
        // Обработка после добавления события
        if ($ID > 0) {
            // Логирование или дополнительные действия
            \CEventLog::Add([
                'SEVERITY' => 'INFO',
                'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_EVENT_ADD',
                'MODULE_ID' => 'artmax.calendar',
                'ITEM_ID' => $ID,
                'DESCRIPTION' => 'Добавлено новое событие: ' . $arFields['TITLE']
            ]);
        }
    }
} 