<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;

$arParams["EVENT_ID"] = isset($arParams["EVENT_ID"]) ? (int)$arParams["EVENT_ID"] : 0;

$this->IncludeComponentTemplate();
?>

