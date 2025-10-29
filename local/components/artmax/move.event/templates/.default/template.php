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
        <form id="move-event-form" novalidate>
            <?= bitrix_sessid_post() ?>
            <input type="hidden" name="event_id" value="<?= htmlspecialchars($arResult['EVENT']['ID']) ?>">
            
            <!-- Блок настроек -->
            <div class="artmax-settings-block">
                <!-- Филиал -->
                <div class="artmax-form-field">
                    <label for="move-event-branch" class="artmax-field-label">
                        Филиал
                        <span class="artmax-required">*</span>
                    </label>
                    <div class="artmax-field-content">
                        <select id="move-event-branch" name="branch_id" class="artmax-select" required>
                            <option value="">Выберите филиал</option>
                            <?php
                            foreach ($arResult['BRANCHES'] as $branch) {
                                $selected = ($arResult['EVENT']['BRANCH_ID'] == $branch['ID']) ? 'selected' : '';
                                echo '<option value="' . htmlspecialchars($branch['ID']) . '" ' . $selected . '>' . htmlspecialchars($branch['NAME']) . '</option>';
                            }
                            ?>
                        </select>
                        <div class="artmax-field-error" id="branch-error" style="display: none;">
                            Выберите филиал
                        </div>
                    </div>
                </div>
                
                <!-- Врач -->
                <div class="artmax-form-field">
                    <label for="move-event-employee" class="artmax-field-label">
                        Врач
                        <span class="artmax-required">*</span>
                    </label>
                    <div class="artmax-field-content">
                        <select id="move-event-employee" name="employee_id" class="artmax-select" required>
                            <option value="">Выберите врача</option>
                            <?php
                            foreach ($arResult['EMPLOYEES'] as $employee) {
                                $selected = ($arResult['EVENT']['EMPLOYEE_ID'] == $employee['ID']) ? 'selected' : '';
                                $employeeName = trim(($employee['NAME'] ?? '') . ' ' . ($employee['LAST_NAME'] ?? ''));
                                if (empty($employeeName)) {
                                    $employeeName = $employee['LOGIN'] ?? 'Сотрудник #' . $employee['ID'];
                                }
                                echo '<option value="' . htmlspecialchars($employee['ID']) . '" ' . $selected . '>' . htmlspecialchars($employeeName) . '</option>';
                            }
                            ?>
                        </select>
                        <div class="artmax-field-error" id="employee-error" style="display: none;">
                            Выберите врача
                        </div>
                    </div>
                </div>
                
                <!-- Дата -->
                <div class="artmax-form-field">
                    <label for="move-event-date" class="artmax-field-label">
                        Дата
                        <span class="artmax-required">*</span>
                    </label>
                    <div class="artmax-field-content">
                        <input type="date" id="move-event-date" name="date" class="artmax-input" 
                               value="<?= htmlspecialchars($arResult['EVENT_DATE']) ?>" required>
                        <div class="artmax-field-error" id="date-error" style="display: none;">
                            Выберите дату
                        </div>
                    </div>
                </div>
                
                <!-- Время -->
                <div class="artmax-form-field">
                    <label for="move-event-time" class="artmax-field-label">
                        Время
                        <span class="artmax-required">*</span>
                    </label>
                    <div class="artmax-field-content">
                        <select id="move-event-time" name="time" class="artmax-select" required>
                            <option value="">Выберите время</option>
                            <!-- Опции будут загружены через JavaScript -->
                        </select>
                        <div class="artmax-field-error" id="time-error" style="display: none;">
                            Выберите время
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <?php if ($arResult['IS_IFRAME']): ?>
    <!-- Кнопки для iframe режима -->
    <div class="webform-buttons calendar-form-buttons-fixed">
        <input type="button" class="ui-btn ui-btn-success" id="move-event-btn" value="Перенести" onclick="saveMoveEvent()">
        <input type="button" class="ui-btn ui-btn-link" id="cancel-move-event-btn" value="Отмена" onclick="closeSidePanel()">
    </div>
    <?php endif; ?>
</div>

<script>
    // Передаём данные из PHP в JavaScript
    window.moveEventData = {
        eventId: <?= json_encode($arResult['EVENT']['ID']) ?>,
        event: <?= json_encode($arResult['EVENT']) ?>,
        currentBranchId: <?= json_encode($arResult['EVENT']['BRANCH_ID']) ?>,
        currentEmployeeId: <?= json_encode($arResult['EVENT']['EMPLOYEE_ID']) ?>,
        eventDate: <?= json_encode($arResult['EVENT_DATE']) ?>,
        branches: <?= json_encode($arResult['BRANCHES']) ?>,
        employees: <?= json_encode($arResult['EMPLOYEES']) ?>
    };
</script>
