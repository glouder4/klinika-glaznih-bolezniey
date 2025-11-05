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
        <div class="deal-modal-form-wrapper">
            <?= bitrix_sessid_post() ?>
            <!-- Скрытое поле для ID сделки -->
            <input type="hidden" id="deal-id" value="">
            
            <!-- Поиск сделки -->
            <div id="deal-search-group">
                <div class="artmax-form-field">
                    <label for="deal-input" class="artmax-field-label">Сделка</label>
                    <div class="artmax-field-content">
                        <div class="input-with-icons">
                            <div class="input-icon left">💼</div>
                            <input type="text" id="deal-input" class="artmax-input" placeholder="Название сделки">
                            <div class="input-icon right">🔍</div>
                        </div>
                        <!-- Выпадающее окошко с результатами поиска -->
                        <div id="deal-search-dropdown" class="search-dropdown" style="display: none;">
                            <div class="search-suggestion">
                                <span class="search-text">«Поиск»</span>
                            </div>
                            <button class="create-new-deal-btn" onclick="createNewDeal()">
                                <span class="plus-icon">+</span>
                                создать новую сделку
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-instruction">
                Чтобы выбрать сделку из CRM, начните вводить название сделки
            </div>
        </div>
    </div>
    
    <?php if ($arResult['IS_IFRAME']): ?>
    <!-- Кнопки для iframe режима -->
    <div class="webform-buttons calendar-form-buttons-fixed">
        <input type="button" class="ui-btn ui-btn-success" id="save-deal-btn" value="Сохранить" onclick="saveDealData()" style="display: none;">
        <input type="button" class="ui-btn ui-btn-link" id="cancel-deal-btn" value="Отмена" onclick="closeSidePanel()">
    </div>
    <?php endif; ?>
</div>

<script>
    // Передаём данные из PHP в JavaScript
    window.dealFormData = {
        eventId: <?= json_encode($arResult['EVENT_ID']) ?>
    };
</script>

