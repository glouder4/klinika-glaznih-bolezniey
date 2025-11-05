<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;

$arParams["EVENT_ID"] = isset($arParams["EVENT_ID"]) ? (int)$arParams["EVENT_ID"] : 0;

// Получаем записи журнала
$journal = new \Artmax\Calendar\Journal();
$arResult["JOURNAL_ENTRIES"] = [];
$arResult["EVENT_ID"] = $arParams["EVENT_ID"];

if ($arParams["EVENT_ID"] > 0) {
    $arResult["JOURNAL_ENTRIES"] = $journal->getEventJournal($arParams["EVENT_ID"]);
}

// Получаем информацию о событии для заголовка
if ($arParams["EVENT_ID"] > 0) {
    $calendar = new \Artmax\Calendar\Calendar();
    $arResult["EVENT"] = $calendar->getEvent($arParams["EVENT_ID"]);
}

$this->IncludeComponentTemplate();
?>

