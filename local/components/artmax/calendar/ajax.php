<?php
/**
 * AJAX endpoint для модуля ArtMax Calendar
 * Этот файл копируется в корень сайта при установке модуля
 */

// Подключаем Bitrix
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

// Подключаем класс компонента
require_once($_SERVER['DOCUMENT_ROOT'].'/local/components/artmax/calendar/class.php');

/**
 * Конвертирует дату из российского формата в стандартный для SQL
 */
function convertRussianDateToStandard($dateString)
{
    if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})\s+(\d{1,2}):(\d{1,2}):(\d{1,2})$/', $dateString, $matches)) {
        $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
        $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        $year = $matches[3];
        $hour = str_pad($matches[4], 2, '0', STR_PAD_LEFT);
        $minute = str_pad($matches[5], 2, '0', STR_PAD_LEFT);
        $second = str_pad($matches[6], 2, '0', STR_PAD_LEFT);
        return "{$year}-{$month}-{$day} {$hour}:{$minute}:{$second}";
    }
    return $dateString;
}

/**
 * Получение следующего дня недели для еженедельного повторения
 */
function getNextWeekday($startDate, $weekdays, $iteration = 1)
{
    $currentDate = clone $startDate;
    
    // Для первой итерации ищем следующий день недели
    if ($iteration == 1) {
        for ($i = 1; $i <= 7; $i++) {
            $currentDate->add(new \DateInterval('P1D'));
            $currentWeekday = $currentDate->format('N'); // 1 (понедельник) - 7 (воскресенье)
            
            if (in_array($currentWeekday, $weekdays)) {
                return $currentDate;
            }
        }
    } else {
        // Для последующих итераций добавляем недели
        $currentDate->add(new \DateInterval('P' . (($iteration - 1) * 7) . 'D'));
        
        // Ищем первый подходящий день недели в этой неделе
        for ($i = 0; $i < 7; $i++) {
            $currentWeekday = $currentDate->format('N');
            if (in_array($currentWeekday, $weekdays)) {
                return $currentDate;
            }
            $currentDate->add(new \DateInterval('P1D'));
        }
    }
    
    return null;
}

// Проверяем, что это AJAX запрос (более мягкая проверка)
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Также проверяем по наличию action в POST данных
if (!$isAjax && !isset($_POST['action'])) {
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Только AJAX запросы или POST с action']));
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

// Получаем действие из POST или GET (для совместимости)
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Логируем входящий запрос
file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
    "=== INCOMING AJAX REQUEST ===\n", 
    FILE_APPEND | LOCK_EX);
file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
    "Action: {$action}\n", 
    FILE_APPEND | LOCK_EX);
file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
    "POST data: " . json_encode($_POST) . "\n", 
    FILE_APPEND | LOCK_EX);
file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
    "Raw input: " . file_get_contents('php://input') . "\n", 
    FILE_APPEND | LOCK_EX);

