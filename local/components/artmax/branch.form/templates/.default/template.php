<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array $arResult */
/** @var array $arParams */

// Подключаем CSS и JS
$templateFolder = $this->GetFolder();
$this->addExternalCss($templateFolder . '/style.css');
$this->addExternalJS($templateFolder . '/script.js');

// Подключаем стили Bitrix UI для кнопок
CJSCore::Init(['ui.buttons']);
?>

<div class="side-panel-content-container">
    <div class="artmax-event-form">
        <form id="add-branch-form" novalidate onsubmit="event.preventDefault(); return false;">
            <?= bitrix_sessid_post() ?>
            
            <!-- Название филиала - большое поле сверху -->
            <div class="artmax-event-title-section">
                <label for="branch-name" class="artmax-title-label">Название филиала</label>
                <input type="text" id="branch-name" name="name" class="artmax-title-input" placeholder="Введите название филиала" required>
                <div class="artmax-field-error" id="name-error" style="display: none;">
                    Заполните название филиала
                </div>
            </div>
            
            <!-- Блок настроек -->
            <div class="artmax-settings-block">
                <!-- Адрес -->
                <div class="artmax-form-field">
                    <label for="branch-address" class="artmax-field-label">Адрес</label>
                    <div class="artmax-field-content">
                        <input type="text" id="branch-address" name="address" class="artmax-input" placeholder="Введите адрес филиала">
                    </div>
                </div>
                
                <!-- Телефон -->
                <div class="artmax-form-field">
                    <label for="branch-phone" class="artmax-field-label">Телефон</label>
                    <div class="artmax-field-content">
                        <input type="tel" id="branch-phone" name="phone" class="artmax-input" placeholder="Введите телефон филиала">
                    </div>
                </div>
                
                <!-- Email -->
                <div class="artmax-form-field">
                    <label for="branch-email" class="artmax-field-label">Email</label>
                    <div class="artmax-field-content">
                        <input type="email" id="branch-email" name="email" class="artmax-input" placeholder="Введите email филиала">
                        <div class="artmax-field-error" id="email-error" style="display: none;">
                            Введите корректный email
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <?php if ($arResult['IS_IFRAME']): ?>
    <!-- Кнопки для iframe режима -->
    <div class="webform-buttons calendar-form-buttons-fixed">
        <input type="button" class="ui-btn ui-btn-success" id="save-branch-btn" value="Создать филиал" onclick="saveBranch()">
        <input type="button" class="ui-btn ui-btn-link" id="cancel-branch-btn" value="Отмена" onclick="closeSidePanel()">
    </div>
    <?php endif; ?>
</div>

