<?php if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

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

<div class="artmax-calendar">
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
                СОЗДАТЬ <span class="arrow-down">▼</span>
            </button>
        </div>
        
        <div class="header-right">
            <div class="search-container">
                <input type="text" class="search-input" placeholder="Фильтр + поиск">
                <span class="search-icon">🔍</span>
            </div>
            <button class="btn btn-secondary">КАЛЕНДАРИ</button>
            <button class="btn btn-icon">⚙️</button>
        </div>
    </div>

    <!-- Навигация по календарю -->
    <div class="calendar-navigation">
        <div class="nav-tabs">
            <button class="nav-tab">День</button>
            <button class="nav-tab">Неделя</button>
            <button class="nav-tab active">Месяц</button>
            <button class="nav-tab">Расписание</button>
            <button class="nav-tab">
                Приглашения
                <span class="badge">0</span>
            </button>
        </div>
        
        <div class="nav-controls">
            <button class="btn btn-sync">СИНХРОНИЗИРОВАТЬ КАЛЕНДАРЬ</button>
            <div class="toggle-container">
                <span>СВОБОДНЫЕ СЛОТЫ</span>
                <label class="toggle-switch">
                    <input type="checkbox">
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </div>
    </div>

    <!-- Основной календарь -->
    <div class="calendar-main">
        <div class="calendar-toolbar">
            <div class="month-selector">
                <span class="current-month"><?= $currentDate->format('F, Y') ?></span>
                <span class="arrow-down">▼</span>
            </div>
            <div class="calendar-controls">
                <span class="view-type">Месяц</span>
                <button class="btn-nav" onclick="previousMonth()">◀</button>
                <button class="btn-nav" onclick="nextMonth()">▶</button>
                <button class="btn-today" onclick="goToToday()">Сегодня</button>
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
                                echo '<div class="calendar-event" data-event-id="' . $event['ID'] . '">';
                                echo '<div class="event-dot"></div>';
                                echo '<span class="event-title">' . htmlspecialchars($event['TITLE']) . '</span>';
                                echo '<span class="event-time">' . date('H:i', strtotime($event['DATE_FROM'])) . '</span>';
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
                <form id="add-event-form">
                    <?= bitrix_sessid_post() ?>
                    <div class="form-group">
                        <label for="event-title">Название события *</label>
                        <input type="text" id="event-title" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="event-description">Описание</label>
                        <textarea id="event-description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="event-date-from">Дата и время начала *</label>
                        <input type="datetime-local" id="event-date-from" name="dateFrom" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="event-date-to">Дата и время окончания *</label>
                        <input type="datetime-local" id="event-date-to" name="dateTo" required>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeEventForm()">Отмена</button>
                        <button type="submit" class="btn btn-primary">Добавить событие</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
let currentYear = <?= $year ?>;
let currentMonth = <?= $month ?>;

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

function openEventForm(date) {
    const modal = document.getElementById('eventFormModal');
    if (modal) {
        // Устанавливаем выбранную дату
        const dateFromInput = document.getElementById('event-date-from');
        if (dateFromInput) {
            dateFromInput.value = date + 'T09:00';
        }
        
        const dateToInput = document.getElementById('event-date-to');
        if (dateToInput) {
            dateToInput.value = date + 'T10:00';
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

    // Обработка формы добавления события
    const form = document.getElementById('add-event-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            
            BX.ajax.runComponentAction('artmax:calendar', 'addEvent', {
                mode: 'class',
                data: {
                    title: formData.get('title'),
                    description: formData.get('description'),
                    dateFrom: formData.get('dateFrom'),
                    dateTo: formData.get('dateTo'),
                    branchId: <?= $arResult['BRANCH']['ID'] ?? 0 ?>
                }
            }).then(function(response) {
                if (response.data.success) {
                    alert('Событие добавлено');
                    closeEventForm();
                    form.reset();
                    location.reload();
                } else {
                    alert('Ошибка: ' + response.data.error);
                }
            }).catch(function(response) {
                alert('Ошибка: ' + response.errors[0].message);
            });
        });
    }
});

// Закрытие модального окна при клике вне его
window.addEventListener('click', function(event) {
    const modal = document.getElementById('eventFormModal');
    if (event.target === modal) {
        closeEventForm();
    }
});
</script> 