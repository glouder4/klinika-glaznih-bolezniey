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
        <form id="branch-settings-form" novalidate onsubmit="event.preventDefault(); saveBranchSettings(); return false;">
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
                <!-- Адрес -->
                <div class="artmax-form-field">
                    <label for="branch-address" class="artmax-field-label">Адрес</label>
                    <div class="artmax-field-content">
                        <input type="text" id="branch-address" name="address" class="artmax-input"
                               value="<?= htmlspecialchars($arResult['BRANCH']['ADDRESS'] ?? '') ?>"
                               placeholder="Введите адрес филиала">
                    </div>
                </div>

                <!-- Телефон -->
                <div class="artmax-form-field">
                    <label for="branch-phone" class="artmax-field-label">Телефон</label>
                    <div class="artmax-field-content">
                        <input type="tel" id="branch-phone" name="phone" class="artmax-input"
                               value="<?= htmlspecialchars($arResult['BRANCH']['PHONE'] ?? '') ?>"
                               placeholder="Введите телефон филиала">
                    </div>
                </div>

                <!-- Email -->
                <div class="artmax-form-field">
                    <label for="branch-email" class="artmax-field-label">Email</label>
                    <div class="artmax-field-content">
                        <input type="email" id="branch-email" name="email" class="artmax-input"
                               value="<?= htmlspecialchars($arResult['BRANCH']['EMAIL'] ?? '') ?>"
                               placeholder="Введите email филиала">
                        <div class="artmax-field-error" id="email-error" style="display: none;">
                            Введите корректный email
                        </div>
                    </div>
                </div>

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
                    <label for="branch-employees" class="artmax-field-label">
                        Сотрудники филиала
                        <?php if ($arResult['EMPLOYEES_GROUP_MODE'] === 'group'): ?>
                            <span style="font-weight: normal; font-size: 12px; color: #666;">
                                (из выбранной группы пользователей)
                            </span>
                        <?php endif; ?>
                    </label>
                    <div class="artmax-field-content">
                        <?php if ($arResult['EMPLOYEES_GROUP_MODE'] === 'group'): ?>
                            <div style="padding: 8px; background: #e3f2fd; border: 1px solid #bbdefb; border-radius: 4px; margin-bottom: 10px; font-size: 13px; color: #1976d2;">
                                <strong>Групповой режим:</strong> Вы можете выбрать сотрудников филиала из пользователей выбранной группы.
                                <br><em>Примечание: Сотрудники из предыдущих групп также доступны для управления.</em>
                            </div>
                        <?php endif; ?>

                        <div class="multiselect-container">
                            <div class="multiselect-input" id="multiselect-input">
                                <span class="placeholder">
                                    <?php echo $arResult['EMPLOYEES_GROUP_MODE'] === 'group' ? 'Выберите сотрудников из группы' : 'Выберите сотрудников'; ?>
                                </span>
                                <span class="dropdown-arrow">▼</span>
                            </div>
                            <div id="old-group-notice" style="display: none; padding: 6px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 3px; margin-top: 5px; font-size: 12px; color: #856404;">
                                Некоторые сотрудники из предыдущих групп также доступны для выбора.
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
        branchName: <?= json_encode($arResult['BRANCH']['NAME']) ?>,
        employeesGroupMode: <?= json_encode($arResult['EMPLOYEES_GROUP_MODE']) ?>
    };
</script>

