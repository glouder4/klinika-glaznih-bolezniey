<?php if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

// Отладочная информация
echo '<!-- STATIC LOAD DEBUG: Total events = ' . count($arResult['EVENTS']) . ' -->';
echo '<!-- STATIC LOAD DEBUG: Events by date keys = ' . implode(', ', array_keys($arResult['EVENTS_BY_DATE'])) . ' -->';

// Передаем IS_ADMIN в JavaScript
?>
<script>
    window.IS_ADMIN = <?= $arResult['IS_ADMIN'] ? 'true' : 'false' ?>;
    window.CURRENT_USER_ID = <?= $arResult['CURRENT_USER_ID'] ?>;
</script>

<!-- ИСПРАВЛЕНИЕ: CSS стили для отображения заметок врачам (только просмотр) -->
<style>
<?php if (!$arResult['IS_ADMIN'] && $USER->IsAuthorized()): ?>
/* Врачи видят секцию заметок, но БЕЗ кнопок редактирования */
.add-note-section {
    display: block !important;
    visibility: visible !important;
}

.note-display {
    display: block !important;
}

/* ВАЖНО: Врачи НЕ видят кнопки добавления/редактирования заметок */
.add-note-btn,
.edit-note-btn {
    display: none !important;
    visibility: hidden !important;
}

.note-content {
    display: block !important;
}
<?php endif; ?>
</style>
<?php

/**
 * Конвертирует дату из российского формата (день.месяц.год) в стандартный (год-месяц-день)
 * @param string $dateString Дата в формате "04.08.2025 09:00:00"
 * @return string Дата в формате "2025-08-04 09:00:00"
 */
function convertRussianDateToStandard($dateString)
{
    // Проверяем, что строка не пустая
    if (empty($dateString)) {
        return $dateString;
    }

    // Если дата уже в стандартном формате, возвращаем как есть
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $dateString)) {
        return $dateString;
    }

    // Парсим российский формат: день.месяц.год час:минута:секунда
    if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})\s+(\d{1,2}):(\d{1,2}):(\d{1,2})$/', $dateString, $matches)) {
        $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        $year = $matches[3];
        $hour = str_pad($matches[4], 2, '0', STR_PAD_LEFT);
        $minute = str_pad($matches[5], 2, '0', STR_PAD_LEFT);
        $second = str_pad($matches[6], 2, '0', STR_PAD_LEFT);
        
        return "{$year}-{$month}-{$day} {$hour}:{$minute}:{$second}";
    }

    // Если формат не распознан, пытаемся использовать strtotime как fallback
    $timestamp = strtotime($dateString);
    if ($timestamp !== false) {
        return date('Y-m-d H:i:s', $timestamp);
    }

    // Если ничего не получилось, возвращаем исходную строку
    return $dateString;
}

/**
 * Переводит название месяца на русский язык
 * @param string $monthName Название месяца на английском
 * @return string Название месяца на русском
 */
function translateMonthToRussian($monthName)
{
    $months = [
        'January' => 'Январь',
        'February' => 'Февраль',
        'March' => 'Март',
        'April' => 'Апрель',
        'May' => 'Май',
        'June' => 'Июнь',
        'July' => 'Июль',
        'August' => 'Август',
        'September' => 'Сентябрь',
        'October' => 'Октябрь',
        'November' => 'Ноябрь',
        'December' => 'Декабрь'
    ];
    
    return $months[$monthName] ?? $monthName;
}

/**
 * Переводит сокращенное название месяца на русский язык
 * @param string $monthName Сокращенное название месяца на английском
 * @return string Сокращенное название месяца на русском
 */
function translateShortMonthToRussian($monthName)
{
    $months = [
        'Jan' => 'Янв',
        'Feb' => 'Фев',
        'Mar' => 'Мар',
        'Apr' => 'Апр',
        'May' => 'Май',
        'Jun' => 'Июн',
        'Jul' => 'Июл',
        'Aug' => 'Авг',
        'Sep' => 'Сен',
        'Oct' => 'Окт',
        'Nov' => 'Ноя',
        'Dec' => 'Дек'
    ];
    
    return $months[$monthName] ?? $monthName;
}

