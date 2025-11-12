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
        <form id="schedule-form" novalidate onsubmit="event.preventDefault(); return false;">
            <?= bitrix_sessid_post() ?>
            <input type="hidden" name="branch_id" value="<?= htmlspecialchars($arResult['BRANCH']['ID']) ?>">
            
            <!-- Название расписания - большое поле сверху -->
            <div class="artmax-event-title-section">
                <label for="schedule-title" class="artmax-title-label">Название расписания</label>
                <input type="text" id="schedule-title" name="title" class="artmax-title-input" placeholder="Введите название расписания" required>
                <div class="artmax-field-error" id="title-error" style="display: none;">
                    Заполните это поле
                </div>
            </div>
            
            <!-- Блок настроек -->
            <div class="artmax-settings-block">
                <!-- Ответственный сотрудник -->
                <div class="artmax-form-field">
                    <label for="schedule-employee" class="artmax-field-label">
                        Ответственный сотрудник
                        <span class="artmax-required">*</span>
                    </label>
                    <div class="artmax-field-content">
                        <select id="schedule-employee" name="employee_id" class="artmax-select" required>
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
                            <input type="date" id="schedule-date" name="date" class="artmax-input" value="<?= htmlspecialchars($arResult['DATE']) ?>" required>
                            <div class="artmax-field-error" id="date-error" style="display: none;">
                                Заполните это поле
                            </div>
                        </div>
                        <div class="artmax-field-half">
                            <input type="time" id="schedule-time" name="time" class="artmax-input" required>
                            <div class="artmax-field-error" id="time-error" style="display: none;">
                                Заполните это поле
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Повторяемое расписание -->
                <div class="artmax-form-field">
                    <label class="artmax-field-label">Повторяемое</label>
                    <div class="artmax-field-content">
                        <label class="artmax-checkbox-label">
                            <input type="checkbox" id="schedule-repeat" name="repeat" class="artmax-checkbox" onchange="toggleRepeatFields()">
                            <span class="artmax-checkmark"></span>
                            <span class="artmax-checkbox-text">Включить повторение</span>
                        </label>
                    </div>
                </div>
                
                <!-- Поля повторения (скрыты по умолчанию) -->
                <div id="repeat-fields" class="repeat-fields" style="display: none;">
                    <!-- Повторяемость -->
                    <div class="artmax-form-field">
                        <label for="schedule-frequency" class="artmax-field-label">Повторяемость</label>
                        <div class="artmax-field-content">
                            <select id="schedule-frequency" name="frequency" class="artmax-select" onchange="toggleWeeklyDays()">
                                <option value="daily">Каждый день</option>
                                <option value="weekly">Каждую неделю</option>
                                <option value="monthly">Каждый месяц</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Дни недели для еженедельного повторения -->
                    <div id="weekly-days" class="artmax-form-field" style="display: none;">
                        <label class="artmax-field-label" for="weekday-checkboxes">Дни недели</label>
                        <div class="artmax-field-content">
                            <div class="weekday-checkboxes" id="weekday-checkboxes">
                                <label class="weekday-checkbox">
                                    <input type="checkbox" name="weekdays[]" value="1" class="artmax-checkbox">
                                    <span>ПН</span>
                                </label>
                                <label class="weekday-checkbox">
                                    <input type="checkbox" name="weekdays[]" value="2" class="artmax-checkbox">
                                    <span>ВТ</span>
                                </label>
                                <label class="weekday-checkbox">
                                    <input type="checkbox" name="weekdays[]" value="3" class="artmax-checkbox">
                                    <span>СР</span>
                                </label>
                                <label class="weekday-checkbox">
                                    <input type="checkbox" name="weekdays[]" value="4" class="artmax-checkbox">
                                    <span>ЧТ</span>
                                </label>
                                <label class="weekday-checkbox">
                                    <input type="checkbox" name="weekdays[]" value="5" class="artmax-checkbox">
                                    <span>ПТ</span>
                                </label>
                                <label class="weekday-checkbox">
                                    <input type="checkbox" name="weekdays[]" value="6" class="artmax-checkbox">
                                    <span>СБ</span>
                                </label>
                                <label class="weekday-checkbox">
                                    <input type="checkbox" name="weekdays[]" value="7" class="artmax-checkbox">
                                    <span>ВС</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Окончание повторения -->
                    <div class="artmax-form-field">
                        <label class="artmax-field-label">Окончание</label>
                        <div class="artmax-field-content">
                            <div class="radio-group" id="repeat-end-group">
                                <label class="radio-label">
                                    <input type="radio" name="repeat-end" value="after" checked onclick="toggleEndFields()">
                                    <span>После</span>
                                    <input type="number" name="repeat-count" id="repeat-count" min="1" value="1" class="repeat-count-input">
                                    <span>повторений</span>
                                </label>
                                <label class="radio-label">
                                    <input type="radio" name="repeat-end" value="date" onclick="toggleEndFields()">
                                    <span>Дата</span>
                                    <input type="date" name="repeat-end-date" id="repeat-end-date" class="repeat-end-date-input artmax-input" style="display: none;">
                                </label>
                                <div id="include-end-date-container" class="checkbox-inline" style="display: none;">
                                    <label class="checkbox-label-small">
                                        <input type="checkbox" id="include-end-date" name="include-end-date" checked class="artmax-checkbox">
                                        <span class="checkmark-small"></span>
                                        <span>Включая дату окончания</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Цвет события -->
                <div class="artmax-form-field">
                    <label for="event-color" class="artmax-field-label">Цвет события</label>
                    <div class="artmax-field-content">
                        <div class="artmax-color-picker">
                            <div class="artmax-color-presets">
                                <button type="button" class="artmax-color-preset active" data-color="#2fc6f6" style="background-color: #2fc6f6;" onclick="selectSchedulePresetColor('#2fc6f6')"></button>
                                <button type="button" class="artmax-color-preset" data-color="#ff5752" style="background-color: #ff5752;" onclick="selectSchedulePresetColor('#ff5752')"></button>
                                <button type="button" class="artmax-color-preset" data-color="#55d0a0" style="background-color: #55d0a0;" onclick="selectSchedulePresetColor('#55d0a0')"></button>
                                <button type="button" class="artmax-color-preset" data-color="#ffa726" style="background-color: #ffa726;" onclick="selectSchedulePresetColor('#ffa726')"></button>
                                <button type="button" class="artmax-color-preset" data-color="#ab47bc" style="background-color: #ab47bc;" onclick="selectSchedulePresetColor('#ab47bc')"></button>
                                <button type="button" class="artmax-color-preset" data-color="#26a69a" style="background-color: #26a69a;" onclick="selectSchedulePresetColor('#26a69a')"></button>
                                <button type="button" class="artmax-color-preset" data-color="#78909c" style="background-color: #78909c;" onclick="selectSchedulePresetColor('#78909c')"></button>
                                <button type="button" class="artmax-color-preset" data-color="#bdbdbd" style="background-color: #bdbdbd;" onclick="selectSchedulePresetColor('#bdbdbd')"></button>
                            </div>
                            <div class="artmax-custom-color">
                                <label for="custom-color-input" class="artmax-custom-color-label">Свой цвет:</label>
                                <input type="color" id="custom-color-input" name="custom-color" value="#2fc6f6" onchange="selectScheduleCustomColor(this.value)">
                            </div>
                            <input type="hidden" id="schedule-selected-color" name="event-color" value="#2fc6f6">
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <?php if ($arResult['IS_IFRAME']): ?>
    <!-- Кнопки для iframe режима -->
    <div class="webform-buttons calendar-form-buttons-fixed">
        <input type="button" class="ui-btn ui-btn-success" id="save-schedule-btn" value="Создать" onclick="saveSchedule()">
        <input type="button" class="ui-btn ui-btn-link" id="cancel-schedule-btn" value="Отмена" onclick="closeSidePanel()">
    </div>
    <?php endif; ?>
</div>

