<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;

// Подключаем модуль календаря
if (!Loader::includeModule('artmax.calendar')) {
    ShowError('Модуль artmax.calendar не найден');
    return;
}

// Инициализируем результат
$arResult = [];

try {
    // Проверяем, открыта ли страница в iframe SidePanel
    $arResult['IS_IFRAME'] = isset($_REQUEST["IFRAME"]) && $_REQUEST["IFRAME"] === "Y";
    
    // Получаем ID события (если есть) для привязки сделки
    $arResult['EVENT_ID'] = (int)($this->arParams['EVENT_ID'] ?? $_REQUEST['EVENT_ID'] ?? 0);
    
} catch (Exception $e) {
    ShowError('Ошибка: ' . $e->getMessage());
    return;
}

$this->IncludeComponentTemplate();

