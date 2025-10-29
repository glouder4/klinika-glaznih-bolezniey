<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;
use Bitrix\Main\Application;

// Подключаем модуль календаря
if (!Loader::includeModule('artmax.calendar')) {
    ShowError('Модуль artmax.calendar не найден');
    return;
}

// Получаем параметры
$arParams = array_merge([
    'EVENT_ID' => 0,
], $arParams);

// Инициализируем результат
$arResult = [];

try {
    // Получаем ID события из параметров или запроса
    $eventId = (int)($arParams['EVENT_ID'] ?? $_REQUEST['EVENT_ID'] ?? 0);
    
    if ($eventId <= 0) {
        ShowError('ID события не указан');
        return;
    }
    
    // Получаем данные события
    $calendarObj = new \Artmax\Calendar\Calendar();
    $event = $calendarObj->getEvent($eventId);
    
    if (!$event) {
        ShowError('Событие не найдено');
        return;
    }
    
    // Получаем данные о филиале события
    $branchObj = new \Artmax\Calendar\Branch();
    $branchId = (int)($event['BRANCH_ID'] ?? 1);
    $branch = $branchObj->getBranch($branchId);
    
    if (!$branch) {
        ShowError('Филиал не найден');
        return;
    }
    
    $arResult['EVENT'] = $event;
    $arResult['BRANCH'] = $branch;
    $arResult['BRANCH_ID'] = $branchId;
    
    // Получаем список сотрудников филиала через класс Calendar
    $employees = $calendarObj->getBranchEmployees($branchId);
    $arResult['EMPLOYEES'] = $employees;
    
    // Проверяем, открыта ли страница в iframe SidePanel
    $arResult['IS_IFRAME'] = isset($_REQUEST["IFRAME"]) && $_REQUEST["IFRAME"] === "Y";
    
} catch (Exception $e) {
    ShowError('Ошибка: ' . $e->getMessage());
    return;
}

$this->IncludeComponentTemplate();

