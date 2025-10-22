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
?>

<div class="artmax-event-form">
    <form id="add-event-form" novalidate>
        <?= bitrix_sessid_post() ?>
        <input type="hidden" name="branch_id" value="<?= htmlspecialchars($arResult['BRANCH']['ID']) ?>">
        
        <!-- Название события -->
        <div class="artmax-form-field">
            <label for="event-title" class="artmax-field-label">
                Название события
                <span class="artmax-required">*</span>
            </label>
            <div class="artmax-field-content">
                <input type="text" id="event-title" name="title" class="artmax-input" placeholder="Введите название события" required>
                <div class="artmax-field-error" id="title-error" style="display: none;">
                    Заполните это поле
                </div>
            </div>
        </div>
        
        <!-- Описание -->
        <div class="artmax-form-field">
            <label for="event-description" class="artmax-field-label">Описание</label>
            <div class="artmax-field-content">
                <textarea id="event-description" name="description" class="artmax-textarea" rows="2" placeholder="Дополнительная информация о событии"></textarea>
            </div>
        </div>
        
        <!-- Ответственный сотрудник -->
        <div class="artmax-form-field">
            <label for="event-employee" class="artmax-field-label">
                Ответственный сотрудник
                <span class="artmax-required">*</span>
            </label>
            <div class="artmax-field-content">
                <select id="event-employee" name="employee_id" class="artmax-select" required>
                    <option value="">Выберите сотрудника</option>
                    <?php foreach ($arResult['EMPLOYEES'] as $employee): ?>
                        <option value="<?= $employee['ID'] ?>" <?= ($arParams['EMPLOYEE_ID'] == $employee['ID']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($employee['NAME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="artmax-field-error" id="employee-error" style="display: none;">
                    Выберите ответственного сотрудника
                </div>
            </div>
        </div>
        
        <!-- Дата и время в одной строке -->
        <div class="artmax-form-row">
            <label class="artmax-field-label">
                Дата и время
                <span class="artmax-required">*</span>
            </label>
            <div class="artmax-field-content">
                <div class="artmax-field-half">
                    <input type="date" id="event-date" name="date" class="artmax-input" value="<?= htmlspecialchars($arResult['DATE']) ?>" required>
                    <div class="artmax-field-error" id="date-error" style="display: none;">
                        Заполните это поле
                    </div>
                </div>
                <div class="artmax-field-half">
                    <select id="event-time" name="time" class="artmax-select" required>
                        <option value="">Выберите время</option>
                        <option value="08:00">08:00</option>
                        <option value="08:30">08:30</option>
                        <option value="09:00">09:00</option>
                        <option value="09:30">09:30</option>
                        <option value="10:00">10:00</option>
                        <option value="10:30">10:30</option>
                        <option value="11:00">11:00</option>
                        <option value="11:30">11:30</option>
                        <option value="12:00">12:00</option>
                        <option value="12:30">12:30</option>
                        <option value="13:00">13:00</option>
                        <option value="13:30">13:30</option>
                        <option value="14:00">14:00</option>
                        <option value="14:30">14:30</option>
                        <option value="15:00">15:00</option>
                        <option value="15:30">15:30</option>
                        <option value="16:00">16:00</option>
                        <option value="16:30">16:30</option>
                        <option value="17:00">17:00</option>
                        <option value="17:30">17:30</option>
                        <option value="18:00">18:00</option>
                    </select>
                    <div class="artmax-field-error" id="time-error" style="display: none;">
                        Заполните это поле
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Длительность приема -->
        <div class="artmax-form-field">
            <label for="event-duration" class="artmax-field-label">
                Длительность приема
                <span class="artmax-required">*</span>
            </label>
            <div class="artmax-field-content">
                <select id="event-duration" name="duration" class="artmax-select" required>
                    <option value="">Выберите длительность</option>
                    <option value="5">5 минут</option>
                    <option value="10">10 минут</option>
                    <option value="15">15 минут</option>
                    <option value="30">30 минут</option>
                    <option value="60">1 час</option>
                    <option value="120">2 часа</option>
                </select>
                <div class="artmax-field-error" id="duration-error" style="display: none;">
                    Заполните это поле
                </div>
            </div>
        </div>
        
        <!-- Цвет события -->
        <div class="artmax-form-field">
            <label for="event-color" class="artmax-field-label">Цвет события</label>
            <div class="artmax-field-content">
                <div class="artmax-color-picker">
                    <div class="artmax-color-presets">
                        <button type="button" class="artmax-color-preset active" data-color="#2fc6f6" style="background-color: #2fc6f6;" onclick="selectPresetColor('#2fc6f6')"></button>
                        <button type="button" class="artmax-color-preset" data-color="#ff5752" style="background-color: #ff5752;" onclick="selectPresetColor('#ff5752')"></button>
                        <button type="button" class="artmax-color-preset" data-color="#55d0a0" style="background-color: #55d0a0;" onclick="selectPresetColor('#55d0a0')"></button>
                        <button type="button" class="artmax-color-preset" data-color="#ffa726" style="background-color: #ffa726;" onclick="selectPresetColor('#ffa726')"></button>
                        <button type="button" class="artmax-color-preset" data-color="#ab47bc" style="background-color: #ab47bc;" onclick="selectPresetColor('#ab47bc')"></button>
                        <button type="button" class="artmax-color-preset" data-color="#26a69a" style="background-color: #26a69a;" onclick="selectPresetColor('#26a69a')"></button>
                        <button type="button" class="artmax-color-preset" data-color="#78909c" style="background-color: #78909c;" onclick="selectPresetColor('#78909c')"></button>
                        <button type="button" class="artmax-color-preset" data-color="#bdbdbd" style="background-color: #bdbdbd;" onclick="selectPresetColor('#bdbdbd')"></button>
                    </div>
                    <div class="artmax-custom-color">
                        <label for="custom-color-input" class="artmax-custom-color-label">Свой цвет:</label>
                        <input type="color" id="custom-color-input" name="custom-color" value="#2fc6f6">
                    </div>
                    <input type="hidden" id="selected-color" name="event-color" value="#2fc6f6">
                </div>
            </div>
        </div>
        
        <?php if ($arResult['IS_IFRAME']): ?>
        <!-- Кнопки для iframe режима -->
        <div class="artmax-form-actions">
            <button type="button" id="save-event-btn" class="artmax-btn artmax-btn-primary" onclick="saveEvent()">
                Сохранить
            </button>
            <button type="button" id="cancel-event-btn" class="artmax-btn artmax-btn-secondary" onclick="closeSidePanel()">
                Отмена
            </button>
        </div>
        <?php endif; ?>
    </form>
</div>
