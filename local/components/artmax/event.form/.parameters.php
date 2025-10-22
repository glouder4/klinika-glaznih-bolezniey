<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

$arComponentParameters = [
    "GROUPS" => [
        "SETTINGS" => [
            "NAME" => "Настройки",
            "SORT" => 100,
        ],
    ],
    "PARAMETERS" => [
        "BRANCH_ID" => [
            "PARENT" => "SETTINGS",
            "NAME" => "ID филиала",
            "TYPE" => "STRING",
            "DEFAULT" => "1",
        ],
        "DATE" => [
            "PARENT" => "SETTINGS", 
            "NAME" => "Дата события",
            "TYPE" => "STRING",
            "DEFAULT" => "",
        ],
        "EMPLOYEE_ID" => [
            "PARENT" => "SETTINGS",
            "NAME" => "ID сотрудника по умолчанию",
            "TYPE" => "STRING",
            "DEFAULT" => "",
        ],
    ],
];