// Создаем объект календаря
try {
    $calendarObj = new \Artmax\Calendar\Calendar();
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
        "Calendar object created successfully\n", 
        FILE_APPEND | LOCK_EX);
} catch (Exception $e) {
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
        "Error creating calendar object: " . $e->getMessage() . "\n", 
        FILE_APPEND | LOCK_EX);
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
        $eventColor = $_POST['eventColor'] ?? '#3498db';
        $employeeId = $_POST['employee_id'] ?? null;

        if (empty($title) || empty($dateFrom) || empty($dateTo)) {
            http_response_code(400);
            die(json_encode(['success' => false, 'error' => 'Не все обязательные поля заполнены']));
        }

        $userId = $GLOBALS['USER']->GetID();

        // Сохраняем время как есть, без конвертации в UTC
        // Это позволит избежать проблем с часовыми поясами

        // Проверяем доступность времени для врача в конкретном филиале
        if (!$calendarObj->isTimeAvailableForDoctor($dateFrom, $dateTo, $employeeId, null, $branchId)) {
            die(json_encode(['success' => false, 'error' => 'Время уже занято для выбранного врача в этом филиале']));
        }

        // Добавляем событие с цветом (без конвертации в UTC)
        $eventId = $calendarObj->addEvent($title, $description, $dateFrom, $dateTo, $userId, $branchId, $eventColor, $employeeId);

        if ($eventId) {
            // Записываем в журнал событие создания
            $journal = new \Artmax\Calendar\Journal();
            $journal->writeEvent(
                $eventId,
                'CREATED_BY_CUSTOM',
                'Artmax\Calendar\Calendar::addEvent',
                $userId,
                'EVENT_ID=' . $eventId
            );
            
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

    case 'clearAllEvents':
        // Получаем все события текущего пользователя
        $events = $calendarObj->getEventsByUser($GLOBALS['USER']->GetID());
        $deletedCount = 0;
        
        if ($events) {
            foreach ($events as $event) {
                if ($calendarObj->deleteEvent($event['ID'])) {
                    $deletedCount++;
                }
            }
        }
        
        die(json_encode([
            'success' => true, 
            'deletedCount' => $deletedCount,
            'message' => "Удалено $deletedCount событий"
        ]));
        break;

    case 'updateEvent':
        $eventId = (int)($_POST['eventId'] ?? 0);
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $dateFrom = $_POST['dateFrom'] ?? '';
        $dateTo = $_POST['dateTo'] ?? '';
        $eventColor = $_POST['eventColor'] ?? '#3498db';
        $branchId = (int)($_POST['branchId'] ?? 1);
        $employeeId = $_POST['employee_id'] ?? null;

        if (!$eventId || empty($title) || empty($dateFrom) || empty($dateTo)) {
            http_response_code(400);
            die(json_encode(['success' => false, 'error' => 'Не все обязательные поля заполнены']));
        }

        $event = $calendarObj->getEvent($eventId);
        if (!$event) {
            die(json_encode(['success' => false, 'error' => 'Событие не найдено']));
        }

        // Проверяем права на редактирование (только автор события)
        if ($event['USER_ID'] != $GLOBALS['USER']->GetID()) {
            http_response_code(403);
            die(json_encode(['success' => false, 'error' => 'Нет прав на редактирование']));
        }

        // Проверяем доступность времени для врача при изменении времени
        $timeChanged = ($event['DATE_FROM'] != $dateFrom || $event['DATE_TO'] != $dateTo);
        $doctorChanged = ((int)$event['EMPLOYEE_ID'] != (int)$employeeId);
        
        // Логируем для отладки
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "UPDATE_EVENT_DEBUG: timeChanged=" . ($timeChanged ? 'true' : 'false') . ", doctorChanged=" . ($doctorChanged ? 'true' : 'false') . "\n", 
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "UPDATE_EVENT_DEBUG: oldTime=" . $event['DATE_FROM'] . " - " . $event['DATE_TO'] . ", newTime=" . $dateFrom . " - " . $dateTo . "\n", 
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "UPDATE_EVENT_DEBUG: oldDoctor=" . $event['EMPLOYEE_ID'] . ", newDoctor=" . $employeeId . "\n", 
            FILE_APPEND | LOCK_EX);
        
        if ($timeChanged || $doctorChanged) {
            // Проверяем конфликты с текущим врачом события (не с новым)
            $doctorToCheck = $event['EMPLOYEE_ID'] ? $event['EMPLOYEE_ID'] : null;
            
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "UPDATE_EVENT_DEBUG: Checking conflicts with doctorToCheck=" . $doctorToCheck . "\n", 
                FILE_APPEND | LOCK_EX);
            
            if (!$calendarObj->isTimeAvailableForDoctor($dateFrom, $dateTo, $doctorToCheck, $eventId, $branchId)) {
                die(json_encode(['success' => false, 'error' => 'Время уже занято для выбранного врача в этом филиале']));
            }
        }

        try {
            $result = $calendarObj->updateEvent($eventId, $title, $description, $dateFrom, $dateTo, $eventColor, $branchId, $employeeId);
            if ($result) {
                // Записываем в журнал изменения каждого поля отдельно
                $journal = new \Artmax\Calendar\Journal();
                $userId = $GLOBALS['USER']->GetID();
                
                // Сравниваем и записываем изменения для каждого поля
                
                // TITLE
                if (isset($event['TITLE']) && $event['TITLE'] != $title) {
                    $journal->writeEvent(
                        $eventId,
                        'EVENT_TITLE_CHANGED',
                        'Artmax\Calendar\Calendar::updateEvent',
                        $userId,
                        'TITLE=' . $event['TITLE'] . '->' . $title
                    );
                }
                
                // DESCRIPTION
                $oldDescription = $event['DESCRIPTION'] ?? '';
                if ($oldDescription != $description) {
                    $journal->writeEvent(
                        $eventId,
                        'EVENT_DESCRIPTION_CHANGED',
                        'Artmax\Calendar\Calendar::updateEvent',
                        $userId,
                        'DESCRIPTION=' . ($oldDescription ?: 'empty') . '->' . ($description ?: 'empty')
                    );
                }
                
                // DATE_FROM (начало) - нормализуем даты для сравнения
                if (isset($event['DATE_FROM'])) {
                    // Конвертируем дату из формата dd.mm.yyyy в yyyy-mm-dd для сравнения
                    $oldDateFromNormalized = $event['DATE_FROM'];
                    if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})\s+(\d{1,2}):(\d{1,2}):(\d{1,2})$/', $oldDateFromNormalized, $matches)) {
                        $oldDateFromNormalized = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $matches[3], $matches[2], $matches[1], $matches[4], $matches[5], $matches[6]);
                    }
                    
                    if ($oldDateFromNormalized != $dateFrom) {
                        $journal->writeEvent(
                            $eventId,
                            'EVENT_DATE_FROM_CHANGED',
                            'Artmax\Calendar\Calendar::updateEvent',
                            $userId,
                            'DATE_FROM=' . $event['DATE_FROM'] . '->' . $dateFrom
                        );
                    }
                }
                
                // DATE_TO (окончание) - нормализуем даты для сравнения
                if (isset($event['DATE_TO'])) {
                    // Конвертируем дату из формата dd.mm.yyyy в yyyy-mm-dd для сравнения
                    $oldDateToNormalized = $event['DATE_TO'];
                    if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})\s+(\d{1,2}):(\d{1,2}):(\d{1,2})$/', $oldDateToNormalized, $matches)) {
                        $oldDateToNormalized = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $matches[3], $matches[2], $matches[1], $matches[4], $matches[5], $matches[6]);
                    }
                    
                    if ($oldDateToNormalized != $dateTo) {
                        $journal->writeEvent(
                            $eventId,
                            'EVENT_DATE_TO_CHANGED',
                            'Artmax\Calendar\Calendar::updateEvent',
                            $userId,
                            'DATE_TO=' . $event['DATE_TO'] . '->' . $dateTo
                        );
                    }
                }
                
                // EVENT_COLOR
                $oldColor = $event['EVENT_COLOR'] ?? '#3498db';
                if ($oldColor != $eventColor) {
                    $journal->writeEvent(
                        $eventId,
                        'EVENT_COLOR_CHANGED',
                        'Artmax\Calendar\Calendar::updateEvent',
                        $userId,
                        'EVENT_COLOR=' . $oldColor . '->' . $eventColor
                    );
                }
                
                // BRANCH_ID
                $oldBranchId = $event['BRANCH_ID'] ?? 1;
                if ($oldBranchId != $branchId) {
                    $journal->writeEvent(
                        $eventId,
                        'EVENT_BRANCH_CHANGED',
                        'Artmax\Calendar\Calendar::updateEvent',
                        $userId,
                        'BRANCH_ID=' . $oldBranchId . '->' . $branchId
                    );
                }
                
                // EMPLOYEE_ID
                $oldEmployeeId = !empty($event['EMPLOYEE_ID']) ? (int)$event['EMPLOYEE_ID'] : null;
                $newEmployeeId = !empty($employeeId) ? (int)$employeeId : null;
                if ($oldEmployeeId != $newEmployeeId) {
                    $journal->writeEvent(
                        $eventId,
                        'EVENT_EMPLOYEE_CHANGED',
                        'Artmax\Calendar\Calendar::updateEvent',
                        $userId,
                        'EMPLOYEE_ID=' . ($oldEmployeeId ?? 'null') . '->' . ($newEmployeeId ?? 'null')
                    );
                }
                
                // Обновляем активность CRM если есть
                if (!empty($event['ACTIVITY_ID'])) {
                    $activityUpdated = $calendarObj->updateCrmActivity(
                        $event['ACTIVITY_ID'],
                        $title,
                        $dateFrom,
                        $dateTo
                    );
                    
                    if ($activityUpdated) {
                        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                            "UPDATE_EVENT: Обновлена активность CRM ID={$event['ACTIVITY_ID']}\n", 
                            FILE_APPEND | LOCK_EX);
                    }
                }
                
                // Обновляем бронирование в сделке если есть
                if (!empty($event['DEAL_ENTITY_ID']) && \CModule::IncludeModule('crm')) {
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                        "UPDATE_EVENT (ajax.php): Начинаем обновление бронирования для сделки ID={$event['DEAL_ENTITY_ID']}\n", 
                        FILE_APPEND | LOCK_EX);
                    
                    $responsibleId = $event['EMPLOYEE_ID'] ?? $GLOBALS['USER']->GetID();
                    $branchTimezone = $calendarObj->getBranchTimezone($event['BRANCH_ID']);
                    
                    // Преобразуем формат даты из Y-m-d H:i:s в d.m.Y H:i:s для бронирования
                    $dateFromObj = \DateTime::createFromFormat('Y-m-d H:i:s', $dateFrom, new \DateTimeZone($branchTimezone));
                    $dateToObj = \DateTime::createFromFormat('Y-m-d H:i:s', $dateTo, new \DateTimeZone($branchTimezone));
                    
                    if (!$dateFromObj || !$dateToObj) {
                        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                            "UPDATE_EVENT (ajax.php): Ошибка парсинга дат. dateFrom=$dateFrom, dateTo=$dateTo\n", 
                            FILE_APPEND | LOCK_EX);
                        // Пробуем другой формат
                        $dateFromObj = \DateTime::createFromFormat('d.m.Y H:i:s', $dateFrom, new \DateTimeZone($branchTimezone));
                        $dateToObj = \DateTime::createFromFormat('d.m.Y H:i:s', $dateTo, new \DateTimeZone($branchTimezone));
                    }
                    
                    if ($dateFromObj && $dateToObj) {
                        // Используем исходное время как есть
                        $bookingDateTime = $dateFromObj->format('d.m.Y H:i:s');
                        
                        // Вычисляем длительность
                        $durationSeconds = $dateToObj->getTimestamp() - $dateFromObj->getTimestamp();
                        $bookingValue = "user|{$responsibleId}|{$bookingDateTime}|{$durationSeconds}|{$title}";
                        
                        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                            "UPDATE_EVENT (ajax.php): Вычисление бронирования:\n" .
                            "  dateFrom (вход): $dateFrom\n" .
                            "  dateTo (вход): $dateTo\n" .
                            "  branchTimezone: $branchTimezone\n" .
                            "  bookingDateTime: $bookingDateTime\n" .
                            "  startDateTime timestamp: {$dateFromObj->getTimestamp()}\n" .
                            "  endDateTime timestamp: {$dateToObj->getTimestamp()}\n" .
                            "  durationSeconds: $durationSeconds\n" .
                            "  EMPLOYEE_ID: {$event['EMPLOYEE_ID']}\n" .
                            "  responsibleId: $responsibleId\n" .
                            "  title: $title\n" .
                            "  ФИНАЛЬНАЯ СТРОКА: $bookingValue\n", 
                            FILE_APPEND | LOCK_EX);
                        
                        $bookingFieldCode = \Bitrix\Main\Config\Option::get('artmax.calendar', 'deal_booking_field', 'UF_CRM_CALENDAR_BOOKING');
                        
                        $deal = new \CCrmDeal(false);
                        $updateFields = [
                            $bookingFieldCode => [$bookingValue]
                        ];
                        $deal->Update($event['DEAL_ENTITY_ID'], $updateFields);
                        
                        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                            "UPDATE_EVENT (ajax.php): Обновлено бронирование в сделке ID={$event['DEAL_ENTITY_ID']}\n", 
                            FILE_APPEND | LOCK_EX);
                    }
                }
                
                die(json_encode(['success' => true]));
            } else {
                die(json_encode(['success' => false, 'error' => 'Ошибка обновления события']));
            }
        } catch (Exception $e) {
            die(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }
        break;

    case 'assignDoctor':
        $eventId = (int)($_POST['eventId'] ?? 0);
        $employeeId = $_POST['employee_id'] ?? null;

        if (!$eventId || !$employeeId) {
            http_response_code(400);
            die(json_encode(['success' => false, 'error' => 'ID события и врача обязательны']));
        }

        $event = $calendarObj->getEvent($eventId);
        if (!$event) {
            die(json_encode(['success' => false, 'error' => 'Событие не найдено']));
        }

        // Проверяем права на редактирование (только автор события)
        if ($event['USER_ID'] != $GLOBALS['USER']->GetID()) {
            http_response_code(403);
            die(json_encode(['success' => false, 'error' => 'Нет прав на редактирование']));
        }

        try {
            $oldEmployeeId = !empty($event['EMPLOYEE_ID']) ? (int)$event['EMPLOYEE_ID'] : null;
            $newEmployeeId = (int)$employeeId;
            
            $result = $calendarObj->assignDoctor($eventId, $employeeId);
            if ($result) {
                // Записываем в журнал изменение ответственного врача
                $journal = new \Artmax\Calendar\Journal();
                $userId = $GLOBALS['USER']->GetID();
                
                $actionValue = 'EMPLOYEE_ID=' . ($oldEmployeeId ? $oldEmployeeId : 'null') . '->' . $newEmployeeId;
                $journal->writeEvent(
                    $eventId,
                    'EMPLOYEE_CHANGED',
                    'Artmax\Calendar\Calendar::assignDoctor',
                    $userId,
                    $actionValue
                );
                
                die(json_encode(['success' => true]));
            } else {
                die(json_encode(['success' => false, 'error' => 'Ошибка назначения врача']));
            }
        } catch (Exception $e) {
            die(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }
        break;

    case 'getOccupiedTimes':
        $date = $_POST['date'] ?? '';
        $employeeId = $_POST['employee_id'] ?? null;
        $excludeEventId = $_POST['excludeEventId'] ?? null;

        if (!$date) {
            http_response_code(400);
            die(json_encode(['success' => false, 'error' => 'Дата обязательна']));
        }

        try {
            $occupiedTimes = $calendarObj->getOccupiedTimesForDoctor($date, $employeeId, $excludeEventId);
            die(json_encode(['success' => true, 'occupiedTimes' => $occupiedTimes]));
        } catch (Exception $e) {
            die(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }
        break;

    case 'checkTimeAvailability':
        $dateFrom = $_POST['dateFrom'] ?? '';
        $dateTo = $_POST['dateTo'] ?? '';
        $employeeId = $_POST['employeeId'] ?? null;
        $excludeEventId = $_POST['excludeEventId'] ?? null;
        $branchId = $_POST['branchId'] ?? null;

        if (!$dateFrom || !$dateTo) {
            http_response_code(400);
            die(json_encode(['success' => false, 'error' => 'Даты обязательны']));
        }

        try {
            // Конвертируем даты из российского формата в стандартный
            $convertedDateFrom = convertRussianDateToStandard($dateFrom);
            $convertedDateTo = convertRussianDateToStandard($dateTo);
            
            $available = $calendarObj->isTimeAvailableForDoctor($convertedDateFrom, $convertedDateTo, $employeeId, $excludeEventId, $branchId);
            die(json_encode(['success' => true, 'available' => $available]));
        } catch (Exception $e) {
            die(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }
        break;

    case 'getAvailableTimesForMove':
        $date = $_POST['date'] ?? '';
        $employeeId = $_POST['employeeId'] ?? null;
        $excludeEventId = $_POST['excludeEventId'] ?? null;

        if (!$date) {
            http_response_code(400);
            die(json_encode(['success' => false, 'error' => 'Дата обязательна']));
        }

        try {
            $availableTimes = $calendarObj->getAvailableTimesForMove($date, $excludeEventId, $employeeId);
            die(json_encode(['success' => true, 'availableTimes' => $availableTimes]));
        } catch (Exception $e) {
            die(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }
        break;

    case 'getDoctorScheduleForMove':
        $date = $_POST['date'] ?? '';
        $employeeId = $_POST['employeeId'] ?? null;
        $excludeEventId = $_POST['excludeEventId'] ?? null;
        $branchId = $_POST['branchId'] ?? null;

        if (!$date || !$employeeId) {
            http_response_code(400);
            die(json_encode(['success' => false, 'error' => 'Дата и врач обязательны']));
        }

        try {
            $availableTimes = $calendarObj->getDoctorScheduleForMove($date, $employeeId, $excludeEventId, $branchId);
            die(json_encode(['success' => true, 'availableTimes' => $availableTimes]));
        } catch (Exception $e) {
            die(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }
        break;

    case 'moveEvent':
        $eventId = (int)($_POST['eventId'] ?? 0);
        $branchId = isset($_POST['branchId']) ? (int)$_POST['branchId'] : null;
        $employeeId = isset($_POST['employeeId']) ? (int)$_POST['employeeId'] : null;
        $newDateFrom = $_POST['dateFrom'] ?? '';
        $newDateTo = $_POST['dateTo'] ?? '';

        // Логируем входящие параметры
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "MOVE_EVENT_ACTION: Parameters:\n" .
            "  - eventId: $eventId\n" .
            "  - dateFrom: $newDateFrom\n" .
            "  - dateTo: $newDateTo\n" .
            "  - employeeId: $employeeId\n" .
            "  - branchId: $branchId\n\n", 
            FILE_APPEND | LOCK_EX);

        // Проверяем на NaN в dateTo
        if (strpos($newDateTo, 'NaN') !== false) {
            http_response_code(400);
            die(json_encode(['success' => false, 'error' => 'Неправильный формат даты окончания']));
        }

        if (!$eventId || !$newDateFrom || !$newDateTo) {
            http_response_code(400);
            die(json_encode(['success' => false, 'error' => 'Не все параметры переданы']));
        }

        try {
            $component = new ArtmaxCalendarComponent();
            $params = [
                'event_id' => $eventId,
                'branch_id' => $branchId,
                'employee_id' => $employeeId,
                'date_from' => $newDateFrom,
                'date_to' => $newDateTo,
            ];

            $result = $component->moveEventAction($params);
            if (!empty($result['success'])) {
                // Передаем полный результат с информацией о затронутых событиях
                die(json_encode($result));
            } else {
                $error = $result['error'] ?? 'Ошибка переноса записи';
                die(json_encode(['success' => false, 'error' => $error]));
            }
        } catch (Exception $e) {
            die(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }
        break;

    case 'updateEventStatus':
        $eventId = (int)($_POST['eventId'] ?? 0);
        $status = $_POST['status'] ?? '';

        if (!$eventId || !in_array($status, ['active', 'moved', 'cancelled'])) {
            http_response_code(400);
            die(json_encode(['success' => false, 'error' => 'Некорректные параметры']));
        }

        $event = $calendarObj->getEvent($eventId);
        if (!$event) {
            die(json_encode(['success' => false, 'error' => 'Событие не найдено']));
        }

        // Проверяем права на редактирование (только автор события)
        if ($event['USER_ID'] != $GLOBALS['USER']->GetID()) {
            http_response_code(403);
            die(json_encode(['success' => false, 'error' => 'Нет прав на редактирование']));
        }

        try {
            // Если возвращаем отмененную запись в расписание, проверяем доступность времени
            if ($event['STATUS'] === 'cancelled' && $status === 'active') {
                // Конвертируем даты из российского формата в стандартный для SQL
                $dateFromStandard = convertRussianDateToStandard($event['DATE_FROM']);
                $dateToStandard = convertRussianDateToStandard($event['DATE_TO']);
                
                $isAvailable = $calendarObj->isTimeAvailableForDoctor(
                    $dateFromStandard, 
                    $dateToStandard, 
                    $event['EMPLOYEE_ID'], 
                    $eventId,
                    $event['BRANCH_ID']
                );
                
                if (!$isAvailable) {
                    die(json_encode([
                        'success' => false, 
                        'error' => 'Время уже занято другой записью. Пожалуйста, измените время записи перед возвратом в расписание.'
                    ]));
                }
            }
            
            $result = $calendarObj->updateEventStatus($eventId, $status);
            if ($result) {
                die(json_encode(['success' => true]));
            } else {
                die(json_encode(['success' => false, 'error' => 'Ошибка обновления статуса']));
            }
        } catch (Exception $e) {
            die(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }
        break;

    case 'getEvents':
        $branchId = (int)($_POST['branchId'] ?? 1);
        $dateFrom = $_POST['dateFrom'] ?? null;
        $dateTo = $_POST['dateTo'] ?? null;

        // Отладочная информация
        error_log("DYNAMIC LOAD: dateFrom=$dateFrom, dateTo=$dateTo, branchId=$branchId");

        try {
            $events = $calendarObj->getEventsByBranch($branchId, $dateFrom, $dateTo);
            error_log("DYNAMIC LOAD: actual events count=" . count($events));
            
            // Логируем первые несколько событий для отладки
            if (count($events) > 0) {
                error_log("DYNAMIC LOAD: first event sample: " . json_encode($events[0]));
            }
            
            die(json_encode(['success' => true, 'events' => $events]));
        } catch (Exception $e) {
            error_log("DYNAMIC LOAD ERROR: " . $e->getMessage());
            die(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }
        break;

    case 'getEvent':
        $eventId = (int)($_POST['eventId'] ?? 0);

        // Логируем запрос
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log',
            "=== AJAX GET_EVENT ===\n",
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log',
            "AJAX: Requesting event ID: {$eventId}\n",
            FILE_APPEND | LOCK_EX);

        if (!$eventId) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log',
                "AJAX: Error - ID события не указан\n",
                FILE_APPEND | LOCK_EX);
            http_response_code(400);
            die(json_encode(['success' => false, 'error' => 'ID события не указан']));
        }

        try {
            // Используем компонент для получения события с контактом
            $component = new ArtmaxCalendarComponent();
            $result = $component->getEventAction($eventId);
            
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log',
                "AJAX: getEventAction result: " . json_encode($result) . "\n",
                FILE_APPEND | LOCK_EX);
            
            die(json_encode($result));
        } catch (Exception $e) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log',
                "AJAX: Exception: " . $e->getMessage() . "\n",
                FILE_APPEND | LOCK_EX);
            die(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }

        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log',
            "=== END AJAX GET_EVENT ===\n",
            FILE_APPEND | LOCK_EX);
        break;

    case 'addSchedule':
        // Правильно обрабатываем массив weekdays
        $weekdays = [];
        if (isset($_POST['weekdays']) && is_array($_POST['weekdays'])) {
            $weekdays = array_map('intval', $_POST['weekdays']);
        } elseif (isset($_POST['weekdays']) && is_string($_POST['weekdays']) && !empty($_POST['weekdays'])) {
            // Если weekdays приходит как строка "1,2", разбиваем её на массив
            $weekdays = array_map('intval', explode(',', $_POST['weekdays']));
        }

        // Логируем полученные данные
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log',
            "=== ADD_SCHEDULE AJAX DEBUG ===\n",
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log',
            "POST data: " . json_encode($_POST) . "\n",
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log',
            "eventColor from POST: " . ($_POST['eventColor'] ?? 'NOT SET') . "\n",
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log',
            "excludeWeekends from POST: " . ($_POST['excludeWeekends'] ?? 'NOT SET') . "\n",
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log',
            "excludeHolidays from POST: " . ($_POST['excludeHolidays'] ?? 'NOT SET') . "\n",
            FILE_APPEND | LOCK_EX);

        // Создаем экземпляр компонента для вызова метода
        $component = new ArtmaxCalendarComponent();
        
        // Подготавливаем параметры в виде массива
        $params = [
            'title' => $_POST['title'] ?? '',
            'date' => $_POST['date'] ?? '',
            'time' => $_POST['time'] ?? '',
            'employee_id' => $_POST['employee_id'] ?? null,
            'branch_id' => $_POST['branch_id'] ?? 1,
            'repeat' => $_POST['repeat'] === 'on' || $_POST['repeat'] === 'true' || $_POST['repeat'] === 'Y' || $_POST['repeat'] === 'y',
            'frequency' => $_POST['frequency'] ?? null,
            'weekdays' => $weekdays,
            'repeat_end' => $_POST['repeatEnd'] ?? 'never',
            'repeat_count' => !empty($_POST['repeatCount']) ? (int)$_POST['repeatCount'] : null,
            'repeat_end_date' => !empty($_POST['repeatEndDate']) ? $_POST['repeatEndDate'] : null,
            'event_color' => $_POST['eventColor'] ?? '#3498db',
            'exclude_weekends' => $_POST['excludeWeekends'] === 'on' || $_POST['excludeWeekends'] === 'true' || false,
            'exclude_holidays' => $_POST['excludeHolidays'] === 'on' || $_POST['excludeHolidays'] === 'true' || false,
            'include_end_date' => $_POST['includeEndDate'] === 'on' || $_POST['includeEndDate'] === 'true'
        ];
        
        $result = $component->addScheduleAction($params);

        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log',
            "Result: " . json_encode($result) . "\n",
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log',
            "=== END ADD_SCHEDULE AJAX DEBUG ===\n",
            FILE_APPEND | LOCK_EX);

        die(json_encode($result));
        break;

    case 'searchClients':
        $query = $_POST['query'] ?? '';
        $type = $_POST['type'] ?? 'contact';

        // Логируем запрос поиска
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log',
            "=== AJAX SEARCH_CLIENTS ===\n",
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log',
            "Query: {$query}, Type: {$type}\n",
            FILE_APPEND | LOCK_EX);

        if (empty($query)) {
            die(json_encode(['success' => false, 'error' => 'Запрос не может быть пустым']));
        }

        try {
            // Создаем экземпляр компонента для вызова метода поиска
            $component = new ArtmaxCalendarComponent();
            $result = $component->searchClientsAction($query, $type);

            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log',
                "Search result: " . json_encode($result) . "\n",
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log',
                "=== END AJAX SEARCH_CLIENTS ===\n",
                FILE_APPEND | LOCK_EX);

            die(json_encode($result));
        } catch (Exception $e) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log',
                "Search error: " . $e->getMessage() . "\n",
                FILE_APPEND | LOCK_EX);
            die(json_encode(['success' => false, 'error' => 'Ошибка поиска: ' . $e->getMessage()]));
        }
        break;



    case 'getEventContacts':
        $eventId = $_POST['eventId'] ?? 0;

        if (empty($eventId)) {
            die(json_encode(['success' => false, 'error' => 'ID события не указан']));
        }

        try {
            $component = new ArtmaxCalendarComponent();
            $result = $component->getEventContactsAction($eventId);

            die(json_encode($result));
        } catch (Exception $e) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log',
                "Get contacts error: " . $e->getMessage() . "\n",
                FILE_APPEND | LOCK_EX);
            die(json_encode(['success' => false, 'error' => 'Ошибка получения контактов: ' . $e->getMessage()]));
        }
        break;

    case 'getEventDeals':
        $eventId = $_POST['eventId'] ?? 0;

        if (empty($eventId)) {
            die(json_encode(['success' => false, 'error' => 'ID события не указан']));
        }

        try {
            $component = new ArtmaxCalendarComponent();
            $result = $component->getEventDealsAction($eventId);

            die(json_encode($result));
        } catch (Exception $e) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log',
                "Get deals error: " . $e->getMessage() . "\n",
                FILE_APPEND | LOCK_EX);
            die(json_encode(['success' => false, 'error' => 'Ошибка получения сделок: ' . $e->getMessage()]));
        }
        break;

    case 'createDealForEvent':
        $eventId = $_POST['eventId'] ?? 0;
        $contactId = $_POST['contactId'] ?? 0;

        if (empty($eventId) || empty($contactId)) {
            die(json_encode(['success' => false, 'error' => 'ID события или контакта не указан']));
        }

        try {
            $component = new ArtmaxCalendarComponent();
            $result = $component->createDealForEventAction($eventId, $contactId);

            die(json_encode($result));
        } catch (Exception $e) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log',
                "Create deal error: " . $e->getMessage() . "\n",
                FILE_APPEND | LOCK_EX);
            die(json_encode(['success' => false, 'error' => 'Ошибка создания сделки: ' . $e->getMessage()]));
        }
        break;
        
    case 'getContactData':
        $contactId = $_POST['contactId'] ?? 0;
        if (empty($contactId)) {
            die(json_encode(['success' => false, 'error' => 'ID контакта не указан']));
        }
        try {
            $component = new ArtmaxCalendarComponent();
            $contact = $component->getContactFromCRM($contactId);
            if ($contact) {
                die(json_encode(['success' => true, 'contact' => $contact]));
            } else {
                die(json_encode(['success' => false, 'error' => 'Контакт не найден']));
            }
        } catch (Exception $e) {
            die(json_encode(['success' => false, 'error' => 'Ошибка получения контакта: ' . $e->getMessage()]));
        }
        break;
        
    case 'createContact':
        $contactData = $_POST['contactData'] ?? '';
        if (empty($contactData)) {
            die(json_encode(['success' => false, 'error' => 'Данные контакта не указаны']));
        }
        try {
            $contactData = json_decode($contactData, true);
            if (!$contactData) {
                die(json_encode(['success' => false, 'error' => 'Неверный формат данных контакта']));
            }
            
            $component = new ArtmaxCalendarComponent();
            $result = $component->createContactAction($contactData);
            die(json_encode($result));
        } catch (Exception $e) {
            die(json_encode(['success' => false, 'error' => 'Ошибка создания контакта: ' . $e->getMessage()]));
        }
        break;
        
    case 'saveEventContact':
        $eventId = $_POST['eventId'] ?? 0;
        $contactData = $_POST['contactData'] ?? '';
        
        if (empty($eventId) || empty($contactData)) {
            die(json_encode(['success' => false, 'error' => 'ID события или данные контакта не указаны']));
        }
        
        try {
            $contactData = json_decode($contactData, true);
            if (!$contactData || !isset($contactData['id'])) {
                die(json_encode(['success' => false, 'error' => 'Неверный формат данных контакта']));
            }
            
            $contactId = $contactData['id'];
            $component = new ArtmaxCalendarComponent();
            $result = $component->saveEventContactAction($eventId, $contactId, $contactData);
            die(json_encode($result));
        } catch (Exception $e) {
            die(json_encode(['success' => false, 'error' => 'Ошибка сохранения контакта: ' . $e->getMessage()]));
        }
        break;
        
    case 'getEventData':
        $eventId = $_POST['eventId'] ?? 0;
        if (empty($eventId)) {
            die(json_encode(['success' => false, 'error' => 'ID события не указан']));
        }
        try {
            $component = new ArtmaxCalendarComponent();
            $event = $component->getEventDataAction($eventId);
            die(json_encode($event));
        } catch (Exception $e) {
            die(json_encode(['success' => false, 'error' => 'Ошибка получения данных события: ' . $e->getMessage()]));
        }
        break;

    case 'saveEventNote':
        $eventId = $_POST['eventId'] ?? 0;
        $noteText = $_POST['noteText'] ?? '';
        
        if (empty($eventId) || empty($noteText)) {
            die(json_encode(['success' => false, 'error' => 'ID события или текст заметки не указан']));
        }
        
        try {
            $component = new ArtmaxCalendarComponent();
            $result = $component->saveEventNoteAction($eventId, $noteText);
            die(json_encode($result));
        } catch (Exception $e) {
            die(json_encode(['success' => false, 'error' => 'Ошибка сохранения заметки: ' . $e->getMessage()]));
        }
        break;

    case 'getEmployees':
        try {
            $component = new ArtmaxCalendarComponent();
            $result = $component->getEmployeesAction();
            die(json_encode($result));
        } catch (Exception $e) {
            die(json_encode(['success' => false, 'error' => 'Ошибка получения сотрудников: ' . $e->getMessage()]));
        }
        break;

    case 'searchEmployees':
        $query = $_POST['query'] ?? '';
        
        try {
            $component = new ArtmaxCalendarComponent();
            $result = $component->searchEmployeesAction($query);
            die(json_encode($result));
        } catch (Exception $e) {
            die(json_encode(['success' => false, 'error' => 'Ошибка поиска сотрудников: ' . $e->getMessage()]));
        }
        break;

    case 'saveBranchSettings':
        $branchId = $_POST['branch_id'] ?? 0;
        $timezoneName = $_POST['timezone_name'] ?? '';
        $employeeIds = $_POST['employee_ids'] ?? '[]';
        $branchName = $_POST['branch_name'] ?? '';
        
        if (empty($branchId)) {
            die(json_encode(['success' => false, 'error' => 'ID филиала не указан']));
        }
        
        try {
            $component = new ArtmaxCalendarComponent();
            $result = $component->saveBranchSettingsAction($branchId, $timezoneName, $employeeIds, $branchName);
            die(json_encode($result));
        } catch (Exception $e) {
            die(json_encode(['success' => false, 'error' => 'Ошибка сохранения настроек филиала: ' . $e->getMessage()]));
        }
        break;

    case 'getBranchEmployees':
        $branchId = $_POST['branchId'] ?? $_POST['branch_id'] ?? 0;
        
        // Отладочная информация
        error_log("AJAX getBranchEmployees: branchId = " . $branchId);
        error_log("AJAX getBranchEmployees: POST data = " . json_encode($_POST));
        
        try {
            $component = new ArtmaxCalendarComponent();
            $result = $component->getBranchEmployeesAction($branchId);
            error_log("AJAX getBranchEmployees: result = " . json_encode($result));
            die(json_encode($result));
        } catch (Exception $e) {
            error_log("AJAX getBranchEmployees: error = " . $e->getMessage());
            die(json_encode(['success' => false, 'error' => 'Ошибка получения сотрудников: ' . $e->getMessage()]));
        }
        break;

    case 'saveEventDeal':
        $eventId = $_POST['eventId'] ?? 0;
        $dealData = $_POST['dealData'] ?? array();

        // Логируем запрос
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log',
            "=== AJAX SAVE_EVENT_DEAL ===\n",
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log',
            "EventId: {$eventId}, DealData: " . json_encode($dealData) . "\n",
            FILE_APPEND | LOCK_EX);

        if (empty($eventId) || empty($dealData)) {
            die(json_encode(['success' => false, 'error' => 'Недостаточно данных для сохранения']));
        }

        try {
            $component = new ArtmaxCalendarComponent();
            $result = $component->saveEventDealAction($eventId, $dealData);

            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log',
                "Save result: " . json_encode($result) . "\n",
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log',
                "=== END AJAX SAVE_EVENT_DEAL ===\n",
                FILE_APPEND | LOCK_EX);

            die(json_encode($result));
        } catch (Exception $e) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log',
                "Save error: " . $e->getMessage() . "\n",
                FILE_APPEND | LOCK_EX);
            die(json_encode(['success' => false, 'error' => 'Ошибка сохранения: ' . $e->getMessage()]));
        }
        break;

    case 'get_confirmation_status':
        $eventId = (int)($_POST['event_id'] ?? 0);

        if (!$eventId) {
            http_response_code(400);
            die(json_encode(['success' => false, 'error' => 'ID события не указан']));
        }

        try {
            $event = $calendarObj->getEvent($eventId);
            if (!$event) {
                die(json_encode(['success' => false, 'error' => 'Событие не найдено']));
            }

            // Получаем статус подтверждения из поля события
            $confirmationStatus = $event['CONFIRMATION_STATUS'] ?? 'pending';

            die(json_encode([
                'success' => true,
                'confirmation_status' => $confirmationStatus
            ]));
        } catch (Exception $e) {
            die(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }
        break;

    case 'update_confirmation_status':
        $eventId = (int)($_POST['event_id'] ?? 0);
        $confirmationStatus = $_POST['confirmation_status'] ?? '';

        if (!$eventId || empty($confirmationStatus)) {
            http_response_code(400);
            die(json_encode(['success' => false, 'error' => 'Не все обязательные поля заполнены']));
        }

        // Проверяем валидность статуса
        $validStatuses = ['confirmed', 'not_confirmed', 'pending'];
        if (!in_array($confirmationStatus, $validStatuses)) {
            http_response_code(400);
            die(json_encode(['success' => false, 'error' => 'Недопустимый статус подтверждения']));
        }

        try {

            $event = $calendarObj->getEvent($eventId);
            if (!$event) {
                die(json_encode(['success' => false, 'error' => 'Событие не найдено']));
            }

            // Проверяем права на редактирование (только автор события)
            if ($event['USER_ID'] != $GLOBALS['USER']->GetID()) {
                http_response_code(403);
                die(json_encode(['success' => false, 'error' => 'Нет прав на редактирование']));
            }

            // Получаем старое значение статуса подтверждения
            $oldConfirmationStatus = !empty($event['CONFIRMATION_STATUS']) ? $event['CONFIRMATION_STATUS'] : 'pending';
            
            // Обновляем статус подтверждения в базе данных
            $result = $calendarObj->updateEventConfirmationStatus($eventId, $confirmationStatus);

            if ($result) {
                // Записываем в журнал изменение статуса подтверждения
                $journal = new \Artmax\Calendar\Journal();
                $userId = $GLOBALS['USER']->GetID();
                
                $actionValue = 'CONFIRMATION_STATUS=' . $oldConfirmationStatus . '->' . $confirmationStatus;
                $journal->writeEvent(
                    $eventId,
                    'CONFIRMATION_STATUS_CHANGED',
                    'Artmax\Calendar\Calendar::updateEventConfirmationStatus',
                    $userId,
                    $actionValue
                );
                
                die(json_encode(['success' => true, 'message' => 'Статус подтверждения обновлен']));
            } else {
                die(json_encode(['success' => false, 'error' => 'Ошибка обновления статуса подтверждения']));
            }
        } catch (Exception $e) {
            die(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }
        break;

    case 'get_visit_status':
        $eventId = (int)($_POST['event_id'] ?? 0);
        
        if (!$eventId) {
            http_response_code(400);
            die(json_encode(['success' => false, 'error' => 'ID события не указан']));
        }
        
        try {
            $event = $calendarObj->getEvent($eventId);
            if (!$event) {
                die(json_encode(['success' => false, 'error' => 'Событие не найдено']));
            }
            
            die(json_encode([
                'success' => true, 
                'visit_status' => $event['VISIT_STATUS'] ?? 'not_specified'
            ]));
        } catch (Exception $e) {
            die(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }
        break;
        
    case 'update_visit_status':
        $eventId = (int)($_POST['event_id'] ?? 0);
        $visitStatus = $_POST['visit_status'] ?? '';
        
        if (!$eventId || empty($visitStatus)) {
            http_response_code(400);
            die(json_encode(['success' => false, 'error' => 'Не все обязательные поля заполнены']));
        }
        
        // Проверяем валидность статуса
        $validStatuses = ['not_specified', 'client_came', 'client_did_not_come'];
        if (!in_array($visitStatus, $validStatuses)) {
            http_response_code(400);
            die(json_encode(['success' => false, 'error' => 'Недопустимый статус визита']));
        }
        
        try {
            $event = $calendarObj->getEvent($eventId);
            if (!$event) {
                die(json_encode(['success' => false, 'error' => 'Событие не найдено']));
            }
            
            // Проверяем права на редактирование (только автор события)
            if ($event['USER_ID'] != $GLOBALS['USER']->GetID()) {
                http_response_code(403);
                die(json_encode(['success' => false, 'error' => 'Нет прав на редактирование']));
            }
            
            // Получаем старое значение статуса визита
            $oldVisitStatus = !empty($event['VISIT_STATUS']) ? $event['VISIT_STATUS'] : 'not_specified';
            
            // Обновляем статус визита в базе данных
            $result = $calendarObj->updateEventVisitStatus($eventId, $visitStatus);
            
            if ($result) {
                // Записываем в журнал изменение статуса визита
                $journal = new \Artmax\Calendar\Journal();
                $userId = $GLOBALS['USER']->GetID();
                
                $actionValue = 'VISIT_STATUS=' . $oldVisitStatus . '->' . $visitStatus;
                $journal->writeEvent(
                    $eventId,
                    'VISIT_STATUS_CHANGED',
                    'Artmax\Calendar\Calendar::updateEventVisitStatus',
                    $userId,
                    $actionValue
                );
                
                die(json_encode(['success' => true, 'message' => 'Статус визита обновлен']));
            } else {
                die(json_encode(['success' => false, 'error' => 'Ошибка обновления статуса визита']));
            }
        } catch (Exception $e) {
            die(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }
        break;

    case 'getBranches':
        try {
            $component = new ArtmaxCalendarComponent();
            $result = $component->getBranchesAction();
            die(json_encode($result));
        } catch (Exception $e) {
            die(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }
        break;

    case 'addBranch':
        $name = $_POST['name'] ?? '';
        $address = $_POST['address'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $email = $_POST['email'] ?? '';
        
        try {
            $component = new ArtmaxCalendarComponent();
            $result = $component->addBranchAction($name, $address, $phone, $email);
            die(json_encode($result));
        } catch (Exception $e) {
            die(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }
        break;

    default:
        http_response_code(400);
        die(json_encode(['success' => false, 'error' => 'Неизвестное действие']));
        http_response_code(500);
        die(json_encode(['success' => false, 'error' => 'Неожиданная ошибка']));
        break;
}
