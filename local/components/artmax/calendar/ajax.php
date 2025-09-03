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

// Получаем действие
$action = $_POST['action'] ?? '';

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
        
        if (empty($title) || empty($dateFrom) || empty($dateTo)) {
            http_response_code(400);
            die(json_encode(['success' => false, 'error' => 'Не все обязательные поля заполнены']));
        }
        
        $userId = $GLOBALS['USER']->GetID();
        
        // Логируем полученные данные
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "=== AJAX ADD_EVENT DEBUG ===\n", 
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "AJAX: Received data:\n", 
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "  - title: {$title}\n", 
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "  - dateFrom: {$dateFrom}\n", 
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "  - dateTo: {$dateTo}\n", 
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "  - eventColor: {$eventColor}\n", 
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "=== END AJAX ADD_EVENT DEBUG ===\n", 
            FILE_APPEND | LOCK_EX);
        
        // Сохраняем время как есть, без конвертации в UTC
        // Это позволит избежать проблем с часовыми поясами
        
        // Проверяем доступность времени
        if (!$calendarObj->isTimeAvailable($dateFrom, $dateTo, $userId)) {
            die(json_encode(['success' => false, 'error' => 'Время уже занято']));
        }
        
        // Добавляем событие с цветом (без конвертации в UTC)
        $eventId = $calendarObj->addEvent($title, $description, $dateFrom, $dateTo, $userId, $branchId, $eventColor);
        
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
        
    case 'updateEvent':
        $eventId = (int)($_POST['eventId'] ?? 0);
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $dateFrom = $_POST['dateFrom'] ?? '';
        $dateTo = $_POST['dateTo'] ?? '';
        $eventColor = $_POST['eventColor'] ?? '#3498db';
        $branchId = (int)($_POST['branchId'] ?? 1);
        
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
        
        try {
            $result = $calendarObj->updateEvent($eventId, $title, $description, $dateFrom, $dateTo, $eventColor, $branchId);
            if ($result) {
                die(json_encode(['success' => true]));
            } else {
                die(json_encode(['success' => false, 'error' => 'Ошибка обновления события']));
            }
        } catch (Exception $e) {
            die(json_encode(['success' => false, 'error' => $e->getMessage()]));
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
            $event = $calendarObj->getEvent($eventId);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "AJAX: getEvent result: " . json_encode($event) . "\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "AJAX: DATE_FROM type: " . gettype($event['DATE_FROM'] ?? null) . "\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "AJAX: DATE_TO type: " . gettype($event['DATE_TO'] ?? null) . "\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "AJAX: DATE_FROM value: " . print_r($event['DATE_FROM'] ?? null, true) . "\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "AJAX: DATE_TO value: " . print_r($event['DATE_TO'] ?? null, true) . "\n", 
                FILE_APPEND | LOCK_EX);
            
            if ($event) {
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "AJAX: Event found, returning success\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "AJAX: Final event data before JSON: " . print_r($event, true) . "\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "AJAX: Final DATE_FROM type: " . gettype($event['DATE_FROM'] ?? null) . "\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "AJAX: Final DATE_TO type: " . gettype($event['DATE_TO'] ?? null) . "\n", 
                    FILE_APPEND | LOCK_EX);
                $jsonResult = json_encode(['success' => true, 'event' => $event]);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "AJAX: JSON result: " . $jsonResult . "\n", 
                    FILE_APPEND | LOCK_EX);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                        "AJAX: JSON error: " . json_last_error_msg() . "\n", 
                        FILE_APPEND | LOCK_EX);
                }
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "AJAX: JSON length: " . strlen($jsonResult) . "\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "AJAX: JSON first 500 chars: " . substr($jsonResult, 0, 500) . "\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "AJAX: JSON last 500 chars: " . substr($jsonResult, -500) . "\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "AJAX: JSON contains DATE_FROM: " . (strpos($jsonResult, 'DATE_FROM') !== false ? 'YES' : 'NO') . "\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "AJAX: JSON contains DATE_TO: " . (strpos($jsonResult, 'DATE_TO') !== false ? 'YES' : 'NO') . "\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "AJAX: JSON contains empty objects: " . (strpos($jsonResult, '{}') !== false ? 'YES' : 'NO') . "\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "AJAX: JSON contains quotes around DATE_FROM: " . (strpos($jsonResult, '"DATE_FROM"') !== false ? 'YES' : 'NO') . "\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "AJAX: JSON contains quotes around DATE_TO: " . (strpos($jsonResult, '"DATE_TO"') !== false ? 'YES' : 'NO') . "\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "AJAX: JSON contains quotes around DATE_FROM value: " . (strpos($jsonResult, '"DATE_FROM":') !== false ? 'YES' : 'NO') . "\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "AJAX: JSON contains quotes around DATE_TO value: " . (strpos($jsonResult, '"DATE_TO":') !== false ? 'YES' : 'NO') . "\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "AJAX: JSON contains quotes around DATE_FROM value: " . (strpos($jsonResult, '"DATE_FROM":') !== false ? 'YES' : 'NO') . "\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "AJAX: JSON contains quotes around DATE_TO value: " . (strpos($jsonResult, '"DATE_TO":') !== false ? 'YES' : 'NO') . "\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "AJAX: JSON contains quotes around DATE_FROM value: " . (strpos($jsonResult, '"DATE_FROM":') !== false ? 'YES' : 'NO') . "\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "AJAX: JSON contains quotes around DATE_TO value: " . (strpos($jsonResult, '"DATE_TO":') !== false ? 'YES' : 'NO') . "\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "AJAX: JSON contains quotes around DATE_FROM value: " . (strpos($jsonResult, '"DATE_FROM":') !== false ? 'YES' : 'NO') . "\n", 
                    FILE_APPEND | LOCK_EX);
                die($jsonResult);
            } else {
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "AJAX: Event not found\n", 
                    FILE_APPEND | LOCK_EX);
                die(json_encode(['success' => false, 'error' => 'Событие не найдено']));
            }
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
        
        // Создаем экземпляр компонента для вызова метода
        $component = new ArtmaxCalendarComponent();
        $result = $component->addScheduleAction(
            $_POST['title'] ?? '',
            $_POST['date'] ?? '',
            $_POST['time'] ?? '',
            $_POST['repeat'] === 'on' || $_POST['repeat'] === 'true',
            $_POST['frequency'] ?? null,
            $weekdays,
            $_POST['repeatEnd'] ?? 'never',
            !empty($_POST['repeatCount']) ? (int)$_POST['repeatCount'] : null,
            !empty($_POST['repeatEndDate']) ? $_POST['repeatEndDate'] : null,
            $_POST['eventColor'] ?? '#3498db'
        );
        
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "Result: " . json_encode($result) . "\n", 
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "=== END ADD_SCHEDULE AJAX DEBUG ===\n", 
            FILE_APPEND | LOCK_EX);
        
        die(json_encode($result));
        break;
        
    case 'update_timezone':
        $branchId = (int)($_POST['branch_id'] ?? 0);
        $timezoneName = $_POST['timezone_name'] ?? '';
        
        if (!$branchId || empty($timezoneName)) {
            http_response_code(400);
            die(json_encode(['success' => false, 'error' => 'Не все обязательные поля заполнены']));
        }
        
        try {
            $timezoneManager = new \Artmax\Calendar\TimezoneManager();
            
            $timezoneData = [
                'timezone_name' => $timezoneName,
                'timezone_offset' => 0, // Автоматически определяется из timezone_name
                'dst_enabled' => 0, // Отключаем DST
                'dst_start_month' => 3,
                'dst_start_day' => 1,
                'dst_start_hour' => 2,
                'dst_end_month' => 10,
                'dst_end_day' => 1,
                'dst_end_hour' => 3
            ];
            
            $result = $timezoneManager->updateBranchTimezone($branchId, $timezoneData);
            
            if ($result) {
                die(json_encode(['success' => true, 'message' => 'Часовой пояс обновлен']));
            } else {
                die(json_encode(['success' => false, 'error' => 'Ошибка обновления часового пояса']));
            }
        } catch (Exception $e) {
            die(json_encode(['success' => false, 'error' => 'Ошибка: ' . $e->getMessage()]));
        }
        break;
        
    default:
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "Unknown action: {$action}\n", 
            FILE_APPEND | LOCK_EX);
        http_response_code(400);
        die(json_encode(['success' => false, 'error' => 'Неизвестное действие']));
}

        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "=== END AJAX REQUEST ===\n", 
            FILE_APPEND | LOCK_EX);
        
        // Если дошли до сюда, что-то пошло не так
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "Reached end of file - unexpected\n", 
            FILE_APPEND | LOCK_EX);
        http_response_code(500);
        die(json_encode(['success' => false, 'error' => 'Неожиданная ошибка']));
