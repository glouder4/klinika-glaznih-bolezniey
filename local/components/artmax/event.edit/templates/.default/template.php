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

// Извлекаем дату и время из события
$event = $arResult['EVENT'];
$eventDate = '';
$eventTime = '';
$eventDuration = 30;

if (!empty($event['DATE_FROM'])) {
    // Парсим дату из формата "2025-08-04 12:00:00" или "04.08.2025 12:00:00"
    $dateFrom = $event['DATE_FROM'];
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})\s+(\d{1,2}):(\d{1,2}):/', $dateFrom, $matches)) {
        $eventDate = sprintf('%04d-%02d-%02d', $matches[1], $matches[2], $matches[3]);
        $eventTime = sprintf('%02d:%02d', $matches[4], $matches[5]);
    } elseif (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})\s+(\d{1,2}):(\d{1,2}):/', $dateFrom, $matches)) {
        $eventDate = sprintf('%04d-%02d-%02d', $matches[3], $matches[2], $matches[1]);
        $eventTime = sprintf('%02d:%02d', $matches[4], $matches[5]);
    }
    
    // Рассчитываем длительность
    if (!empty($event['DATE_TO'])) {
        $dateFromObj = \DateTime::createFromFormat('Y-m-d H:i:s', preg_replace('/(\d{1,2})\.(\d{1,2})\.(\d{4})/', '$3-$2-$1', $event['DATE_FROM']));
        $dateToObj = \DateTime::createFromFormat('Y-m-d H:i:s', preg_replace('/(\d{1,2})\.(\d{1,2})\.(\d{4})/', '$3-$2-$1', $event['DATE_TO']));
        if ($dateFromObj && $dateToObj) {
            $diff = $dateToObj->diff($dateFromObj);
            $eventDuration = $diff->h * 60 + $diff->i;
        }
    }
}

$eventColor = $event['EVENT_COLOR'] ?? '#2fc6f6';
?>

