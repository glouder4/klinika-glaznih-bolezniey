<?php
/**
 * AJAX endpoint для модуля ArtMax Calendar
 * Этот файл копируется в корень сайта при установке модуля
 */

// Подключаем Bitrix
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

/**
 * Создание повторяющихся событий
 */
function createRecurringEvents($originalEventId, $frequency, $weekdays = [], $repeatEnd = 'never', $repeatCount = null, $repeatEndDate = null, $eventColor = '#3498db')
{
    try {
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "=== CREATE_RECURRING_EVENTS DEBUG ===\n", 
            FILE_APPEND | LOCK_EX);
        
        if (!CModule::IncludeModule('artmax.calendar')) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "CREATE_RECURRING_EVENTS: Module not included\n", 
                FILE_APPEND | LOCK_EX);
            return false;
        }
        
        $calendarObj = new \Artmax\Calendar\Calendar();
        $originalEvent = $calendarObj->getEvent($originalEventId);
        
        if (!$originalEvent) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "CREATE_RECURRING_EVENTS: Original event not found\n", 
                FILE_APPEND | LOCK_EX);
            return false;
        }
        
        $dateFrom = new \DateTime($originalEvent['DATE_FROM']);
        $dateTo = new \DateTime($originalEvent['DATE_TO']);
        $duration = $dateFrom->diff($dateTo);

        $eventsCreated = 0;
        $maxEvents = ($repeatEnd === 'after' && $repeatCount) ? $repeatCount : 100; // Максимум 100 событий
        $endDate = ($repeatEnd === 'date' && $repeatEndDate) ? new \DateTime($repeatEndDate) : null;

        if ($frequency === 'weekly' && !empty($weekdays)) {
            // Специальная обработка для еженедельного повторения с выбранными днями недели
            $currentDate = clone $dateFrom;
            $weekNumber = 0;
            
            while ($eventsCreated < $maxEvents) {
                // Находим понедельник текущей недели
                $dayOfWeek = $currentDate->format('N'); // 1 = понедельник, 7 = воскресенье
                $mondayOffset = $dayOfWeek - 1;
                $weekStart = clone $currentDate;
                $weekStart->sub(new \DateInterval('P' . $mondayOffset . 'D'));
                
                // Создаем события для каждого выбранного дня недели в текущей неделе
                foreach ($weekdays as $weekday) {
                    $eventDate = clone $weekStart;
                    $eventDate->add(new \DateInterval('P' . ($weekday - 1) . 'D'));
                    
                    // Проверяем, что дата события не раньше исходной даты
                    if ($eventDate >= $dateFrom) {
                        // Проверяем ограничение по дате
                        if ($endDate && $eventDate > $endDate) {
                            break 2; // Выходим из обоих циклов
                        }
                        
                        // Проверяем ограничение по количеству
                        if ($eventsCreated >= $maxEvents) {
                            break 2; // Выходим из обоих циклов
                        }
                        
                        $eventDateTo = clone $eventDate;
                        $eventDateTo->add($duration);
                        
                        // Проверяем доступность времени для повторяющегося события
                        if ($calendarObj->isTimeAvailable($eventDate->format('Y-m-d H:i:s'), $eventDateTo->format('Y-m-d H:i:s'), $originalEvent['USER_ID'])) {
                            // Создаем повторяющееся событие
                            $recurringEventId = $calendarObj->addEvent(
                                $originalEvent['TITLE'],
                                $originalEvent['DESCRIPTION'],
                                $eventDate->format('Y-m-d H:i:s'),
                                $eventDateTo->format('Y-m-d H:i:s'),
                                $originalEvent['USER_ID'],
                                1,
                                $eventColor
                            );

                            if ($recurringEventId) {
                                $eventsCreated++;
                            }
                        }
                        // Если время занято, просто пропускаем этот день
                    }
                }
                
                // Переходим к следующей неделе
                $currentDate->add(new \DateInterval('P7D'));
                $weekNumber++;
                
                // Защита от бесконечного цикла
                if ($weekNumber > 100) {
                    break;
                }
            }
        } else {
            // Обычная обработка для других типов повторений
            for ($i = 1; $i <= $maxEvents; $i++) {
                $newDateFrom = clone $dateFrom;
                $newDateTo = clone $dateTo;

                // Вычисляем следующую дату в зависимости от частоты
                switch ($frequency) {
                    case 'daily':
                        $newDateFrom->add(new \DateInterval('P' . $i . 'D'));
                        $newDateTo->add(new \DateInterval('P' . $i . 'D'));
                        break;
                    
                    case 'weekly':
                        $newDateFrom->add(new \DateInterval('P' . ($i * 7) . 'D'));
                        $newDateTo->add(new \DateInterval('P' . ($i * 7) . 'D'));
                        break;
                    
                    case 'monthly':
                        $newDateFrom->add(new \DateInterval('P' . $i . 'M'));
                        $newDateTo->add(new \DateInterval('P' . $i . 'M'));
                        break;
                }

                // Проверяем ограничение по дате
                if ($endDate && $newDateFrom > $endDate) {
                    break;
                }

                // Проверяем доступность времени для повторяющегося события
                if ($calendarObj->isTimeAvailable($newDateFrom->format('Y-m-d H:i:s'), $newDateTo->format('Y-m-d H:i:s'), $originalEvent['USER_ID'])) {
                    // Создаем повторяющееся событие
                    $recurringEventId = $calendarObj->addEvent(
                        $originalEvent['TITLE'],
                        $originalEvent['DESCRIPTION'],
                        $newDateFrom->format('Y-m-d H:i:s'),
                        $newDateTo->format('Y-m-d H:i:s'),
                        $originalEvent['USER_ID'],
                        1,
                        $eventColor
                    );

                    if ($recurringEventId) {
                        $eventsCreated++;
                    }
                }
                // Если время занято, просто пропускаем этот день
            }
        }

        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "CREATE_RECURRING_EVENTS: Total events created: {$eventsCreated}\n", 
            FILE_APPEND | LOCK_EX);
        
        return $eventsCreated;
    } catch (\Exception $e) {
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "CREATE_RECURRING_EVENTS: Exception: " . $e->getMessage() . "\n", 
            FILE_APPEND | LOCK_EX);
        return false;
    }
}

