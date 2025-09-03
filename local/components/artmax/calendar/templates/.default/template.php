<?php if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

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

        <div class="header-center">
            <button class="btn btn-primary btn-create">
                СОЗДАТЬ РАСПИСАНИЕ
            </button> 
        </div>
        
        <div class="header-right">
            <button class="btn btn-secondary btn-timezone" id="timezone-settings-btn" title="Настройки филиала">
                ⚙️ Настройки филиала
            </button>
        </div>
    </div>

    <!-- Основной календарь -->
    <div class="calendar-main">
        <div class="calendar-toolbar">
            <div class="month-selector">
                <span class="current-month"><?= $currentDate->format('F, Y') ?></span>
            </div>
            <div class="calendar-controls">
                <span class="view-type">Месяц</span>
                <button class="btn-nav" onclick="previousMonth()">◀</button>
                <button class="btn-nav" onclick="nextMonth()">▶</button>
                <button class="btn-today" onclick="goToToday()">Сегодня</button>
                <button class="btn-refresh" onclick="refreshCalendarEvents()" title="Обновить события">🔄</button>
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
                        echo '<div class="day-number">' . $currentDateIterator->format('j') . '</div>';

                        // Если это не текущий месяц, добавляем месяц
                        if (!$isCurrentMonth && $currentDateIterator->format('j') <= 7) {
                            echo '<div class="month-label">' . $currentDateIterator->format('M') . '</div>';
                        }

                        // Отображаем события для этого дня
                        if (isset($arResult['EVENTS_BY_DATE'][$dateKey])) {
                            foreach ($arResult['EVENTS_BY_DATE'][$dateKey] as $event) {
                                $eventColor = $event['EVENT_COLOR'] ?? '#3498db';
                                $style = 'border-left: 4px solid ' . $eventColor . '; background-color: ' . $eventColor . '65;';
                                
                                // Логируем данные события перед извлечением времени
                                error_log("Отображение события ID=" . $event['ID'] . ", DATE_FROM=" . $event['DATE_FROM']);
                                
                                // Получаем время напрямую из БД, избегая проблем с часовыми поясами
                                $eventTime = extractTimeFromDate($event['DATE_FROM']);
                                
                                // Получаем время окончания
                                $eventEndTime = extractTimeFromDate($event['DATE_TO']);
                                
                                echo '<div class="calendar-event" data-event-id="' . $event['ID'] . '" style="' . $style . '" onclick="event.stopPropagation();">';
                                echo '<div class="event-content">';
                                echo '<div class="event-title">' . htmlspecialchars($event['TITLE']) . '</div>';
                                echo '<div class="event-time">' . $eventTime . ' – ' . $eventEndTime . '</div>';
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
                        <div class="radio-group">
                            <label class="radio-label">
                                <input type="radio" name="repeat-end" value="never" checked onchange="toggleEndFields()">
                                Никогда
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="repeat-end" value="after" onchange="toggleEndFields()">
                                После <input type="number" name="repeat-count" min="1" value="1" class="repeat-count-input"> повторений
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="repeat-end" value="date" onchange="toggleEndFields()">
                                Дата <input type="date" name="repeat-end-date" class="repeat-end-date-input">
                            </label>
                        </div>
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
                    <button type="button" class="btn btn-secondary" onclick="closeScheduleModal()">Отмена</button>
                    <button type="submit" class="btn btn-primary">Создать</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Модальное окно для настроек часового пояса -->
    <div id="timezoneModal" class="event-form-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Настройки филиала</h3>
                <button class="close-btn" id="close-timezone-modal">×</button>
            </div>
            <form id="timezone-form" novalidate>
                <?= bitrix_sessid_post() ?>
                <input type="hidden" name="branch_id" value="<?= $arResult['BRANCH']['ID'] ?>">
                
                <div class="form-group">
                    <label for="timezone-name">Часовой пояс *</label>
                    <select id="timezone-name" name="timezone_name" required>
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
                    <div class="error-message" style="display: none;">
                        <span class="error-icon">⚠️</span>
                        <span>Выберите часовой пояс.</span>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" id="cancel-timezone-modal">ОТМЕНА</button>
                    <button type="submit" class="btn btn-primary">СОХРАНИТЬ</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Боковое окно для просмотра деталей события -->
    <div id="eventSidePanel" class="event-side-panel" style="display: none;">
        <div class="side-panel-content">
            <div class="side-panel-header">
                <h3 id="sidePanelTitle">Детали записи</h3>
                <button class="close-side-panel" onclick="closeEventSidePanel()">×</button>
            </div>
            
            <div class="side-panel-body">
                <!-- Информация о клиенте -->
                <div class="client-section">
                    <div class="client-info">
                        <div class="client-icon">👤</div>
                        <div class="client-details">
                            <div class="client-name">Нет клиента</div>
                            <div class="client-placeholder">Добавьте информацию о клиенте</div>
                        </div>
                        <div class="client-actions">
                            <button class="action-btn add-contact-btn" title="Добавить">➕</button>
                        </div>
                    </div>
                    <div class="add-note-section">
                        <button class="add-note-btn">+ Добавить заметку к записи</button>
                    </div>
                </div>

                <!-- Карточки действий -->
                <div class="action-cards">
                    <div class="action-card">
                        <div class="card-icon">🤝</div>
                        <div class="card-content">
                            <div class="card-title">Сделка <span class="question-mark">?</span></div>
                            <div class="card-status">Не добавлена</div>
                        </div>
                        <button class="card-action-btn">+ Добавить</button>
                    </div>

                    <div class="action-card">
                        <div class="card-icon">
                            <div class="booking-actions-popup-item-icon --confirmed"><div class="ui-icon-set --check" style="--ui-icon-set__icon-color: #ffffff;"></div></div>
                        </div>
                        <div class="card-content">
                            <div class="card-title">Подтверждение <span class="question-mark">?</span></div>
                            <div class="card-status confirmed">Подтверждено</div>
                        </div>
                        <button class="card-action-btn">Выбрать ▼</button>
                    </div>

                    <div class="action-card">
                        <div class="card-icon">👥</div>
                        <div class="card-content">
                            <div class="card-title">Визит <span class="question-mark">?</span></div>
                            <div class="card-status">Клиент не добавлен</div>
                        </div>
                        <button class="card-action-btn">Выбрать ▼</button>
                    </div>
                </div>

                <!-- Кнопки действий -->
                <div class="side-panel-actions">
                    <button class="edit-event-btn" onclick="openEditEventModalFromSidePanel()">Редактировать</button>
                    <button class="delete-event-btn" onclick="deleteEventFromSidePanel()">🗑️ Удалить</button>
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
                <div class="form-group">
                    <label for="contact-input">Контакт</label>
                    <div class="input-with-icons">
                        <div class="input-icon left">👤</div>
                        <input type="text" id="contact-input" placeholder="Имя контакта">
                        <div class="input-icon right">🔍</div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="company-input">Компания</label>
                    <div class="input-with-icons">
                        <div class="input-icon left">📄</div>
                        <input type="text" id="company-input" placeholder="Название компании">
                        <div class="input-icon right">🔍</div>
                    </div>
                </div>
                <div class="modal-instruction">
                    Чтобы выбрать клиента из CRM, начните вводить имя, телефон, e-mail или название компании
                </div>
            </div>
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
    
    // Функция для переключения полей окончания повторения
    function toggleEndFields() {
        const repeatEnd = document.querySelector('input[name="repeat-end"]:checked').value;
        const repeatCountInput = document.querySelector('.repeat-count-input');
        const repeatEndDateInput = document.querySelector('.repeat-end-date-input');
        
        // Скрываем все поля
        repeatCountInput.style.display = 'none';
        repeatEndDateInput.style.display = 'none';
        
        // Показываем нужные поля
        if (repeatEnd === 'after') {
            repeatCountInput.style.display = 'inline-block';
        } else if (repeatEnd === 'date') {
            repeatEndDateInput.style.display = 'inline-block';
        }
    }
    
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
        const timezoneBtn = document.getElementById('timezone-settings-btn');
        if (timezoneBtn) {
            timezoneBtn.addEventListener('click', function() {
                openTimezoneModal();
            });
        }

        // Обработка кнопки закрытия модального окна настроек
        const closeTimezoneBtn = document.getElementById('close-timezone-modal');
        if (closeTimezoneBtn) {
            closeTimezoneBtn.addEventListener('click', function() {
                closeTimezoneModal();
            });
        }

        // Обработка кнопки "ОТМЕНА" в форме настроек
        const cancelTimezoneBtn = document.getElementById('cancel-timezone-modal');
        if (cancelTimezoneBtn) {
            cancelTimezoneBtn.addEventListener('click', function() {
                closeTimezoneModal();
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
                    repeat: formData.get('repeat') === 'on',
                    frequency: formData.get('frequency')
                };

                console.log('Данные расписания:', scheduleData);
                
                // Здесь можно добавить AJAX запрос для сохранения расписания
                // Пока просто показываем уведомление
                alert('Расписание успешно создано!');
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



    // Обработка отправки формы настроек филиала
    document.addEventListener('DOMContentLoaded', function() {
        const timezoneForm = document.getElementById('timezone-form');
        if (timezoneForm) {
            timezoneForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const timezoneData = {
                    action: 'update_timezone',
                    branch_id: formData.get('branch_id'),
                    timezone_name: formData.get('timezone_name')
                };

                // Отправляем AJAX запрос
                fetch('/local/components/artmax/calendar/ajax.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams(timezoneData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Настройки часового пояса успешно обновлены!');
                        closeTimezoneModal();
                        // Перезагружаем страницу для применения изменений
                        location.reload();
                    } else {
                        alert('Ошибка при обновлении настроек: ' + (data.message || 'Неизвестная ошибка'));
                    }
                })
                .catch(error => {
                    console.error('Ошибка:', error);
                    alert('Произошла ошибка при отправке запроса');
                });
            });
        }
    });

    // Закрытие модального окна часового пояса при клике вне его
    window.addEventListener('click', function(event) {
        const timezoneModal = document.getElementById('timezoneModal');
        if (event.target === timezoneModal) {
            closeTimezoneModal();
        }
    });
</script> 