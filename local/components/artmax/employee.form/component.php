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

// Получаем параметры
$arParams = array_merge([
    'EVENT_ID' => null,
], $arParams);

// Инициализируем результат
$arResult = [];

try {
    if (!$arParams['EVENT_ID']) {
        ShowError('Не указан ID события');
        return;
    }
    
    // Получаем данные о событии
    $calendarObj = new \Artmax\Calendar\Calendar();
    $event = $calendarObj->getEvent($arParams['EVENT_ID']);
    
    if (!$event) {
        ShowError('Событие не найдено');
        return;
    }
    
    $arResult['EVENT'] = $event;
    
    // Получаем список сотрудников филиала
    $employees = $calendarObj->getBranchEmployees($event['BRANCH_ID']);
    $arResult['EMPLOYEES'] = $employees;
    
    // Получаем текущего врача события (если есть)
    $arResult['CURRENT_EMPLOYEE_ID'] = $event['EMPLOYEE_ID'] ?? null;
    
    // Проверяем, открыта ли страница в iframe SidePanel
    $arResult['IS_IFRAME'] = isset($_REQUEST["IFRAME"]) && $_REQUEST["IFRAME"] === "Y";
    
} catch (Exception $e) {
    ShowError('Ошибка: ' . $e->getMessage());
    return;
}

$this->IncludeComponentTemplate();
