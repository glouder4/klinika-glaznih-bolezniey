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
    'BRANCH_ID' => 1,
    'DATE' => date('Y-m-d'),
    'EMPLOYEE_ID' => null,
], $arParams);

// Инициализируем результат
$arResult = [];

try {
    // Получаем данные о филиале
    $branchObj = new \Artmax\Calendar\Branch();
    $branch = $branchObj->getBranch($arParams['BRANCH_ID']);
    
    if (!$branch) {
        ShowError('Филиал не найден');
        return;
    }
    
    $arResult['BRANCH'] = $branch;
    
    // Получаем список сотрудников филиала через класс Calendar
    $calendarObj = new \Artmax\Calendar\Calendar();
    $employees = $calendarObj->getBranchEmployees($arParams['BRANCH_ID']);
    $arResult['EMPLOYEES'] = $employees;
    
    // Устанавливаем дату
    $arResult['DATE'] = $arParams['DATE'];
    
    // Проверяем, открыта ли страница в iframe SidePanel
    $arResult['IS_IFRAME'] = isset($_REQUEST["IFRAME"]) && $_REQUEST["IFRAME"] === "Y";
    
} catch (Exception $e) {
    ShowError('Ошибка: ' . $e->getMessage());
    return;
}

$this->IncludeComponentTemplate();

