<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;

if (!Loader::includeModule('crm')) {
    ShowError('Модуль CRM не установлен');
    return;
}

if (!Loader::includeModule('artmax.calendar')) {
    ShowError('Модуль artmax.calendar не найден');
    return;
}

$dealId = (int)($this->arParams['DEAL_ID'] ?? $_REQUEST['DEAL_ID'] ?? 0);
$eventId = (int)($this->arParams['EVENT_ID'] ?? $_REQUEST['EVENT_ID'] ?? 0);

if ($dealId <= 0) {
    ShowError('ID сделки не указан');
    return;
}

$fieldCodes = [
    'SERVICE' => Option::get('artmax.calendar', 'deal_service_field', 'UF_CRM_CALENDAR_SERVICE'),
    'SOURCE' => Option::get('artmax.calendar', 'deal_source_field', 'UF_CRM_CALENDAR_SOURCE'),
    'AMOUNT' => Option::get('artmax.calendar', 'deal_amount_field', 'UF_CRM_CALENDAR_AMOUNT'),
    'BRANCH' => Option::get('artmax.calendar', 'deal_branch_field', 'UF_CRM_CALENDAR_BRANCH'),
];

$selectFields = array_filter(array_values($fieldCodes));
$select = array_merge(
    ['ID', 'TITLE', 'STAGE_ID', 'CATEGORY_ID', 'ASSIGNED_BY_ID', 'CONTACT_ID', 'COMPANY_ID'],
    $selectFields
);
$select = array_unique($select);

$deal = \CCrmDeal::GetListEx([], ['=ID' => $dealId], false, false, $select)->Fetch();

if (!$deal) {
    ShowError('Сделка не найдена');
    return;
}

$amountRaw = $fieldCodes['AMOUNT'] && isset($deal[$fieldCodes['AMOUNT']]) ? (string)$deal[$fieldCodes['AMOUNT']] : '';
$amountParts = explode('|', $amountRaw);
$amountData = [
    'VALUE' => isset($amountParts[0]) ? $amountParts[0] : '',
    'CURRENCY' => isset($amountParts[1]) && $amountParts[1] !== '' ? $amountParts[1] : 'RUB',
];

$enumValues = [];
foreach (['SERVICE', 'SOURCE', 'BRANCH'] as $enumKey) {
    $enumValues[$enumKey] = getUserFieldEnumValues($fieldCodes[$enumKey]);
}

$currencyList = class_exists('\Bitrix\Currency\CurrencyManager')
    ? \Bitrix\Currency\CurrencyManager::getCurrencyList()
    : ['RUB' => 'RUB'];

$arResult = [
    'IS_IFRAME' => isset($_REQUEST["IFRAME"]) && $_REQUEST["IFRAME"] === "Y",
    'DEAL_ID' => $dealId,
    'EVENT_ID' => $eventId,
    'DEAL' => $deal,
    'FIELD_CODES' => $fieldCodes,
    'ENUMS' => $enumValues,
    'AMOUNT' => $amountData,
    'CURRENCIES' => $currencyList,
];

$this->IncludeComponentTemplate();

/**
 * Получает список значений пользовательского поля типа список
 *
 * @param string $fieldName
 * @return array<int, array<string, mixed>>
 */
function getUserFieldEnumValues(string $fieldName): array
{
    if ($fieldName === '') {
        return [];
    }

    $values = [];
    $enum = new \CUserFieldEnum();
    $rsEnum = $enum->GetList(
        ['SORT' => 'ASC'],
        ['USER_FIELD_NAME' => $fieldName]
    );

    while ($item = $rsEnum->Fetch()) {
        $values[] = [
            'ID' => $item['ID'],
            'VALUE' => $item['VALUE'],
        ];
    }

    return $values;
}

