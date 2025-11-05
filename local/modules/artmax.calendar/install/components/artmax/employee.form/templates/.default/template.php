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
        <form id="employee-form" novalidate>
            <?= bitrix_sessid_post() ?>
            <input type="hidden" name="event_id" value="<?= htmlspecialchars($arResult['EVENT']['ID']) ?>">
            
            <!-- Блок настроек -->
            <div class="artmax-settings-block">
                <!-- Выбор врача -->
                <div class="artmax-form-field">
                    <label for="employee-select" class="artmax-field-label">
                        Выберите врача
                        <span class="artmax-required">*</span>
                    </label>
                    <div class="artmax-field-content">
                        <select id="employee-select" name="employee_id" class="artmax-select" required>
                            <option value="">Выберите врача</option>
                            <?php
                            foreach ($arResult['EMPLOYEES'] as $employee) {
                                $selected = ($arResult['CURRENT_EMPLOYEE_ID'] == $employee['ID']) ? 'selected' : '';
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
                
                <div class="artmax-form-description">
                    Выберите ответственного врача для данного события
                </div>
            </div>
        </form>
    </div>
    
    <?php if ($arResult['IS_IFRAME']): ?>
    <!-- Кнопки для iframe режима -->
    <div class="webform-buttons calendar-form-buttons-fixed">
        <input type="button" class="ui-btn ui-btn-success" id="save-employee-btn" value="Сохранить" onclick="saveEmployeeData()">
        <input type="button" class="ui-btn ui-btn-link" id="cancel-employee-btn" value="Отмена" onclick="closeSidePanel()">
    </div>
    <?php endif; ?>
</div>

<script>
    // Передаём данные из PHP в JavaScript
    window.employeeFormData = {
        eventId: <?= json_encode($arResult['EVENT']['ID']) ?>,
        currentEmployeeId: <?= json_encode($arResult['CURRENT_EMPLOYEE_ID']) ?>,
        employees: <?= json_encode($arResult['EMPLOYEES']) ?>
    };
</script>
