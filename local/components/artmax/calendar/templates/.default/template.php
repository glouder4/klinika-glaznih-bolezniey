<?php if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

// Отладочная информация
echo '<!-- STATIC LOAD DEBUG: Total events = ' . count($arResult['EVENTS']) . ' -->';
echo '<!-- STATIC LOAD DEBUG: Events by date keys = ' . implode(', ', array_keys($arResult['EVENTS_BY_DATE'])) . ' -->';

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
    <!-- Заголовок календаря -->
    <div class="calendar-header">
        <div class="header-left">
            <h1 class="calendar-title">
                <span class="star-icon">★</span>
                Календарь
            </h1>
        </div>

        
        <div class="header-right">
            <button class="btn btn-primary btn-add-branch" onclick="openAddBranchModal()" title="Добавить филиал">
                ➕ Добавить филиал
            </button>
            <button class="btn btn-secondary btn-branch" id="branch-settings-btn" title="Настройки филиала">
                ⚙️ Настройки филиала
            </button>
        </div>
    </div>

    <!-- Основной календарь -->
    <div class="calendar-main">
        <div class="calendar-toolbar">
            <div class="month-selector">
                <span class="current-month"><?= translateMonthToRussian($currentDate->format('F')) . ', ' . $currentDate->format('Y') ?></span>
            </div>
            <div class="calendar-controls">
                <button class="btn btn-primary btn-create">
                    СОЗДАТЬ РАСПИСАНИЕ
                </button>
                <span class="view-type">Месяц</span>
                <button class="btn-nav" onclick="previousMonth()">◀</button>
                <button class="btn-nav" onclick="nextMonth()">▶</button>
                <button class="btn-today" onclick="goToToday()">Сегодня</button>
                <button class="btn-refresh" onclick="refreshCalendarEvents()" title="Обновить события">🔄</button>
                <button class="btn btn-danger btn-clear-all" onclick="clearAllEvents()" title="Удалить все события">
                    🗑️
                </button>
            </div>
        </div>

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
                                echo '<div class="event-title">' . htmlspecialchars($event['TITLE']) . '</div>';
                                echo '<div class="event-time">';
                                echo '<span>';
                                echo $eventTime . ' – ' . $eventEndTime;
                                echo '</span>';
                                echo '<div class="event-icons">';
                                echo '<span class="event-icon contact-icon ' . ($event['CONTACT_ENTITY_ID'] ? 'active' : '') . '" title="Контакт">👤</span>';
                                echo '<span class="event-icon deal-icon ' . ($event['DEAL_ENTITY_ID'] ? 'active' : '') . '" title="Сделка">💼</span>';
                                
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

    <!-- Модальное окно для переноса записи -->
    <div class="event-form-modal" id="moveEventModal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Перенести запись</h3>
                <button class="close-btn" onclick="closeMoveEventModal()">×</button>
            </div>
            <form id="move-event-form" novalidate onsubmit="handleMoveEventSubmit(event)">
                <?= bitrix_sessid_post() ?>
                <input type="hidden" id="move-event-id" name="eventId">
                
                <div class="form-group" id="move-branch-group">
                    <label for="move-event-branch">Филиал *</label>
                    <select id="move-event-branch" name="branch_id" required onchange="onMoveBranchChange()">
                        <option value="">Выберите филиал</option>
                        <!-- Опции будут загружены через JavaScript -->
                    </select>
                    <div class="error-message" style="display: none;">
                        <span class="error-icon">⚠️</span>
                        <span>Выберите филиал.</span>
                    </div>
                </div>
                
                <div class="form-group" id="move-employee-group">
                    <label for="move-event-employee">Врач *</label>
                    <select id="move-event-employee" name="employee_id" required onchange="onMoveEmployeeChange()">
                        <option value="">Выберите врача</option>
                        <!-- Опции будут загружены через JavaScript -->
                    </select>
                    <div class="error-message" style="display: none;">
                        <span class="error-icon">⚠️</span>
                        <span>Выберите врача.</span>
                    </div>
                </div>
                
                <div class="form-group" id="move-date-group">
                    <label for="move-event-date">Дата *</label>
                    <input type="date" id="move-event-date" name="date" required onchange="onMoveDateChange()">
                    <div class="error-message" style="display: none;">
                        <span class="error-icon">⚠️</span>
                        <span>Выберите дату.</span>
                    </div>
                </div>
                
                <div class="form-group" id="move-time-group">
                    <label for="move-event-time">Время *</label>
                    <select id="move-event-time" name="time" required>
                        <option value="">Выберите время</option>
                        <!-- Опции будут загружены через JavaScript -->
                    </select>
                    <div class="error-message" style="display: none;">
                        <span class="error-icon">⚠️</span>
                        <span>Выберите время.</span>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeMoveEventModal()">Отмена</button>
                    <button type="submit" class="btn btn-primary">Перенести</button>
                </div>
            </form>
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

    <!-- Модальное окно для редактирования события -->
    <div id="editEventModal" class="event-form-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Редактировать событие</h3>
                <button class="close-btn" onclick="closeEditEventModal()">×</button>
            </div>
            <form id="edit-event-form" novalidate>
                <?= bitrix_sessid_post() ?>
                <input type="hidden" id="edit-event-id" name="eventId">
                
                <div class="form-group" id="edit-title-group">
                    <label for="edit-event-title">Название события *</label>
                    <input type="text" id="edit-event-title" name="title" required>
                    <div class="error-message" style="display: none;">
                        <span class="error-icon">⚠️</span>
                        <span>Заполните это поле.</span>
                    </div>
                </div>
                
                <div class="form-group" id="edit-description-group">
                    <label for="edit-event-description">Описание</label>
                    <textarea id="edit-event-description" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-group" id="edit-employee-group">
                    <label for="edit-event-employee">Ответственный сотрудник *</label>
                    <select id="edit-event-employee" name="employee_id" required>
                        <option value="">Выберите сотрудника</option>
                        <!-- Опции будут загружены через JavaScript -->
                    </select>
                    <div class="error-message" style="display: none;">
                        <span class="error-icon">⚠️</span>
                        <span>Выберите ответственного сотрудника.</span>
                    </div>
                </div>
                
                <div class="form-group" id="edit-date-group">
                    <label for="edit-event-date">ДАТА *</label>
                    <input type="date" id="edit-event-date" name="date" required>
                    <div class="error-message" style="display: none;">
                        <span class="error-icon">⚠️</span>
                        <span>Заполните это поле.</span>
                    </div>
                </div>
                
                <div class="form-group" id="edit-time-group">
                    <label for="edit-event-time">ВРЕМЯ *</label>
                    <select id="edit-event-time" name="time" required>
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
                
                <div class="form-group" id="edit-duration-group">
                    <label for="edit-event-duration">Длительность приема *</label>
                    <select id="edit-event-duration" name="duration" required>
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
                    <label for="edit-event-color">Цвет события</label>
                    <div class="color-picker-container">
                        <div class="color-presets">
                            <button type="button" class="color-preset" data-color="#3498db" style="background-color: #3498db;" onclick="selectEditPresetColor('#3498db')"></button>
                            <button type="button" class="color-preset" data-color="#e74c3c" style="background-color: #e74c3c;" onclick="selectEditPresetColor('#e74c3c')"></button>
                            <button type="button" class="color-preset" data-color="#2ecc71" style="background-color: #2ecc71;" onclick="selectEditPresetColor('#2ecc71')"></button>
                            <button type="button" class="color-preset" data-color="#f39c12" style="background-color: #f39c12;" onclick="selectEditPresetColor('#f39c12')"></button>
                            <button type="button" class="color-preset" data-color="#9b59b6" style="background-color: #9b59b6;" onclick="selectEditPresetColor('#9b59b6')"></button>
                            <button type="button" class="color-preset" data-color="#1abc9c" style="background-color: #1abc9c;" onclick="selectEditPresetColor('#1abc9c')"></button>
                            <button type="button" class="color-preset" data-color="#34495e" style="background-color: #34495e;" onclick="selectEditPresetColor('#34495e')"></button>
                            <button type="button" class="color-preset" data-color="#95a5a6" style="background-color: #95a5a6;" onclick="selectEditPresetColor('#95a5a6')"></button>
                        </div>
                        <div class="custom-color">
                            <label for="edit-custom-color-input">Свой цвет:</label>
                            <input type="color" id="edit-custom-color-input" name="custom-color" value="#3498db" onchange="selectEditCustomColor(this.value)">
                        </div>
                        <input type="hidden" id="edit-selected-color" name="event-color" value="#3498db">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-danger" onclick="deleteEventAjax(document.getElementById('edit-event-form').getAttribute('data-event-id'))">УДАЛИТЬ</button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditEventModal()">ОТМЕНА</button>
                    <button type="submit" class="btn btn-primary">СОХРАНИТЬ</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Модальное окно для создания расписания -->
    <div id="scheduleModal" class="modal-overlay" style="display: none;">
        <div class="modal-content schedule-modal">
            <div class="modal-header">
                <h3>Создать расписание</h3>
                <button type="button" class="modal-close" onclick="closeScheduleModal()">&times;</button>
            </div>
            
            <form id="scheduleForm" class="schedule-form">
                <div class="form-group">
                    <label for="schedule-title">Название *</label>
                    <input type="text" id="schedule-title" name="title" required placeholder="Введите название расписания">
                </div>
                
                <div class="form-group">
                    <label for="schedule-employee">Ответственный сотрудник *</label>
                    <select id="schedule-employee" name="employee_id" required>
                        <option value="">Выберите сотрудника</option>
                        <!-- Опции будут загружены через JavaScript -->
                    </select>
                    <div class="error-message" style="display: none;">
                        <span class="error-icon">⚠️</span>
                        <span>Выберите ответственного сотрудника.</span>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="schedule-date">Дата *</label>
                        <input type="date" id="schedule-date" name="date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="schedule-time">Время *</label>
                        <input type="time" id="schedule-time" name="time" required>
                    </div>
                </div>
                
                <div class="form-group checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="schedule-repeat" name="repeat" onchange="toggleRepeatFields()">
                        <span class="checkmark"></span>
                        Повторяемое
                    </label>
                </div>
                
                <!-- Галочки для исключения выходных и праздников (скрыты) -->
                <div class="form-group checkbox-group" style="display: none;">
                    <label class="checkbox-label">
                        <input type="checkbox" id="exclude-weekends" name="exclude_weekends" value="false">
                        <span class="checkmark"></span>
                        Исключить выходные
                    </label>
                </div>
                
                <div class="form-group checkbox-group" style="display: none;">
                    <label class="checkbox-label">
                        <input type="checkbox" id="exclude-holidays" name="exclude_holidays" value="false">
                        <span class="checkmark"></span>
                        Исключить праздничные дни
                    </label>
                </div>
                
                <div id="repeat-fields" class="repeat-fields" style="display: none;">
                    <div class="form-group">
                        <label for="schedule-frequency">Повторяемость</label>
                        <select id="schedule-frequency" name="frequency" onchange="toggleWeeklyDays()">
                            <option value="daily">Каждый день</option>
                            <option value="weekly">Каждую неделю</option>
                            <option value="monthly">Каждый месяц</option>
                        </select>
                    </div>
                    
                    <!-- Дни недели для еженедельного повторения -->
                    <div id="weekly-days" class="weekly-days" style="display: none;">
                        <label>Дни недели</label>
                        <div class="weekday-checkboxes">
                            <label class="weekday-checkbox">
                                <input type="checkbox" name="weekdays[]" value="1">
                                <span>ПН</span>
                            </label>
                            <label class="weekday-checkbox">
                                <input type="checkbox" name="weekdays[]" value="2">
                                <span>ВТ</span>
                            </label>
                            <label class="weekday-checkbox">
                                <input type="checkbox" name="weekdays[]" value="3">
                                <span>СР</span>
                            </label>
                            <label class="weekday-checkbox">
                                <input type="checkbox" name="weekdays[]" value="4">
                                <span>ЧТ</span>
                            </label>
                            <label class="weekday-checkbox">
                                <input type="checkbox" name="weekdays[]" value="5">
                                <span>ПТ</span>
                            </label>
                            <label class="weekday-checkbox">
                                <input type="checkbox" name="weekdays[]" value="6">
                                <span>СБ</span>
                            </label>
                            <label class="weekday-checkbox">
                                <input type="checkbox" name="weekdays[]" value="7">
                                <span>ВС</span>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Поля для окончания повторения -->
                    <div class="form-group">
                        <label>Окончание</label>
                        <div class="radio-group" id="repeat-end-group">
                            <label class="radio-label">
                                <input type="radio" name="repeat-end" value="after" checked onclick="toggleEndFields()">
                                После <input type="number" name="repeat-count" id="repeat-count" min="1" value="1" class="repeat-count-input"> повторений
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="repeat-end" value="date" onclick="toggleEndFields()">
                                Дата <input type="date" name="repeat-end-date" id="repeat-end-date" class="repeat-end-date-input">
                            </label>
                            <div id="include-end-date-container" class="checkbox-inline" style="display: none;">
                                <label class="checkbox-label-small">
                                    <input type="checkbox" id="include-end-date" name="include-end-date" checked>
                                    <span class="checkmark-small"></span>
                                    Включая дату окончания
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Поле для выбора цвета события -->
                <div class="form-group">
                    <label for="event-color">Цвет события</label>
                    <div class="color-picker-container">
                        <div class="color-presets">
                            <button type="button" class="color-preset" data-color="#3498db" style="background-color: #3498db;" onclick="selectSchedulePresetColor('#3498db')"></button>
                            <button type="button" class="color-preset" data-color="#e74c3c" style="background-color: #e74c3c;" onclick="selectSchedulePresetColor('#e74c3c')"></button>
                            <button type="button" class="color-preset" data-color="#2ecc71" style="background-color: #2ecc71;" onclick="selectSchedulePresetColor('#2ecc71')"></button>
                            <button type="button" class="color-preset" data-color="#f39c12" style="background-color: #f39c12;" onclick="selectSchedulePresetColor('#f39c12')"></button>
                            <button type="button" class="color-preset" data-color="#9b59b6" style="background-color: #9b59b6;" onclick="selectSchedulePresetColor('#9b59b6')"></button>
                            <button type="button" class="color-preset" data-color="#1abc9c" style="background-color: #1abc9c;" onclick="selectSchedulePresetColor('#1abc9c')"></button>
                            <button type="button" class="color-preset" data-color="#34495e" style="background-color: #34495e;" onclick="selectSchedulePresetColor('#34495e')"></button>
                            <button type="button" class="color-preset" data-color="#95a5a6" style="background-color: #95a5a6;" onclick="selectSchedulePresetColor('#95a5a6')"></button>
                        </div>
                        <div class="custom-color">
                            <label for="custom-color-input">Свой цвет:</label>
                            <input type="color" id="custom-color-input" name="custom-color" value="#3498db" onchange="selectScheduleCustomColor(this.value)">
                        </div>
                        <input type="hidden" id="schedule-selected-color" name="event-color" value="#3498db">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeScheduleModal()">Отмена</button>
                    <button type="submit" class="btn btn-primary">Создать</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Модальное окно для настроек филиала -->
    <div id="branchModal" class="event-form-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Настройки филиала</h3>
                <button class="close-btn" id="close-branch-modal">×</button>
            </div>
            <form id="branch-form" novalidate>
                <?= bitrix_sessid_post() ?>
                <input type="hidden" name="branch_id" value="<?= $arResult['BRANCH']['ID'] ?>">
                
                <div class="form-group">
                    <label for="branch-name">Название филиала</label>
                    <input type="text" id="branch-name" name="branch_name" value="<?= htmlspecialchars($arResult['BRANCH']['NAME']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="timezone-name">Часовой пояс</label>
                    <select id="timezone-name" name="timezone_name" class="timezone-select">
                        <option value="">Выберите часовой пояс</option>
                        <?php
                        $timezoneManager = new \Artmax\Calendar\TimezoneManager();
                        $availableTimezones = $timezoneManager->getAvailableTimezones();
                        $currentTimezone = null;
                        
                        // Получаем текущие настройки часового пояса для филиала
                        if (isset($arResult['BRANCH']['ID'])) {
                            $currentTimezone = $timezoneManager->getBranchTimezone($arResult['BRANCH']['ID']);
                        }
                        
                        foreach ($availableTimezones as $timezoneName => $timezoneLabel) {
                            $selected = ($currentTimezone && $currentTimezone['TIMEZONE_NAME'] === $timezoneName) ? 'selected' : '';
                            echo '<option value="' . htmlspecialchars($timezoneName) . '" ' . $selected . '>' . htmlspecialchars($timezoneLabel) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="branch-employees">Сотрудники филиала</label>
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
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="cancel-branch-modal">ОТМЕНА</button>
                    <button type="submit" class="btn btn-primary">СОХРАНИТЬ</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Боковое окно для просмотра деталей события -->
    <div id="eventSidePanel" class="event-side-panel" style="display: none;">
        <!-- Прелоадер -->
        <div class="side-panel-preloader" id="sidePanelPreloader">
            <div class="preloader-spinner"></div>
            <div class="preloader-text">Загрузка данных...</div>
        </div>
        
        <div class="side-panel-content">
            <div class="side-panel-header">
                <h3 id="sidePanelTitle">Детали записи</h3>
                <button class="close-side-panel" onclick="closeEventSidePanel()">×</button>
            </div>
            
            <div class="side-panel-body">
                <!-- Информация о клиенте -->
                <div class="client-section" onclick="openContactDetails()">
                    <div class="client-info">
                        <div class="client-icon">👤</div>
                        <div class="client-details">
                            <div class="client-name">Нет клиента</div>
                            <div class="client-placeholder">Добавьте информацию о клиенте</div>
                        </div>
                        <div class="client-actions">
                            <button class="action-btn add-contact-btn" title="Добавить" onclick="event.stopPropagation(); openClientModal();">➕</button>
                        </div>
                    </div>
                    <div class="add-note-section">
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
                        <div class="card-icon">🤝</div>
                        <div class="card-content">
                            <div class="card-title">Сделка</div>
                            <div class="card-status" id="deal-status">Не добавлена</div>
                        </div>
                        <div class="card-actions" onclick="event.stopPropagation()">
                            <button class="card-action-btn add-btn" onclick="createNewDeal()" title="Создать новую сделку">+</button>
                            <button class="card-action-btn select-btn" onclick="openDealModal()">Выбрать</button>
                        </div>
                    </div>

                    <div class="action-card" id="employee-card" onclick="openEmployeeDetails()">
                        <div class="card-icon">👨‍⚕️</div>
                        <div class="card-content">
                            <div class="card-title">Ответственный врач</div>
                            <div class="card-status" id="employee-status">Не назначен</div>
                        </div>
                        <div class="card-actions" onclick="event.stopPropagation()">
                            <button class="card-action-btn add-btn" onclick="openEmployeeModal()" title="Назначить врача">+</button>
                        </div>
                    </div>

                    <div class="action-card">
                        <div class="card-icon">
                            <div class="booking-actions-popup-item-icon">✓</div>
                        </div>
                        <div class="card-content">
                            <div class="card-title">Подтверждение</div>
                            <div class="card-status" id="confirmation-status">Ожидается подтверждение</div>
                        </div>
                        <button class="card-action-btn" id="confirmation-select-btn" onclick="toggleConfirmationDropdown()">Выбрать ▼</button>
                        
                        <!-- Выпадающее меню подтверждения -->
                        <div class="confirmation-dropdown" id="confirmation-dropdown">
                            <div class="confirmation-dropdown-item" onclick="setConfirmationStatus('confirmed')">Подтверждено</div>
                            <div class="confirmation-dropdown-item" onclick="setConfirmationStatus('not_confirmed')">Не подтверждено</div>
                        </div>
                    </div>

                    <div class="action-card">
                        <div class="card-icon">🏥</div>
                        <div class="card-content">
                            <div class="card-title">Визит</div>
                            <div class="card-status" id="visit-status">Не указано</div>
                        </div>
                        <button class="card-action-btn" id="visit-select-btn" onclick="toggleVisitDropdown()">Выбрать ▼</button>
                        
                        <!-- Выпадающее меню визита -->
                        <div class="visit-dropdown" id="visit-dropdown">
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
                    <button class="delete-event-btn" style="display: none;" onclick="deleteEventFromSidePanel()">🗑️ Удалить</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно для выбора клиента -->
    <div id="clientModal" class="client-modal" style="display: none;">
        <div class="client-modal-content">
            <div class="client-modal-header">
                <h3>Добавить или выбрать клиента</h3>
                <button class="close-client-modal" onclick="closeClientModal()">×</button>
            </div>
            <div class="client-modal-body">
                <div class="client-modal-form-wrapper">
                    <!-- Скрытое поле для ID контакта -->
                    <input type="hidden" id="contact-id" value="">
                    
                    <div class="form-group" id="contact-search-group">
                        <label for="contact-input">Контакт</label>
                        <div class="input-with-icons">
                            <div class="input-icon left">👤</div>
                            <input type="text" id="contact-input" placeholder="Имя, email или номер телефона">
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
                    
                    <!-- Кнопка "Назад" для возврата к поиску -->
                    <div id="back-to-search" class="back-to-search" style="display: none;">
                        <button class="back-btn" onclick="hideCreateContactForm()">
                            <span class="back-icon">←</span>
                            Назад к поиску
                        </button>
                    </div>
                    
                    <!-- Форма создания нового контакта -->
                    <div id="create-contact-form" class="create-contact-form" style="display: none;">
                        <div class="form-group">
                            <label for="new-contact-name">Имя *</label>
                            <input type="text" id="new-contact-name" placeholder="Введите имя" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new-contact-lastname">Фамилия</label>
                            <input type="text" id="new-contact-lastname" placeholder="Введите фамилию">
                        </div>
                        
                        <div class="form-group">
                            <label for="new-contact-phone">Телефон</label>
                            <input type="tel" id="new-contact-phone" placeholder="Введите номер телефона">
                        </div>
                        
                        <div class="form-group">
                            <label for="new-contact-email">E-mail</label>
                            <input type="email" id="new-contact-email" placeholder="Введите email">
                        </div>
                        
                        <div class="create-contact-actions">
                            <button type="button" class="btn btn-primary" onclick="createContact()">Создать контакт</button>
                            <button type="button" class="btn btn-secondary" onclick="hideCreateContactForm()">Отмена</button>
                        </div>
                    </div>
                    
                    <div class="form-group contact-details-field" style="display: none;">
                        <label for="phone-input">Телефон</label>
                        <div class="input-with-icons">
                            <div class="input-icon left">🇷🇺</div>
                            <input type="tel" id="phone-input" placeholder="Номер телефона">
                        </div>
                    </div>
                    
                    <div class="form-group contact-details-field" style="display: none;">
                        <label for="email-input">E-mail</label>
                        <div class="input-with-icons">
                            <div class="input-icon left">✉️</div>
                            <input type="email" id="email-input" placeholder="Адрес электронной почты">
                        </div>
                    </div>
                    
                    <!--<div class="form-group contact-details-field" style="display: none;">
                        <label for="company-input">Компания</label>
                        <div class="input-with-icons">
                            <div class="input-icon left">🏢</div>
                            <input type="text" id="company-input" placeholder="Название компании">
                            <div class="input-icon right">🔍</div>
                        </div>
                    </div>-->
                </div>
                <div class="modal-instruction">
                    Чтобы выбрать клиента из CRM, начните вводить имя, телефон или e-mail
                </div>
            </div>
            <div class="client-modal-footer" style="display: none;">
                <button type="button" class="btn btn-secondary" onclick="closeClientModal()">ОТМЕНА</button>
                <button type="button" class="btn btn-primary" onclick="saveClientData()">СОХРАНИТЬ</button>
            </div>
        </div>
    </div>

    <!-- Модальное окно для выбора сделки -->
    <div id="dealModal" class="deal-modal" style="display: none;">
        <div class="deal-modal-content">
            <div class="deal-modal-header">
                <h3>Добавить или выбрать сделку</h3>
                <button class="close-deal-modal" onclick="closeDealModal()">×</button>
            </div>
            <div class="deal-modal-body">
                <div class="deal-modal-form-wrapper">
                    <!-- Скрытое поле для ID сделки -->
                    <input type="hidden" id="deal-id" value="">
                    
                    <div class="form-group">
                        <label for="deal-input">Сделка</label>
                        <div class="input-with-icons">
                            <div class="input-icon left">💼</div>
                            <input type="text" id="deal-input" placeholder="Название сделки">
                            <div class="input-icon right">🔍</div>
                        </div>
                        <!-- Выпадающее окошко с результатами поиска -->
                        <div id="deal-search-dropdown" class="search-dropdown" style="display: none;">
                            <div class="search-suggestion">
                                <span class="search-text">«Поиск»</span>
                            </div>
                            <button class="create-new-deal-btn">
                                <span class="plus-icon">+</span>
                                создать новую сделку
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-instruction">
                    Чтобы выбрать сделку из CRM, начните вводить название сделки
                </div>
            </div>
            <div class="deal-modal-footer" style="display: none;">
                <button type="button" class="btn btn-secondary" onclick="closeDealModal()">ОТМЕНА</button>
                <button type="button" class="btn btn-primary" onclick="saveDealData()">СОХРАНИТЬ</button>
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

    <!-- Модальное окно для выбора врача -->
    <div id="employeeModal" class="employee-modal" style="display: none;">
        <div class="employee-modal-content">
            <div class="employee-modal-header">
                <h3>Назначить ответственного врача</h3>
                <button class="close-employee-modal" onclick="closeEmployeeModal()">×</button>
            </div>
            <div class="employee-modal-body">
                <div class="form-group">
                    <label for="employee-select">Выберите врача</label>
                    <select id="employee-select" class="employee-select">
                        <option value="">Выберите врача</option>
                        <!-- Опции будут загружены через JavaScript -->
                    </select>
                </div>
                <div class="modal-instruction">
                    Выберите ответственного врача для данного события
                </div>
            </div>
            <div class="employee-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEmployeeModal()">ОТМЕНА</button>
                <button type="button" class="btn btn-primary" onclick="saveEmployee()">СОХРАНИТЬ</button>
            </div>
        </div>
    </div>

    <!-- Модальное окно для создания нового филиала -->
    <div id="addBranchModal" class="event-form-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Создать новый филиал</h3>
                <button class="close-btn" onclick="closeAddBranchModal()">×</button>
            </div>
            <form id="add-branch-form" novalidate>
                <?= bitrix_sessid_post() ?>
                
                <div class="form-group" id="branch-name-group">
                    <label for="branch-name">Название филиала *</label>
                    <input type="text" id="branch-name" name="name" required placeholder="Введите название филиала">
                    <div class="error-message" style="display: none;">
                        <span class="error-icon">⚠️</span>
                        <span>Заполните название филиала.</span>
                    </div>
                </div>
                
                <div class="form-group" id="branch-address-group">
                    <label for="branch-address">Адрес</label>
                    <input type="text" id="branch-address" name="address" placeholder="Введите адрес филиала">
                </div>
                
                <div class="form-group" id="branch-phone-group">
                    <label for="branch-phone">Телефон</label>
                    <input type="tel" id="branch-phone" name="phone" placeholder="Введите телефон филиала">
                </div>
                
                <div class="form-group" id="branch-email-group">
                    <label for="branch-email">Email</label>
                    <input type="email" id="branch-email" name="email" placeholder="Введите email филиала">
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeAddBranchModal()">ОТМЕНА</button>
                    <button type="submit" class="btn btn-primary">СОЗДАТЬ ФИЛИАЛ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let currentYear = <?= $year ?>;
    let currentMonth = <?= $month ?>;

    // Функции для работы с модальным окном настроек филиала
    function openTimezoneModal() {
        const modal = document.getElementById('timezoneModal');
        if (modal) {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
    }

    function closeTimezoneModal() {
        const modal = document.getElementById('timezoneModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    }



    function previousMonth() {
        currentMonth--;
        if (currentMonth < 1) {
            currentMonth = 12;
            currentYear--;
        }
        loadCalendar(currentYear, currentMonth);
    }

    function nextMonth() {
        currentMonth++;
        if (currentMonth > 12) {
            currentMonth = 1;
            currentYear++;
        }
        loadCalendar(currentYear, currentMonth);
    }

    function goToToday() {
        const today = new Date();
        currentYear = today.getFullYear();
        currentMonth = today.getMonth() + 1;
        loadCalendar(currentYear, currentMonth);
    }

    function loadCalendar(year, month) {
        const url = new URL(window.location);
        url.searchParams.set('date', `${year}-${month.toString().padStart(2, '0')}-01`);
        window.location.href = url.toString();
    }

    // Функция для переключения отображения дней недели
    function toggleWeeklyDays() {
        const frequency = document.getElementById('schedule-frequency').value;
        const weeklyDays = document.getElementById('weekly-days');
        
        if (frequency === 'weekly') {
            weeklyDays.style.display = 'block';
        } else {
            weeklyDays.style.display = 'none';
        }
    }
    
    // Функция toggleEndFields определена в script.js
    
    // Функция для выбора предустановленного цвета
    function selectPresetColor(color) {
        document.getElementById('selected-color').value = color;
        document.getElementById('custom-color-input').value = color;
        
        // Обновляем активный класс для пресетов
        document.querySelectorAll('.color-preset').forEach(preset => {
            preset.classList.remove('active');
        });
        event.target.classList.add('active');
    }
    
    // Функция для выбора кастомного цвета
    function selectCustomColor(color) {
        document.getElementById('selected-color').value = color;
        
        // Убираем активный класс со всех пресетов
        document.querySelectorAll('.color-preset').forEach(preset => {
            preset.classList.remove('active');
        });
    }

    function openEventForm(date) {
        const modal = document.getElementById('eventFormModal');
        if (modal) {
            // Устанавливаем выбранную дату
            const dateInput = document.getElementById('event-date');
            if (dateInput) {
                dateInput.value = date;
            }

            const timeSelect = document.getElementById('event-time');
            if (timeSelect) {
                timeSelect.value = '09:00';
            }

            const durationSelect = document.getElementById('event-duration');
            if (durationSelect) {
                durationSelect.value = '30';
            }

            modal.style.display = 'block';
        }
    }

    function closeEventForm() {
        const modal = document.getElementById('eventFormModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    function toggleRepeatFields() {
        const repeatCheckbox = document.getElementById('schedule-repeat');
        const repeatFields = document.getElementById('repeat-fields');
        
        if (repeatCheckbox.checked) {
            repeatFields.style.display = 'block';
        } else {
            repeatFields.style.display = 'none';
        }
    }

    function openScheduleModal() {
        const modal = document.getElementById('scheduleModal');
        if (modal) {
            // Устанавливаем текущую дату по умолчанию
            const today = new Date().toISOString().split('T')[0];
            const dateInput = document.getElementById('schedule-date');
            if (dateInput) {
                dateInput.value = today;
            }

            // Устанавливаем текущее время по умолчанию
            const timeInput = document.getElementById('schedule-time');
            if (timeInput) {
                const now = new Date();
                const hours = now.getHours().toString().padStart(2, '0');
                const minutes = now.getMinutes().toString().padStart(2, '0');
                timeInput.value = `${hours}:${minutes}`;
            }

            // Сбрасываем форму
            document.getElementById('scheduleForm').reset();
            document.getElementById('schedule-repeat').checked = false;
            document.getElementById('repeat-fields').style.display = 'none';

            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
    }

    function closeScheduleModal() {
        const modal = document.getElementById('scheduleModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    }

    // Обработка клика по ячейке календаря
    document.addEventListener('DOMContentLoaded', function() {
        const calendarDays = document.querySelectorAll('.calendar-day');
        calendarDays.forEach(day => {
            day.addEventListener('click', function() {
                const date = this.getAttribute('data-date');
                if (date) {
                    openEventForm(date);
                }
            });
        });

        // Обработка кнопки настроек филиала
        const branchBtn = document.getElementById('branch-settings-btn');
        if (branchBtn) {
            branchBtn.addEventListener('click', function() {
                openBranchModal();
            });
        }

        // Обработка кнопки закрытия модального окна настроек
        const closeBranchBtn = document.getElementById('close-branch-modal');
        if (closeBranchBtn) {
            closeBranchBtn.addEventListener('click', function() {
                closeBranchModal();
            });
        }

        // Обработка кнопки "ОТМЕНА" в форме настроек
        const cancelBranchBtn = document.getElementById('cancel-branch-modal');
        if (cancelBranchBtn) {
            cancelBranchBtn.addEventListener('click', function() {
                closeBranchModal();
            });
        }



        // Обработка формы добавления события уже настроена в script.js
    });

    // Закрытие модального окна при клике вне его
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('eventFormModal');
        if (event.target === modal) {
            closeEventForm();
        }
        
        const scheduleModal = document.getElementById('scheduleModal');
        if (event.target === scheduleModal) {
            closeScheduleModal();
        }
    });

    // Обработка отправки формы расписания
    document.addEventListener('DOMContentLoaded', function() {
        const scheduleForm = document.getElementById('scheduleForm');
        if (scheduleForm) {
            scheduleForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const scheduleData = {
                    title: formData.get('title'),
                    date: formData.get('date'),
                    time: formData.get('time'),
                    employee_id: formData.get('employee_id'),
                    repeat: formData.get('repeat') === 'on',
                    frequency: formData.get('frequency')
                };

                console.log('Данные расписания:', scheduleData);
                
                // Здесь можно добавить AJAX запрос для сохранения расписания
                // Пока просто показываем уведомление
                showNotification('Расписание успешно создано!', 'success');
                closeScheduleModal();
            });
        }
    });

    // Закрытие по Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeEventForm();
            closeScheduleModal();
            closeTimezoneModal();
        }
    });



</script>
