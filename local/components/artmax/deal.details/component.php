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
// Получаем BRANCH_ID из параметров (может быть 0, если не передан)
$branchId = isset($this->arParams['BRANCH_ID']) ? (int)$this->arParams['BRANCH_ID'] : (isset($_REQUEST['BRANCH_ID']) ? (int)$_REQUEST['BRANCH_ID'] : 0);

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

// Определяем текущий филиал для автоматического заполнения
$currentBranchEnumId = null;

// Используем переданный BRANCH_ID (приоритет) или получаем из события
$targetBranchId = $branchId;
if (!$targetBranchId && $eventId > 0) {
    // Если BRANCH_ID не передан, получаем из события
    $calendarObj = new \Artmax\Calendar\Calendar();
    $event = $calendarObj->getEvent($eventId);
    if ($event && isset($event['BRANCH_ID']) && $event['BRANCH_ID'] > 0) {
        $targetBranchId = (int)$event['BRANCH_ID'];
    }
}

// Если есть ID филиала, ищем соответствующее enum значение
if ($targetBranchId > 0) {
    $branchFieldCode = $fieldCodes['BRANCH'];
    if ($branchFieldCode) {
        $field = \CUserTypeEntity::GetList(
            [],
            [
                'ENTITY_ID' => 'CRM_DEAL',
                'FIELD_NAME' => $branchFieldCode,
            ]
        )->Fetch();
        
        if ($field) {
            $enum = new \CUserFieldEnum();
            
            // Получаем данные филиала для поиска по имени (запасной вариант)
            $branchObj = new \Artmax\Calendar\Branch();
            $branch = $branchObj->getBranch($targetBranchId);
            $branchName = $branch ? $branch['NAME'] : '';
            
            // Пробуем найти по XML_ID = 'branch_' . $branchId
            $xmlId = 'branch_' . $targetBranchId;
            $rsEnum = $enum->GetList(
                [],
                [
                    'USER_FIELD_ID' => $field['ID'],
                    'XML_ID' => $xmlId
                ]
            );
            $enumItem = $rsEnum->Fetch();
            
            if ($enumItem) {
                $currentBranchEnumId = (int)$enumItem['ID'];
            } else {
                // Пробуем найти по числовому XML_ID (для старых записей)
                $rsEnum = $enum->GetList(
                    [],
                    [
                        'USER_FIELD_ID' => $field['ID'],
                        'XML_ID' => (string)$targetBranchId
                    ]
                );
                $enumItem = $rsEnum->Fetch();
                
                if ($enumItem) {
                    $currentBranchEnumId = (int)$enumItem['ID'];
                } elseif ($branchName) {
                    // Если не нашли по XML_ID, ищем по имени филиала (запасной вариант)
                    $rsEnum = $enum->GetList(
                        [],
                        [
                            'USER_FIELD_ID' => $field['ID']
                        ]
                    );
                    while ($item = $rsEnum->Fetch()) {
                        if (trim($item['VALUE']) === trim($branchName)) {
                            $currentBranchEnumId = (int)$item['ID'];
                            break;
                        }
                    }
                }
            }
            
            // Отладочное логирование
            if (defined('BX_DEBUG') && BX_DEBUG) {
                error_log("deal.details: targetBranchId=$targetBranchId, xmlId=$xmlId, branchName=$branchName, currentBranchEnumId=" . ($currentBranchEnumId ?? 'null'));
            }
        } else {
            // Отладочное логирование
            if (defined('BX_DEBUG') && BX_DEBUG) {
                error_log("deal.details: Поле $branchFieldCode не найдено");
            }
        }
    } else {
        // Отладочное логирование
        if (defined('BX_DEBUG') && BX_DEBUG) {
            error_log("deal.details: branchFieldCode не установлен");
        }
    }
} else {
    // Отладочное логирование
    if (defined('BX_DEBUG') && BX_DEBUG) {
        error_log("deal.details: targetBranchId не установлен (branchId=$branchId, eventId=$eventId)");
    }
}

// Отладочное логирование (можно убрать после отладки)
if (defined('BX_DEBUG') && BX_DEBUG) {
    error_log("deal.details: targetBranchId=$targetBranchId, currentBranchEnumId=" . ($currentBranchEnumId ?? 'null'));
}

$arResult = [
    'IS_IFRAME' => isset($_REQUEST["IFRAME"]) && $_REQUEST["IFRAME"] === "Y",
    'DEAL_ID' => $dealId,
    'EVENT_ID' => $eventId,
    'DEAL' => $deal,
    'FIELD_CODES' => $fieldCodes,
    'ENUMS' => $enumValues,
    'AMOUNT' => $amountData,
    'CURRENCIES' => $currencyList,
    'CURRENT_BRANCH_ENUM_ID' => $currentBranchEnumId, // ID enum значения для автоматического заполнения
    'TARGET_BRANCH_ID' => isset($targetBranchId) ? $targetBranchId : null, // ID филиала для отладки
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

