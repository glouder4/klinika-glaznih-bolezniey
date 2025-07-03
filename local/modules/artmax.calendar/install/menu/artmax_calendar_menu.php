<?php
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

$aMenu = [
    [
        "parent_menu" => "global_menu_services",
        "section" => "artmax_calendar",
        "sort" => 100,
        "text" => "Календарь ArtMax",
        "title" => "Управление календарем событий",
        "url" => "artmax_calendar_events.php?lang=" . LANGUAGE_ID,
        "icon" => "artmax_calendar_menu_icon",
        "page_icon" => "artmax_calendar_page_icon",
        "items_id" => "menu_artmax_calendar",
        "items" => [
            [
                "text" => "События календаря",
                "url" => "artmax_calendar_events.php?lang=" . LANGUAGE_ID,
                "title" => "Управление событиями календаря"
            ],
            [
                "text" => "Просмотр календаря",
                "url" => "/artmax-calendar.php",
                "title" => "Просмотр календаря событий"
            ],
        ],
    ],
];

return $aMenu; 