/**
 * Извлекает время из даты в формате "2025-08-04 09:00:00" без учета часового пояса
 * @param string $dateString Дата в формате "2025-08-04 09:00:00"
 * @return string Время в формате "09:00"
 */
function extractTimeFromDate($dateString)
{
    // Извлекаем время напрямую из строки, избегая проблем с часовыми поясами
    if (preg_match('/\s+(\d{2}):(\d{2}):(\d{2})$/', $dateString, $timeMatches)) {
        $result = $timeMatches[1] . ':' . $timeMatches[2];
        return $result;
    }
    
    // Если дата в ISO формате (с T), извлекаем время
    if (preg_match('/T(\d{2}):(\d{2}):(\d{2})/', $dateString, $timeMatches)) {
        $result = $timeMatches[1] . ':' . $timeMatches[2];
        return $result;
    }

    return '??:??';
}

// Получаем текущую дату или выбранную дату
$currentDate = isset($_GET['date']) ? new DateTime($_GET['date']) : new DateTime();
$year = $currentDate->format('Y');
$month = $currentDate->format('n');

// Получаем первый день месяца
$firstDay = new DateTime("$year-$month-01");
$lastDay = new DateTime("$year-$month-" . $firstDay->format('t'));

// Получаем день недели первого дня (1 = понедельник, 7 = воскресенье)
$firstDayOfWeek = $firstDay->format('N');

// Получаем количество дней в предыдущем месяце для заполнения начала
$prevMonth = clone $firstDay;
$prevMonth->modify('-1 month');
$daysInPrevMonth = $prevMonth->format('t');

// Начинаем с понедельника предыдущей недели
$startDate = clone $firstDay;
$startDate->modify('-' . ($firstDayOfWeek - 1) . ' days');

// Количество недель для отображения (максимум 6)
$totalDays = 42; // 6 недель * 7 дней
?>

