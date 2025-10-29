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
    
    // Получаем ID филиала из параметров
    $branchId = (int)($this->arParams['BRANCH_ID'] ?? $_REQUEST['BRANCH_ID'] ?? 0);
    
    if (!$branchId) {
        ShowError('ID филиала не указан');
        return;
    }
    
    // Получаем информацию о филиале
    $branchObj = new \Artmax\Calendar\Branch();
    $branch = $branchObj->getBranch($branchId);
    
    if (!$branch) {
        ShowError('Филиал не найден');
        return;
    }
    
    // Получаем текущий часовой пояс филиала
    $timezoneManager = new \Artmax\Calendar\TimezoneManager();
    $currentTimezone = $timezoneManager->getBranchTimezone($branchId);
    
    // Получаем доступные часовые пояса
    $availableTimezones = $timezoneManager->getAvailableTimezones();
    
    $arResult['BRANCH'] = $branch;
    $arResult['BRANCH_ID'] = $branchId;
    $arResult['CURRENT_TIMEZONE'] = $currentTimezone;
    $arResult['AVAILABLE_TIMEZONES'] = $availableTimezones;
    
} catch (Exception $e) {
    ShowError('Ошибка: ' . $e->getMessage());
    return;
}

$this->IncludeComponentTemplate();

