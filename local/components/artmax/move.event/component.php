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
    
    // Получаем список филиалов
    $branchObj = new \Artmax\Calendar\Branch();
    $branches = $branchObj->getBranches();
    $arResult['BRANCHES'] = $branches;
    
    // Получаем список сотрудников текущего филиала
    $employees = $calendarObj->getBranchEmployees($event['BRANCH_ID']);
    $arResult['EMPLOYEES'] = $employees;
    
    // Преобразуем дату события из формата "04.08.2025 09:00:00" в "2025-08-04"
    $eventDate = $event['DATE_FROM'];
    if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})/', $eventDate, $matches)) {
        $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        $year = $matches[3];
        $arResult['EVENT_DATE'] = "{$year}-{$month}-{$day}";
    } else {
        // Если дата в стандартном формате
        $arResult['EVENT_DATE'] = substr($eventDate, 0, 10);
    }
    
    // Проверяем, открыта ли страница в iframe SidePanel
    $arResult['IS_IFRAME'] = isset($_REQUEST["IFRAME"]) && $_REQUEST["IFRAME"] === "Y";
    
} catch (Exception $e) {
    ShowError('Ошибка: ' . $e->getMessage());
    return;
}

$this->IncludeComponentTemplate();
