<?php
/**
 * AJAX endpoint для модуля ArtMax Calendar
 * Этот файл копируется в корень сайта при установке модуля
 */

// Подключаем Bitrix
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

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
        $title = $_POST['title'] ?? '';
        $date = $_POST['date'] ?? '';
        $time = $_POST['time'] ?? '';
        $repeat = $_POST['repeat'] === 'on' || $_POST['repeat'] === 'true';
        $frequency = $_POST['frequency'] ?? null;
        $weekdays = [];
        if (isset($_POST['weekdays']) && is_array($_POST['weekdays'])) {
            $weekdays = array_map('intval', $_POST['weekdays']);
        }
        $repeatEnd = $_POST['repeatEnd'] ?? 'never';
        $repeatCount = !empty($_POST['repeatCount']) ? (int)$_POST['repeatCount'] : null;
        $repeatEndDate = $_POST['repeatEndDate'] ?? null;
        $eventColor = $_POST['eventColor'] ?? '#3498db';
        
        if (empty($title) || empty($date) || empty($time)) {
            http_response_code(400);
            die(json_encode(['success' => false, 'error' => 'Не все обязательные поля заполнены']));
        }
        
        $userId = $GLOBALS['USER']->GetID();
        
        // Формируем дату и время
        $dateTime = $date . ' ' . $time;
        $dateFrom = new DateTime($dateTime);
        $dateTo = clone $dateFrom;
        $dateTo->add(new DateInterval('PT1H')); // Добавляем 1 час по умолчанию
        
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
