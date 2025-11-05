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
        <form id="branch-settings-form" novalidate>
            <?= bitrix_sessid_post() ?>
            <input type="hidden" name="branch_id" value="<?= $arResult['BRANCH_ID'] ?>">
            
            <!-- Название филиала - большое поле сверху -->
            <div class="artmax-event-title-section">
                <label for="branch-name" class="artmax-title-label">Название филиала</label>
                <input type="text" id="branch-name" name="branch_name" class="artmax-title-input" 
                       value="<?= htmlspecialchars($arResult['BRANCH']['NAME']) ?>" 
                       placeholder="Введите название филиала" required>
                <div class="artmax-field-error" id="name-error" style="display: none;">
                    Заполните название филиала
                </div>
            </div>
            
            <!-- Блок настроек -->
            <div class="artmax-settings-block">
                <!-- Часовой пояс -->
                <div class="artmax-form-field">
                    <label for="timezone-name" class="artmax-field-label">Часовой пояс</label>
                    <div class="artmax-field-content">
                        <select id="timezone-name" name="timezone_name" class="artmax-select timezone-select">
                            <option value="">Выберите часовой пояс</option>
                            <?php
                            $currentTimezoneName = null;
                            if ($arResult['CURRENT_TIMEZONE'] && isset($arResult['CURRENT_TIMEZONE']['TIMEZONE_NAME'])) {
                                $currentTimezoneName = $arResult['CURRENT_TIMEZONE']['TIMEZONE_NAME'];
                            }
                            
                            foreach ($arResult['AVAILABLE_TIMEZONES'] as $timezoneName => $timezoneLabel) {
                                $selected = ($currentTimezoneName === $timezoneName) ? 'selected' : '';
                                echo '<option value="' . htmlspecialchars($timezoneName) . '" ' . $selected . '>' . htmlspecialchars($timezoneLabel) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <!-- Сотрудники филиала -->
                <div class="artmax-form-field">
                    <label for="branch-employees" class="artmax-field-label">Сотрудники филиала</label>
                    <div class="artmax-field-content">
                        <div class="multiselect-container">
                            <div class="multiselect-input" id="multiselect-input">
                                <span class="placeholder">Выберите сотрудников</span>
                                <span class="dropdown-arrow">▼</span>
                            </div>
                            <div class="multiselect-dropdown" id="multiselect-dropdown" style="display: none;">
                                <div class="multiselect-search">
                                    <input type="text" id="employee-search" placeholder="Поиск сотрудников..." autocomplete="off">
                                </div>
                                <div class="multiselect-options" id="multiselect-options">
                                    <!-- Опции будут загружены через AJAX -->
                                </div>
                            </div>
                        </div>
                        <div class="selected-employees" id="selected-employees">
                            <!-- Выбранные сотрудники будут отображаться здесь -->
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <?php if ($arResult['IS_IFRAME']): ?>
    <!-- Кнопки для iframe режима -->
    <div class="webform-buttons calendar-form-buttons-fixed">
        <input type="button" class="ui-btn ui-btn-success" id="save-branch-settings-btn" value="Сохранить" onclick="saveBranchSettings()">
        <input type="button" class="ui-btn ui-btn-link" id="cancel-branch-settings-btn" value="Отмена" onclick="closeSidePanel()">
    </div>
    <?php endif; ?>
</div>

<script>
    // Передаём данные из PHP в JavaScript
    window.branchSettingsData = {
        branchId: <?= json_encode($arResult['BRANCH_ID']) ?>,
        branchName: <?= json_encode($arResult['BRANCH']['NAME']) ?>
    };
</script>