<div class="artmax-calendar" data-branch-id="<?= $arResult['BRANCH']['ID'] ?>">
  
    <!-- Основной календарь -->
    <div class="calendar-main">

        <div class="calendar-grid">
            <!-- Заголовки дней недели -->
            <div class="calendar-weekdays">
                <div class="weekday">ПН</div>
                <div class="weekday">ВТ</div>
                <div class="weekday">СР</div>
                <div class="weekday">ЧТ</div>
                <div class="weekday">ПТ</div>
                <div class="weekday">СБ</div>
                <div class="weekday">ВС</div>
            </div>

            <!-- Ячейки календаря -->
            <div class="calendar-days">
                <?php
                $currentDateIterator = clone $startDate;

                for ($week = 0; $week < 6; $week++) {
                    echo '<div class="calendar-week">';

                    for ($day = 0; $day < 7; $day++) {
                        $isCurrentMonth = $currentDateIterator->format('n') == $month;
                        $isToday = $currentDateIterator->format('Y-m-d') == date('Y-m-d');
                        $dateKey = $currentDateIterator->format('Y-m-d');

                        $dayClass = 'calendar-day';
                        if (!$isCurrentMonth) $dayClass .= ' other-month';
                        if ($isToday) $dayClass .= ' today';

                        echo '<div class="' . $dayClass . '" data-date="' . $dateKey . '">';
                        
                        // Если это не текущий месяц, добавляем месяц в одну строку с номером дня
                        if (!$isCurrentMonth && $currentDateIterator->format('j') <= 7) {
                            echo '<div class="day-number">' . $currentDateIterator->format('j') . ' ' . translateShortMonthToRussian($currentDateIterator->format('M')) . '</div>';
                        } else {
                            echo '<div class="day-number">' . $currentDateIterator->format('j') . '</div>';
                        }

                        // Отображаем события для этого дня
                        if (isset($arResult['EVENTS_BY_DATE'][$dateKey])) {
                            // Отладочная информация
                            echo '<!-- STATIC LOAD: ' . count($arResult['EVENTS_BY_DATE'][$dateKey]) . ' events for ' . $dateKey . ' -->';
                            foreach ($arResult['EVENTS_BY_DATE'][$dateKey] as $event) {
                                $eventColor = $event['EVENT_COLOR'] ?? '#3498db';
                                $style = 'border-left: 4px solid ' . $eventColor . '; background-color: ' . $eventColor . '65;';
                                
                                // Логируем данные события перед извлечением времени
                                error_log("Отображение события ID=" . $event['ID'] . ", DATE_FROM=" . $event['DATE_FROM']);
                                
                                // Получаем время напрямую из БД, избегая проблем с часовыми поясами
                                $eventTime = extractTimeFromDate($event['DATE_FROM']);
                                
                                // Получаем время окончания
                                $eventEndTime = extractTimeFromDate($event['DATE_TO']);
                                
                                // Добавляем класс статуса
                                $statusClass = isset($event['STATUS']) ? 'status-' . $event['STATUS'] : 'status-active';
                                
                                // Добавляем класс для перенесенных записей
                                $timeChangedClass = (isset($event['TIME_IS_CHANGED']) && $event['TIME_IS_CHANGED'] == 1) ? ' time-changed' : '';
                                
                                echo '<div class="calendar-event ' . $statusClass . $timeChangedClass . '" data-event-id="' . $event['ID'] . '" style="' . $style . '" onclick="event.stopPropagation();">';
                                echo '<div class="event-content">';
                                
                                // Формируем заголовок: Название - Имя - Телефон
                                $eventTitle = htmlspecialchars($event['TITLE']);
                                if (!empty($event['CONTACT_NAME'])) {
                                    $eventTitle .= ' - ' . htmlspecialchars($event['CONTACT_NAME']);
                                }
                                if (!empty($event['CONTACT_PHONE'])) {
                                    $eventTitle .= ' - ' . htmlspecialchars($event['CONTACT_PHONE']);
                                }
                                
                                echo '<div class="event-title">' . $eventTitle . '</div>';
                                echo '<div class="event-time">';
                                echo '<span>';
                                echo $eventTime . ' – ' . $eventEndTime;
                                echo '</span>';
                                echo '<div class="event-icons">';
                                echo '<span class="event-icon contact-icon ' . ($event['CONTACT_ENTITY_ID'] ? 'active' : '') . '" title="Контакт">👤</span>';
                                echo '<span class="event-icon deal-icon ' . ($event['DEAL_ENTITY_ID'] ? 'active' : '') . '" title="Сделка">💼</span>';

                                // Логика для иконки подтверждения
                                $confirmationActive = '';
                                if (isset($event['CONFIRMATION_STATUS'])) {
                                    if ($event['CONFIRMATION_STATUS'] === 'confirmed') {
                                        $confirmationActive = 'active';
                                    } elseif ($event['CONFIRMATION_STATUS'] === 'not_confirmed') {
                                        $confirmationActive = 'inactive';
                                    }
                                }
                                echo '<span class="event-icon confirmation-icon ' . $confirmationActive . '" title="Подтверждение">✅</span>';
                                
                                // Логика для иконки визита
                                $visitActive = '';
                                if (isset($event['VISIT_STATUS'])) {
                                    if ($event['VISIT_STATUS'] === 'client_came') {
                                        $visitActive = 'active';
                                    } elseif ($event['VISIT_STATUS'] === 'client_did_not_come') {
                                        $visitActive = 'inactive';
                                    }
                                }
                                echo '<span class="event-icon visit-icon ' . $visitActive . '" title="Визит">🏥</span>';

                                echo '</div>';
                                echo '</div>';
                                echo '</div>';
                                echo '<div class="event-arrow" onclick="event.stopPropagation(); showEventSidePanel(' . $event['ID'] . ');">▼</div>';
                                echo '</div>';
                            }
                        }

                        echo '</div>';

                        $currentDateIterator->modify('+1 day');
                    }

                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>

    <!-- Форма добавления события -->
    <?php if ($arResult['SHOW_FORM'] && $arResult['CAN_ADD_EVENTS']): ?>
        <div class="event-form-modal" id="eventFormModal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Добавить событие</h3>
                    <button class="close-btn" onclick="closeEventForm()">×</button>
                </div>
                <form id="add-event-form" novalidate>
                    <?= bitrix_sessid_post() ?>
                    <div class="form-group" id="title-group">
                        <label for="event-title">Название события *</label>
                        <input type="text" id="event-title" name="title" required>
                        <div class="error-message" style="display: none;">
                            <span class="error-icon">⚠️</span>
                            <span>Заполните это поле.</span>
                        </div>
                    </div>
                    
                    <div class="form-group" id="description-group">
                        <label for="event-description">Описание</label>
                        <textarea id="event-description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group" id="employee-group">
                        <label for="event-employee">Ответственный сотрудник *</label>
                        <select id="event-employee" name="employee_id" required>
                            <option value="">Выберите сотрудника</option>
                            <!-- Опции будут загружены через JavaScript -->
                        </select>
                        <div class="error-message" style="display: none;">
                            <span class="error-icon">⚠️</span>
                            <span>Выберите ответственного сотрудника.</span>
                        </div>
                    </div>
                    
                    <div class="form-group" id="date-group">
                        <label for="event-date">ДАТА *</label>
                        <input type="date" id="event-date" name="date" required>
                        <div class="error-message" style="display: none;">
                            <span class="error-icon">⚠️</span>
                            <span>Заполните это поле.</span>
                        </div>
                    </div>
                    
                    <div class="form-group" id="time-group">
                        <label for="event-time">ВРЕМЯ *</label>
                        <select id="event-time" name="time" required>
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
                        <div class="error-message" style="display: none;">
                            <span class="error-icon">⚠️</span>
                            <span>Заполните это поле.</span>
                        </div>
                    </div>
                    
                    <div class="form-group" id="duration-group">
                        <label for="event-duration">Длительность приема *</label>
                        <select id="event-duration" name="duration" required>
                            <option value="">Выберите длительность</option>
                            <option value="5">5 минут</option>
                            <option value="10">10 минут</option>
                            <option value="15">15 минут</option>
                            <option value="30">30 минут</option>
                            <option value="60">1 час</option>
                            <option value="120">2 часа</option>
                        </select>
                        <div class="error-message" style="display: none;">
                            <span class="error-icon">⚠️</span>
                            <span>Заполните это поле.</span>
                        </div>
                    </div>
                    
                    <!-- Поле для выбора цвета события -->
                    <div class="form-group">
                        <label for="event-color">Цвет события</label>
                        <div class="color-picker-container">
                            <div class="color-presets">
                                <button type="button" class="color-preset" data-color="#3498db" style="background-color: #3498db;" onclick="selectPresetColor('#3498db')"></button>
                                <button type="button" class="color-preset" data-color="#e74c3c" style="background-color: #e74c3c;" onclick="selectPresetColor('#e74c3c')"></button>
                                <button type="button" class="color-preset" data-color="#2ecc71" style="background-color: #2ecc71;" onclick="selectPresetColor('#2ecc71')"></button>
                                <button type="button" class="color-preset" data-color="#f39c12" style="background-color: #f39c12;" onclick="selectPresetColor('#f39c12')"></button>
                                <button type="button" class="color-preset" data-color="#9b59b6" style="background-color: #9b59b6;" onclick="selectPresetColor('#9b59b6')"></button>
                                <button type="button" class="color-preset" data-color="#1abc9c" style="background-color: #1abc9c;" onclick="selectPresetColor('#1abc9c')"></button>
                                <button type="button" class="color-preset" data-color="#34495e" style="background-color: #34495e;" onclick="selectPresetColor('#34495e')"></button>
                                <button type="button" class="color-preset" data-color="#95a5a6" style="background-color: #95a5a6;" onclick="selectPresetColor('#95a5a6')"></button>
                            </div>
                            <div class="custom-color">
                                <label for="custom-color-input">Свой цвет:</label>
                                <input type="color" id="custom-color-input" name="custom-color" value="#3498db" onchange="selectCustomColor(this.value)">
                            </div>
                            <input type="hidden" id="selected-color" name="event-color" value="#3498db">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeEventForm()">ОТМЕНА</button>
                        <button type="submit" class="submit-btn" type="submit">ДОБАВИТЬ СОБЫТИЕ</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Боковое окно для просмотра деталей события -->
    <div id="eventSidePanel" class="event-side-panel" style="display: none;">
        <!-- Прелоадер -->
        <div class="side-panel-preloader" id="sidePanelPreloader">
            <div class="preloader-spinner"></div>
            <div class="preloader-text">Загрузка данных...</div>
        </div>
        
        <div class="side-panel-content">
            <div class="side-panel-header">
                <h3 id="sidePanelTitle">
                    <span class="title-text">Детали записи</span>
                    <span class="edit-icon" title="Кликните для редактирования названия">✏️</span>
                </h3>
                <button class="close-side-panel" onclick="closeEventSidePanel()">×</button>
            </div>
            
            <div class="side-panel-body">
                <!-- Информация о клиенте -->
                <div class="client-section" onclick="openContactDetails()">
                    <div class="client-info">
                        <div class="client-icon">
                            <div class="booking-actions-popup__item-client-icon">
                                <div class="ui-icon-set --person" style="--ui-icon-set__icon-size: 26px; --ui-icon-set__icon-color: var(--ui-color-palette-gray-20);"></div>
                            </div>
                        </div>
                        <div class="client-details">
                            <div class="client-name">Нет клиента</div>
                            <div class="client-placeholder">Добавьте информацию о клиенте</div>
                        </div>
                        <div class="client-actions">
                            <span data-element="booking-menu-deal-create-button" class="booking-actions-popup-plus-button">
                                <button class="ui-btn ui-btn-shadow ui-btn-xs ui-btn-light ui-btn-round deal-card-add-btn admin-only" title="Добавить" onclick="event.stopPropagation(); openClientModal();">
                                    <div class="ui-icon-set --plus-30" style=""></div>
                                </button>
                            </span>
                        </div>
                    </div>
                    <div class="add-note-section admin-only">
                        <button class="add-note-btn" id="add-note-btn" onclick="event.stopPropagation(); openNoteModal();">+ Добавить заметку к записи</button>
                        <div class="note-display" id="note-display" style="display: none;">
                            <div class="note-content">
                                <span class="note-text" id="note-text-display"></span>
                                <button class="edit-note-btn" onclick="event.stopPropagation(); editNote();" title="Редактировать заметку">✏️</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Карточки действий -->
                <div class="action-cards">

                    <div class="action-card" id="deal-card" onclick="openDealDetails()">
                        <div class="card-icon">
                            <div class="booking-actions-popup-item-icon">
                                <div class="ui-icon-set --deal" style=""></div>
                            </div>
                        </div>
                        <div class="card-content">
                            <div class="card-title">Сделка</div>
                            <div class="card-status" id="deal-status">Не добавлена</div>
                        </div>
                        <div class="card-actions" onclick="event.stopPropagation()">
                            <span data-element="booking-menu-deal-create-button" class="booking-actions-popup-plus-button">
                                <button class="ui-btn ui-btn-shadow ui-btn-xs ui-btn-light ui-btn-round deal-card-add-btn admin-only" onclick="createNewDeal()" title="Создать новую сделку">
                                    <div class="ui-icon-set --plus-30" style=""></div>
                                </button>
                            </span>
                            <button class="card-action-btn select-btn admin-only" onclick="openDealModal()">Выбрать</button>
                        </div>
                    </div>

                    <div class="action-card" id="employee-card" onclick="openEmployeeDetails()">
                        <div class="card-icon">
                            <div class="booking-actions-popup__item-client-icon">
                                <div class="ui-icon-set --person" style="--ui-icon-set__icon-size: 26px; --ui-icon-set__icon-color: var(--ui-color-palette-gray-20);"></div>
                            </div>
                        ️</div>
                        <div class="card-content">
                            <div class="card-title">Ответственный врач</div>
                            <div class="card-status" id="employee-status">Не назначен</div>
                        </div>
                        <div class="card-actions" onclick="event.stopPropagation()">
                            <span data-element="booking-menu-deal-create-button" class="booking-actions-popup-plus-button">
                                <button class="ui-btn ui-btn-shadow ui-btn-xs ui-btn-light ui-btn-round deal-card-add-btn admin-only" title="Назначить врача" onclick="event.stopPropagation(); openEmployeeModal();">
                                    <div class="ui-icon-set --plus-30" style=""></div>
                                </button>
                            </span>
                        </div>
                    </div>

                    <div class="action-card">
                        <div class="card-icon">
                            <div class="booking-actions-popup-item-icon">
                                <div class="ui-icon-set --check"></div>
                            </div>
                        </div>
                        <div class="card-content">
                            <div class="card-title">Подтверждение</div>
                            <div class="card-status" id="confirmation-status">Ожидается подтверждение</div>
                        </div>
                        <button class="card-action-btn admin-only" id="confirmation-select-btn" onclick="toggleConfirmationDropdown()">Выбрать ▼</button>
                        
                        <!-- Выпадающее меню подтверждения -->
                        <div class="confirmation-dropdown admin-only" id="confirmation-dropdown">
                            <div class="confirmation-dropdown-item" onclick="setConfirmationStatus('confirmed')">Подтверждено</div>
                            <div class="confirmation-dropdown-item" onclick="setConfirmationStatus('not_confirmed')">Не подтверждено</div>
                        </div>
                    </div>

                    <div class="action-card">
                        <div class="card-icon">
                            <div class="booking-actions-popup-item-icon">
                                <div class="ui-icon-set --customer-card"></div>
                            </div>
                        </div>
                        <div class="card-content">
                            <div class="card-title">Визит</div>
                            <div class="card-status" id="visit-status">Не указано</div>
                        </div>
                        <button class="card-action-btn admin-only" id="visit-select-btn" onclick="toggleVisitDropdown()">Выбрать ▼</button>
                        
                        <!-- Выпадающее меню визита -->
                        <div class="visit-dropdown admin-only" id="visit-dropdown">
                            <div class="visit-dropdown-item" onclick="setVisitStatus('not_specified')">Не указано</div>
                            <div class="visit-dropdown-item" onclick="setVisitStatus('client_came')">Клиент пришел</div>
                            <div class="visit-dropdown-item" onclick="setVisitStatus('client_did_not_come')">Клиент не пришел</div>
                        </div>
                    </div>
                </div>

                <!-- Кнопки действий -->
                <div class="side-panel-actions">
                    <button class="edit-event-btn" onclick="openEditEventModalFromSidePanel()">✏️ Редактировать</button>
                    <button class="move-event-btn" onclick="moveEventFromSidePanel()">📅 Перенести запись</button>
                    <button id="cancel-event-btn" class="cancel-event-btn" onclick="toggleEventStatusFromSidePanel()">❌ Отменить запись</button>
                    <button class="journal-btn" onclick="openJournalSidePanel()">📋 Журнал</button>
                    <button class="delete-event-btn" style="display: none;" onclick="deleteEventFromSidePanel()">🗑️ Удалить</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно для добавления заметки -->
    <div id="noteModal" class="note-modal" style="display: none;">
        <div class="note-modal-content">
            <div class="note-modal-header">
                <h3>Заметка</h3>
                <button class="close-note-modal" onclick="closeNoteModal()">×</button>
            </div>
            <div class="note-modal-body">
                <div class="form-group">
                    <textarea id="note-text" placeholder="Запишите важные данные, пожелания, нюансы" rows="6"></textarea>
                </div>
                <div class="note-modal-actions">
                    <button type="button" class="btn btn-primary" onclick="saveNote()">СОХРАНИТЬ</button>
                    <button type="button" class="btn btn-secondary" onclick="closeNoteModal()">ОТМЕНИТЬ</button>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- JavaScript код перенесен в отдельный файл script.js -->
