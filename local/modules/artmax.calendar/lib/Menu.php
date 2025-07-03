<?php
namespace Artmax\Menu;

class Menu
{
    public static function addMenuItems(&$aGlobalMenu, &$aModuleMenu)
    {
        $aGlobalMenu['artmax_calendar'] = [
            'menu_id' => 'artmax_calendar',
            'text' => 'Календарь',
            'title' => 'Управление календарем',
            'sort' => 500,
            'items_id' => 'global_menu_artmax_calendar',
            'items' => [
                [
                    'text' => 'События календаря',
                    'url' => 'artmax_calendar_events.php?lang='.LANGUAGE_ID,
                    'more_url' => ['artmax_calendar_events.php'],
                    'title' => 'Управление событиями календаря',
                    'sort' => 100
                ],
                [
                    'text' => 'Настройки календаря',
                    'url' => 'artmax_calendar_settings.php?lang='.LANGUAGE_ID,
                    'more_url' => ['artmax_calendar_settings.php'],
                    'title' => 'Настройки модуля календаря',
                    'sort' => 200
                ]
            ]
        ];
    }
}