<div class="side-panel-content-container">
    <div class="artmax-event-form">
        <form id="edit-event-form" novalidate>
            <?= bitrix_sessid_post() ?>
            <input type="hidden" name="event_id" id="edit-event-id" value="<?= htmlspecialchars($arResult['EVENT']['ID']) ?>">
            <input type="hidden" name="branch_id" value="<?= htmlspecialchars($arResult['BRANCH_ID']) ?>">
            
            <!-- Название события - большое поле сверху -->
            <div class="artmax-event-title-section">
                <label for="edit-event-title" class="artmax-title-label">Название события</label>
                <input type="text" id="edit-event-title" name="title" class="artmax-title-input" placeholder="Введите название события" value="<?= htmlspecialchars($event['TITLE'] ?? '') ?>" required>
                <div class="artmax-field-error" id="title-error" style="display: none;">
                    Заполните это поле
                </div>
            </div>
            
            <!-- Блок настроек -->
            <div class="artmax-settings-block">
            <!-- Описание -->
            <div class="artmax-form-field">
                <label for="edit-event-description" class="artmax-field-label">Описание</label>
                <div class="artmax-field-content">
                    <textarea id="edit-event-description" name="description" class="artmax-textarea" rows="2" placeholder="Дополнительная информация о событии"><?= htmlspecialchars($event['DESCRIPTION'] ?? '') ?></textarea>
                </div>
            </div>
        
        <!-- Ответственный сотрудник -->
        <div class="artmax-form-field">
            <label for="edit-event-employee" class="artmax-field-label">
                Ответственный сотрудник
                <span class="artmax-required">*</span>
            </label>
            <div class="artmax-field-content">
                <select id="edit-event-employee" name="employee_id" class="artmax-select" required>
                    <option value="">Выберите сотрудника</option>
                    <?php foreach ($arResult['EMPLOYEES'] as $employee): ?>
                        <option value="<?= $employee['ID'] ?>" <?= ($event['EMPLOYEE_ID'] == $employee['ID']) ? 'selected' : '' ?>>
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
            <label for="edit-event-date" class="artmax-field-label">
                Дата и время
                <span class="artmax-required">*</span>
            </label>
            <div class="artmax-field-content">
                <div class="artmax-field-half">
                    <input type="date" id="edit-event-date" name="date" class="artmax-input" value="<?= htmlspecialchars($eventDate) ?>" required>
                    <div class="artmax-field-error" id="date-error" style="display: none;">
                        Заполните это поле
                    </div>
                </div>
                <div class="artmax-field-half">
                    <select id="edit-event-time" name="time" class="artmax-select" required>
                        <option value="">Выберите время</option>
                        <?php
                        $times = ['08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '12:00', '12:30', '13:00', '13:30', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30', '17:00', '17:30', '18:00'];
                        foreach ($times as $time):
                        ?>
                            <option value="<?= $time ?>" <?= ($eventTime === $time) ? 'selected' : '' ?>><?= $time ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="artmax-field-error" id="time-error" style="display: none;">
                        Заполните это поле
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Длительность приема -->
        <div class="artmax-form-field">
            <label for="edit-event-duration" class="artmax-field-label">
                Длительность приема
                <span class="artmax-required">*</span>
            </label>
            <div class="artmax-field-content">
                <select id="edit-event-duration" name="duration" class="artmax-select" required>
                    <option value="">Выберите длительность</option>
                    <?php
                    $durations = [
                        '5' => '5 минут',
                        '10' => '10 минут',
                        '15' => '15 минут',
                        '30' => '30 минут',
                        '60' => '1 час',
                        '120' => '2 часа'
                    ];
                    foreach ($durations as $value => $label):
                    ?>
                        <option value="<?= $value ?>" <?= ($eventDuration == $value) ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="artmax-field-error" id="duration-error" style="display: none;">
                    Заполните это поле
                </div>
            </div>
        </div>
        
        <!-- Цвет события -->
        <div class="artmax-form-field">
            <label for="edit-event-color" class="artmax-field-label">Цвет события</label>
            <div class="artmax-field-content">
                <div class="artmax-color-picker">
                    <div class="artmax-color-presets">
                        <?php
                        $colorPresets = [
                            '#2fc6f6' => '#2fc6f6',
                            '#ff5752' => '#ff5752',
                            '#55d0a0' => '#55d0a0',
                            '#ffa726' => '#ffa726',
                            '#ab47bc' => '#ab47bc',
                            '#26a69a' => '#26a69a',
                            '#78909c' => '#78909c',
                            '#bdbdbd' => '#bdbdbd'
                        ];
                        foreach ($colorPresets as $color):
                            $isActive = ($eventColor === $color);
                        ?>
                            <button type="button" class="artmax-color-preset <?= $isActive ? 'active' : '' ?>" data-color="<?= $color ?>" style="background-color: <?= $color ?>;" onclick="selectEditPresetColor('<?= $color ?>')"></button>
                        <?php endforeach; ?>
                    </div>
                    <div class="artmax-custom-color">
                        <label for="edit-custom-color-input" class="artmax-custom-color-label">Свой цвет:</label>
                        <input type="color" id="edit-custom-color-input" name="custom-color" value="<?= htmlspecialchars($eventColor) ?>" onchange="selectEditCustomColor(this.value)">
                    </div>
                    <input type="hidden" id="edit-selected-color" name="event-color" value="<?= htmlspecialchars($eventColor) ?>">
                </div>
            </div>
            </div> <!-- Закрытие блока настроек -->
        </form>
    </div>
    
    <?php if ($arResult['IS_IFRAME']): ?>
    <!-- Кнопки для iframe режима -->
    <div class="webform-buttons calendar-form-buttons-fixed">
        <input type="button" class="ui-btn ui-btn-danger" id="delete-event-btn" value="Удалить" onclick="deleteEvent()">
        <input type="button" class="ui-btn ui-btn-success" id="save-event-btn" value="Сохранить" onclick="saveEditEvent()">
        <input type="button" class="ui-btn ui-btn-link" id="cancel-event-btn" value="Отмена" onclick="closeSidePanel()">
    </div>
    <?php endif; ?>
</div>

<script>
    // Передаём данные из PHP в JavaScript
    window.eventEditData = {
        eventId: <?= json_encode($arResult['EVENT']['ID']) ?>,
        event: <?= json_encode($arResult['EVENT']) ?>
    };
</script>

