<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array $arResult */

$templateFolder = $this->GetFolder();
$this->addExternalCss($templateFolder . '/style.css');
$this->addExternalJS($templateFolder . '/script.js');

CJSCore::Init(['ui.buttons']);

$fieldCodes = $arResult['FIELD_CODES'];
$deal = $arResult['DEAL'];
?>

<div class="side-panel-content-container">
    <div class="artmax-event-form">
        <form id="deal-details-form" data-deal-id="<?= (int)$arResult['DEAL_ID'] ?>" data-event-id="<?= (int)$arResult['EVENT_ID'] ?>" novalidate>
            <?= bitrix_sessid_post() ?>

            <div class="artmax-form-field">
                <label for="deal-title" class="artmax-field-label">Название сделки</label>
                <div class="artmax-field-content">
                    <input type="text" id="deal-title" class="artmax-input" value="<?= htmlspecialcharsbx($deal['TITLE'] ?? '') ?>" placeholder="Введите название сделки">
                </div>
            </div>

            <div class="artmax-form-field">
                <label for="deal-service" class="artmax-field-label">Услуга</label>
                <div class="artmax-field-content">
                    <select id="deal-service" class="artmax-select">
                        <option value="">Не выбрано</option>
                        <?php foreach ($arResult['ENUMS']['SERVICE'] as $enum): ?>
                            <option value="<?= (int)$enum['ID'] ?>" <?= ($fieldCodes['SERVICE'] && (int)$deal[$fieldCodes['SERVICE']] === (int)$enum['ID']) ? 'selected' : '' ?>>
                                <?= htmlspecialcharsbx($enum['VALUE']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="artmax-form-field">
                <label for="deal-source" class="artmax-field-label">Источник</label>
                <div class="artmax-field-content">
                    <select id="deal-source" class="artmax-select">
                        <option value="">Не указан</option>
                        <?php foreach ($arResult['ENUMS']['SOURCE'] as $enum): ?>
                            <option value="<?= (int)$enum['ID'] ?>" <?= ($fieldCodes['SOURCE'] && (int)$deal[$fieldCodes['SOURCE']] === (int)$enum['ID']) ? 'selected' : '' ?>>
                                <?= htmlspecialcharsbx($enum['VALUE']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="artmax-form-field">
                <label for="deal-branch" class="artmax-field-label">Филиал</label>
                <div class="artmax-field-content">
                    <select id="deal-branch" class="artmax-select">
                        <?php foreach ($arResult['ENUMS']['BRANCH'] as $enum): ?>
                            <option value="<?= (int)$enum['ID'] ?>" <?= ($fieldCodes['BRANCH'] && (int)$deal[$fieldCodes['BRANCH']] === (int)$enum['ID']) ? 'selected' : '' ?> <?= ($arResult['CURRENT_BRANCH_ENUM_ID'] && (int)$arResult['CURRENT_BRANCH_ENUM_ID'] === (int)$enum['ID'] && (!$fieldCodes['BRANCH'] || !$deal[$fieldCodes['BRANCH']])) ? 'selected' : '' ?>>
                                <?= htmlspecialcharsbx($enum['VALUE']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="artmax-form-field">
                <label for="deal-amount-value" class="artmax-field-label">Сумма</label>
                <div class="artmax-field-content amount-row">
                    <input type="number" step="0.01" id="deal-amount-value" class="artmax-input amount-input" value="<?= htmlspecialcharsbx($arResult['AMOUNT']['VALUE']) ?>" placeholder="0.00">
                    <select id="deal-amount-currency" class="artmax-select currency-select">
                        <?php foreach ($arResult['CURRENCIES'] as $code => $name): ?>
                            <option value="<?= htmlspecialcharsbx($code) ?>" <?= ($arResult['AMOUNT']['CURRENCY'] === $code) ? 'selected' : '' ?>>
                                <?= htmlspecialcharsbx($code) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
        
        <?php
        // Формируем URL для полной формы сделки
        $dealId = (int)$arResult['DEAL_ID'];
        $dealCategoryId = isset($deal['CATEGORY_ID']) ? (int)$deal['CATEGORY_ID'] : 0;
        $dealFullUrl = '/crm/deal/details/' . $dealId . '/';
        if ($dealCategoryId > 0) {
            $dealFullUrl = '/crm/deal/details/' . $dealId . '/' . $dealCategoryId . '/';
        }
        ?>
        <div class="deal-full-form-link" style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #e0e0e0; text-align: center;">
            <a href="<?= htmlspecialcharsbx($dealFullUrl) ?>" target="_blank" style="color: #2066b0; text-decoration: none; font-size: 14px;">
                Перейти к полной форме
            </a>
        </div>
    </div>

    <?php if ($arResult['IS_IFRAME']): ?>
        <div class="webform-buttons calendar-form-buttons-fixed">
            <input type="button" class="ui-btn ui-btn-success" id="deal-details-save" value="Сохранить">
            <input type="button" class="ui-btn ui-btn-link" id="deal-details-cancel" value="Отмена">
        </div>
    <?php endif; ?>
</div>

<script>
    window.dealDetailsData = <?= CUtil::PhpToJSObject([
        'dealId' => (int)$arResult['DEAL_ID'],
        'eventId' => (int)$arResult['EVENT_ID'],
        'fieldCodes' => $arResult['FIELD_CODES'],
        'currentBranchEnumId' => $arResult['CURRENT_BRANCH_ENUM_ID'] ? (int)$arResult['CURRENT_BRANCH_ENUM_ID'] : null,
        'targetBranchId' => $arResult['TARGET_BRANCH_ID'] ? (int)$arResult['TARGET_BRANCH_ID'] : null,
    ]) ?>;
</script>

