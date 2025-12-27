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
        <div class="client-modal-form-wrapper">
            <?= bitrix_sessid_post() ?>
            <!-- Скрытое поле для ID контакта -->
            <input type="hidden" id="contact-id" value="">
            
            <!-- Поиск контакта -->
            <div id="contact-search-group">
                <div class="artmax-form-field">
                    <label for="contact-input" class="artmax-field-label">Контакт</label>
                    <div class="artmax-field-content">
                        <div class="input-with-icons">
                            <div class="input-icon left">👤</div>
                            <input type="text" id="contact-input" class="artmax-input" placeholder="Имя, email или номер телефона">
                            <div class="input-icon right">🔍</div>
                        </div>
                        <!-- Кнопка создания нового контакта -->
                        <div class="create-contact-section">
                            <button class="create-new-contact-btn" onclick="showCreateContactForm()">
                                <span class="plus-icon">+</span>
                                Создать новый контакт
                            </button>
                        </div>
                        <!-- Выпадающее окошко с результатами поиска -->
                        <div id="contact-search-dropdown" class="search-dropdown" style="display: none;">
                            <div class="search-suggestion">
                                <span class="search-text">«Поиск»</span>
                            </div>
                            <button class="create-new-contact-btn" onclick="showCreateContactForm()">
                                <span class="plus-icon">+</span>
                                создать новый контакт
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Кнопка "Назад" для возврата к поиску -->
            <div id="back-to-search" class="back-to-search" style="display: none;">
                <button class="back-btn" onclick="hideCreateContactForm()">
                    <span class="back-icon">←</span>
                    Назад к поиску
                </button>
            </div>
            
            <!-- Форма создания нового контакта -->
            <div id="create-contact-form" class="create-contact-form" style="display: none;">
                <div class="artmax-form-field">
                    <label for="new-contact-name" class="artmax-field-label">Имя <span class="artmax-required">*</span></label>
                    <div class="artmax-field-content">
                        <input type="text" id="new-contact-name" class="artmax-input" placeholder="Введите имя" required>
                        <div class="artmax-field-error" id="name-error" style="display: none;">
                            Поле "Имя" обязательно для заполнения
                        </div>
                    </div>
                </div>
                
                <div class="artmax-form-field">
                    <label for="new-contact-lastname" class="artmax-field-label">Фамилия</label>
                    <div class="artmax-field-content">
                        <input type="text" id="new-contact-lastname" class="artmax-input" placeholder="Введите фамилию">
                    </div>
                </div>
                
                <div class="artmax-form-field">
                    <label for="new-contact-phone" class="artmax-field-label">Телефон</label>
                    <div class="artmax-field-content">
                        <input type="tel" id="new-contact-phone" class="artmax-input" placeholder="+7 (999) 999-99-99">
                    </div>
                </div>
                
                <div class="artmax-form-field">
                    <label for="new-contact-email" class="artmax-field-label">E-mail</label>
                    <div class="artmax-field-content">
                        <input type="email" id="new-contact-email" class="artmax-input" placeholder="Введите email">
                        <div class="artmax-field-error" id="email-error" style="display: none;">
                            Введите корректный email
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Детали выбранного контакта -->
            <div class="artmax-form-field contact-details-field" style="display: none;">
                <label for="phone-input" class="artmax-field-label">Телефон</label>
                <div class="artmax-field-content">
                    <div class="input-with-icons">
                        <div class="input-icon left">🇷🇺</div>
                        <input type="tel" id="phone-input" class="artmax-input" placeholder="+7 (999) 999-99-99">
                    </div>
                </div>
            </div>
            
            <div class="artmax-form-field contact-details-field" style="display: none;">
                <label for="email-input" class="artmax-field-label">E-mail</label>
                <div class="artmax-field-content">
                    <div class="input-with-icons">
                        <div class="input-icon left">✉️</div>
                        <input type="email" id="email-input" class="artmax-input" placeholder="Адрес электронной почты">
                    </div>
                </div>
            </div>
            
            <div class="modal-instruction">
                Чтобы выбрать клиента из CRM, начните вводить имя, телефон или e-mail
            </div>
        </div>
    </div>
    
    <?php if ($arResult['IS_IFRAME']): ?>
    <!-- Кнопки для iframe режима -->
    <div class="webform-buttons calendar-form-buttons-fixed">
        <input type="button" class="ui-btn ui-btn-success" id="save-client-btn" value="Сохранить" onclick="saveClientData()" style="display: none;">
        <input type="button" class="ui-btn ui-btn-link" id="cancel-client-btn" value="Отмена" onclick="closeSidePanel()">
    </div>
    <?php endif; ?>
</div>

<script>
    // Передаём данные из PHP в JavaScript
    window.clientFormData = {
        eventId: <?= json_encode($arResult['EVENT_ID']) ?>
    };
</script>