// Проверяем, что это AJAX запрос
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Только AJAX запросы']));
}

// Проверяем метод запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'error' => 'Только POST запросы']));
}

// Проверяем авторизацию пользователя
if (!$GLOBALS['USER'] || !$GLOBALS['USER']->IsAuthorized()) {
    http_response_code(401);
    die(json_encode(['success' => false, 'error' => 'Необходима авторизация']));
}

// Подключаем модуль
if (!CModule::IncludeModule('artmax.calendar')) {
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'Модуль artmax.calendar не установлен']));
}

// Получаем действие
$action = $_POST['action'] ?? '';

// Создаем объект календаря
try {
    $calendarObj = new \Artmax\Calendar\Calendar();
} catch (Exception $e) {
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => 'Ошибка инициализации календаря: ' . $e->getMessage()]));
}

// Обрабатываем действия
switch ($action) {
    case 'addEvent':
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $dateFrom = $_POST['dateFrom'] ?? '';
        $dateTo = $_POST['dateTo'] ?? '';
        $branchId = (int)($_POST['branchId'] ?? 1);
        
        if (empty($title) || empty($dateFrom) || empty($dateTo)) {
            http_response_code(400);
            die(json_encode(['success' => false, 'error' => 'Не все обязательные поля заполнены']));
        }
        
        $userId = $GLOBALS['USER']->GetID();
        
        // Проверяем доступность времени
        if (!$calendarObj->isTimeAvailable($dateFrom, $dateTo, $userId)) {
            die(json_encode(['success' => false, 'error' => 'Время уже занято']));
        }
        
        // Добавляем событие
        $eventId = $calendarObj->addEvent($title, $description, $dateFrom, $dateTo, $userId, $branchId);
        
        if ($eventId) {
            die(json_encode(['success' => true, 'eventId' => $eventId]));
        } else {
            die(json_encode(['success' => false, 'error' => 'Ошибка добавления события']));
        }
        break;
        
    case 'deleteEvent':
        $eventId = (int)($_POST['eventId'] ?? 0);
        
        if (!$eventId) {
            http_response_code(400);
            die(json_encode(['success' => false, 'error' => 'ID события не указан']));
        }
        
        $event = $calendarObj->getEvent($eventId);
        if (!$event) {
            die(json_encode(['success' => false, 'error' => 'Событие не найдено']));
        }
        
        // Проверяем права на удаление (только автор события)
        if ($event['USER_ID'] != $GLOBALS['USER']->GetID()) {
            http_response_code(403);
            die(json_encode(['success' => false, 'error' => 'Нет прав на удаление']));
        }
        
        $result = $calendarObj->deleteEvent($eventId);
        
        if ($result) {
            die(json_encode(['success' => true]));
        } else {
            die(json_encode(['success' => false, 'error' => 'Ошибка удаления события']));
        }
        break;
        
    case 'getEvents':
        $branchId = (int)($_POST['branchId'] ?? 1);
        $dateFrom = $_POST['dateFrom'] ?? null;
        $dateTo = $_POST['dateTo'] ?? null;
        
        try {
            $events = $calendarObj->getEventsByBranch($branchId, $dateFrom, $dateTo);
            die(json_encode(['success' => true, 'events' => $events]));
        } catch (Exception $e) {
            die(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }
        break;
        
    case 'addSchedule':
        // Логируем начало обработки
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "=== ADD_SCHEDULE START (install/ajax.php) ===\n", 
            FILE_APPEND | LOCK_EX);
        
        $title = $_POST['title'] ?? '';
        $date = $_POST['date'] ?? '';
        $time = $_POST['time'] ?? '';
        $repeat = $_POST['repeat'] === 'on' || $_POST['repeat'] === 'true';
        $frequency = $_POST['frequency'] ?? null;
        $weekdays = [];
        if (isset($_POST['weekdays'])) {
            if (is_array($_POST['weekdays'])) {
                $weekdays = array_map('intval', $_POST['weekdays']);
            } elseif (is_string($_POST['weekdays']) && !empty($_POST['weekdays'])) {
                // Если weekdays приходит как строка "1,2", разбиваем её на массив
                $weekdays = array_map('intval', explode(',', $_POST['weekdays']));
            }
        }
        $repeatEnd = $_POST['repeatEnd'] ?? 'never';
        $repeatCount = !empty($_POST['repeatCount']) ? (int)$_POST['repeatCount'] : null;
        $repeatEndDate = $_POST['repeatEndDate'] ?? null;
        if ($repeatEndDate === 'null' || $repeatEndDate === '') {
            $repeatEndDate = null;
        }
        $eventColor = $_POST['eventColor'] ?? '#3498db';
        
        // Логируем параметры
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "ADD_SCHEDULE: Parameters:\n", 
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "  - repeat: " . ($repeat ? 'true' : 'false') . "\n", 
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "  - frequency: {$frequency}\n", 
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "  - weekdays: " . json_encode($weekdays) . "\n", 
            FILE_APPEND | LOCK_EX);
        
        if (empty($title) || empty($date) || empty($time)) {
            http_response_code(400);
            die(json_encode(['success' => false, 'error' => 'Не все обязательные поля заполнены']));
        }
        
        $userId = $GLOBALS['USER']->GetID();
        
        // Формируем дату и время
        $dateTime = $date . ' ' . $time;
        $dateFrom = new \DateTime($dateTime);
        $dateTo = clone $dateFrom;
        $dateTo->add(new \DateInterval('PT1H')); // Добавляем 1 час по умолчанию
        
        // Проверяем доступность времени
        if (!$calendarObj->isTimeAvailable($dateFrom->format('Y-m-d H:i:s'), $dateTo->format('Y-m-d H:i:s'), $userId)) {
            die(json_encode(['success' => false, 'error' => 'Время уже занято']));
        }
        
        // Создаем событие
        $eventId = $calendarObj->addEvent($title, '', $dateFrom->format('Y-m-d H:i:s'), $dateTo->format('Y-m-d H:i:s'), $userId, 1, $eventColor);
        
        if ($eventId) {
            die(json_encode(['success' => true, 'eventId' => $eventId]));
        } else {
            die(json_encode(['success' => false, 'error' => 'Ошибка добавления расписания']));
        }
        break;
        
    default:
        http_response_code(400);
        die(json_encode(['success' => false, 'error' => 'Неизвестное действие']));
}

// Если дошли до сюда, что-то пошло не так
http_response_code(500);
die(json_encode(['success' => false, 'error' => 'Неожиданная ошибка']));
