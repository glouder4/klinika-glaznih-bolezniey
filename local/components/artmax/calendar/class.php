<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();




use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class ArtmaxCalendarComponent extends CBitrixComponent{

    public function executeComponent()
    {
        // Проверяем, является ли это AJAX запросом
        if ($this->isAjaxRequest()) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "EXECUTE: AJAX request detected, calling handleAjaxRequest()\n\n", 
                FILE_APPEND | LOCK_EX);
            $this->handleAjaxRequest();
            // Принудительно завершаем выполнение для AJAX запросов
            die();
        } else {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "EXECUTE: Not an AJAX request, continuing with normal execution\n\n", 
                FILE_APPEND | LOCK_EX);
        }

        // Проверяем существование модуля
        if (!CModule::IncludeModule('artmax.calendar')) {
            ShowError('Модуль artmax.calendar не установлен');
            return;
        }

        // Получаем параметры
        $branchId = (int)($this->arParams['BRANCH_ID'] ?? 1);
        $eventsCount = (int)($this->arParams['EVENTS_COUNT'] ?? 20);
        $showForm = $this->arParams['SHOW_FORM'] === 'Y';

        try {
            // Получаем информацию о филиале
            $branchObj = new \Artmax\Calendar\Branch();
            $branch = $branchObj->getBranch($branchId);

            if (!$branch) {
                ShowError('Филиал не найден');
                return;
            }

            // Получаем события для филиала
            $calendarObj = new \Artmax\Calendar\Calendar();
            $events = $calendarObj->getEventsByBranch($branchId, null, null, null, $eventsCount);

            // Получаем список всех филиалов для навигации
            $allBranches = $branchObj->getBranches();
        } catch (Exception $e) {
            ShowError('Ошибка при инициализации календаря: ' . $e->getMessage());
            return;
        }

        // Формируем данные для шаблона
        $this->arResult = [
            'BRANCH' => $branch,
            'EVENTS' => $events,
            'ALL_BRANCHES' => $allBranches,
            'SHOW_FORM' => $showForm,
            'CURRENT_USER_ID' => $GLOBALS['USER'] ? $GLOBALS['USER']->GetID() : 0,
            'CAN_ADD_EVENTS' => $GLOBALS['USER'] ? $GLOBALS['USER']->IsAuthorized() : false,
        ];

        // Подключаем шаблон
        $this->includeComponentTemplate();
    }

    /**
     * Проверка, является ли запрос AJAX
     */
    private function isAjaxRequest()
    {
        // Отладочная информация в файл
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'POST_action' => $_POST['action'] ?? 'NOT SET',
            'HTTP_X_REQUESTED_WITH' => $_SERVER['HTTP_X_REQUESTED_WITH'] ?? 'NOT SET',
            'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'NOT SET',
            'POST_data' => $_POST
        ];
        
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            json_encode($logData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n", 
            FILE_APPEND | LOCK_EX);
        
        $isAjax = isset($_POST['action']) || 
                  (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                   strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "RESULT: isAjaxRequest() returned: " . ($isAjax ? 'TRUE' : 'FALSE') . "\n\n", 
            FILE_APPEND | LOCK_EX);
        
        return $isAjax;
    }

    /**
     * Обработка AJAX запросов
     */
    private function handleAjaxRequest()
    {
        // Включаем обработку ошибок
        set_error_handler(function($severity, $message, $file, $line) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "ERROR_HANDLER: Severity=$severity, Message=$message, File=$file, Line=$line\n", 
                FILE_APPEND | LOCK_EX);
        });
        
        // Включаем обработку исключений
        set_exception_handler(function($exception) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "EXCEPTION_HANDLER: " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine() . "\n", 
                FILE_APPEND | LOCK_EX);
        });
        
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "HANDLE_AJAX: Starting handleAjaxRequest, action = " . ($_POST['action'] ?? 'NOT SET') . "\n", 
            FILE_APPEND | LOCK_EX);
        
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'addEvent':
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "HANDLE_AJAX: Processing addEvent case\n", 
                    FILE_APPEND | LOCK_EX);
                
                $result = $this->addEventAction(
                    $_POST['title'] ?? '',
                    $_POST['description'] ?? '',
                    $_POST['dateFrom'] ?? '',
                    $_POST['dateTo'] ?? '',
                    (int)($_POST['branchId'] ?? 1)
                );
                
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "HANDLE_AJAX: addEventAction result = " . json_encode($result) . "\n", 
                    FILE_APPEND | LOCK_EX);
                break;
                
            case 'addSchedule':
                // Правильно обрабатываем массив weekdays
                $weekdays = [];
                if (isset($_POST['weekdays']) && is_array($_POST['weekdays'])) {
                    $weekdays = array_map('intval', $_POST['weekdays']);
                }
                
                $result = $this->addScheduleAction(
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
                break;
                
            case 'deleteEvent':
                $result = $this->deleteEventAction(
                    (int)($_POST['eventId'] ?? 0)
                );
                break;
                
            case 'getEvents':
                $result = $this->getEventsAction(
                    (int)($_POST['branchId'] ?? 1),
                    $_POST['dateFrom'] ?? null,
                    $_POST['dateTo'] ?? null
                );
                break;
                
            default:
                $result = ['success' => false, 'error' => 'Неизвестное действие'];
        }
        
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "HANDLE_AJAX: About to send JSON response: " . json_encode($result) . "\n", 
            FILE_APPEND | LOCK_EX);
        
        // Отправляем JSON ответ
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "HANDLE_AJAX: Setting Content-Type header\n", 
            FILE_APPEND | LOCK_EX);
        
        header('Content-Type: application/json');
        
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "HANDLE_AJAX: Echoing JSON response\n", 
            FILE_APPEND | LOCK_EX);
        
        echo json_encode($result);
        
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "HANDLE_AJAX: Calling exit()\n", 
            FILE_APPEND | LOCK_EX);
        
        exit;
        die();
    }



    /**
     * Добавление события
     */
    public function addEventAction($title, $description, $dateFrom, $dateTo, $branchId)
    {
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "ADD_EVENT: Starting addEventAction with params: " . json_encode([
                'title' => $title,
                'description' => $description,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'branchId' => $branchId
            ]) . "\n", 
            FILE_APPEND | LOCK_EX);
        
        if (!$GLOBALS['USER'] || !$GLOBALS['USER']->IsAuthorized()) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "ADD_EVENT: User not authorized\n", 
                FILE_APPEND | LOCK_EX);
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "ADD_EVENT: Including module artmax.calendar\n", 
                FILE_APPEND | LOCK_EX);
            
            if (!CModule::IncludeModule('artmax.calendar')) {
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "ADD_EVENT: Module not found\n", 
                    FILE_APPEND | LOCK_EX);
                return ['success' => false, 'error' => 'Модуль artmax.calendar не установлен'];
            }
            
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "ADD_EVENT: Creating Calendar object\n", 
                FILE_APPEND | LOCK_EX);
            
            $calendarObj = new \Artmax\Calendar\Calendar();
            $userId = $GLOBALS['USER']->GetID();
            
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "ADD_EVENT: User ID = " . $userId . "\n", 
                FILE_APPEND | LOCK_EX);

            // Проверяем доступность времени
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "ADD_EVENT: Checking time availability\n", 
                FILE_APPEND | LOCK_EX);
            
            if (!$calendarObj->isTimeAvailable($dateFrom, $dateTo, $userId)) {
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "ADD_EVENT: Time not available\n", 
                    FILE_APPEND | LOCK_EX);
                return ['success' => false, 'error' => 'Время уже занято'];
            }

            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "ADD_EVENT: Adding event to database\n", 
                FILE_APPEND | LOCK_EX);
            
            $eventId = $calendarObj->addEvent($title, $description, $dateFrom, $dateTo, $userId, $branchId, '#3498db');
            
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "ADD_EVENT: Event ID returned = " . ($eventId ?: 'FALSE') . "\n", 
                FILE_APPEND | LOCK_EX);

            if ($eventId) {
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "ADD_EVENT: Success, returning eventId = " . $eventId . "\n", 
                    FILE_APPEND | LOCK_EX);
                return ['success' => true, 'eventId' => $eventId];
            } else {
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "ADD_EVENT: Failed to add event\n", 
                    FILE_APPEND | LOCK_EX);
                return ['success' => false, 'error' => 'Ошибка добавления события'];
            }
        } catch (\Exception $e) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "ADD_EVENT: Exception caught: " . $e->getMessage() . "\n", 
                FILE_APPEND | LOCK_EX);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Удаление события
     */
    public function deleteEventAction($eventId)
    {
        if (!$GLOBALS['USER'] || !$GLOBALS['USER']->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            if (!CModule::IncludeModule('artmax.calendar')) {
                return ['success' => false, 'error' => 'Модуль artmax.calendar не установлен'];
            }
            
            $calendarObj = new \Artmax\Calendar\Calendar();
            $event = $calendarObj->getEvent($eventId);

            if (!$event) {
                return ['success' => false, 'error' => 'Событие не найдено'];
            }

            // Проверяем права на удаление (только автор события)
            if ($event['USER_ID'] != $GLOBALS['USER']->GetID()) {
                return ['success' => false, 'error' => 'Нет прав на удаление'];
            }

            $result = $calendarObj->deleteEvent($eventId);

            if ($result) {
                return ['success' => true];
            } else {
                return ['success' => false, 'error' => 'Ошибка удаления события'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Получение событий
     */
    public function getEventsAction($branchId, $dateFrom = null, $dateTo = null)
    {
        try {
            if (!CModule::IncludeModule('artmax.calendar')) {
                return ['success' => false, 'error' => 'Модуль artmax.calendar не установлен'];
            }
            
            $calendarObj = new \Artmax\Calendar\Calendar();
            $events = $calendarObj->getEventsByBranch($branchId, $dateFrom, $dateTo);

            return ['success' => true, 'events' => $events];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Добавление расписания
     */
    public function addScheduleAction($title, $date, $time, $repeat = false, $frequency = null, $weekdays = [], $repeatEnd = 'never', $repeatCount = null, $repeatEndDate = null, $eventColor = '#3498db')
    {
        if (!$GLOBALS['USER'] || !$GLOBALS['USER']->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            if (!CModule::IncludeModule('artmax.calendar')) {
                return ['success' => false, 'error' => 'Модуль artmax.calendar не установлен'];
            }
            
            $calendarObj = new \Artmax\Calendar\Calendar();
            $userId = $GLOBALS['USER']->GetID();
            
            // Формируем дату и время
            $dateTime = $date . ' ' . $time;
            $dateFrom = new \DateTime($dateTime);
            $dateTo = clone $dateFrom;
            $dateTo->add(new \DateInterval('PT1H')); // Добавляем 1 час по умолчанию

            // Проверяем доступность времени
            if (!$calendarObj->isTimeAvailable($dateFrom->format('Y-m-d H:i:s'), $dateTo->format('Y-m-d H:i:s'), $userId)) {
                return ['success' => false, 'error' => 'Время уже занято'];
            }

            // Создаем событие
            $eventId = $calendarObj->addEvent($title, '', $dateFrom->format('Y-m-d H:i:s'), $dateTo->format('Y-m-d H:i:s'), $userId, 1, $eventColor);

            if ($eventId) {
                // Если событие повторяемое, создаем повторения
                if ($repeat && $frequency) {
                    $this->createRecurringEvents($eventId, $frequency, $weekdays, $repeatEnd, $repeatCount, $repeatEndDate, $eventColor);
                }

                return ['success' => true, 'eventId' => $eventId];
            } else {
                return ['success' => false, 'error' => 'Ошибка добавления расписания'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Создание повторяющихся событий
     */
    private function createRecurringEvents($originalEventId, $frequency, $weekdays = [], $repeatEnd = 'never', $repeatCount = null, $repeatEndDate = null, $eventColor = '#3498db')
    {
        try {
            if (!CModule::IncludeModule('artmax.calendar')) {
                return false;
            }
            
            $calendarObj = new \Artmax\Calendar\Calendar();
            $originalEvent = $calendarObj->getEvent($originalEventId);
            
            if (!$originalEvent) {
                return false;
            }

            $dateFrom = new \DateTime($originalEvent['DATE_FROM']);
            $dateTo = new \DateTime($originalEvent['DATE_TO']);
            $duration = $dateFrom->diff($dateTo);

            $eventsCreated = 0;
            $maxEvents = ($repeatEnd === 'after' && $repeatCount) ? $repeatCount : 100; // Максимум 100 событий
            $endDate = ($repeatEnd === 'date' && $repeatEndDate) ? new \DateTime($repeatEndDate) : null;

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
                        if (!empty($weekdays)) {
                            // Для еженедельного повторения с выбранными днями недели
                            $nextWeekday = $this->getNextWeekday($dateFrom, $weekdays);
                            if ($nextWeekday) {
                                $newDateFrom = $nextWeekday;
                                $newDateTo = clone $nextWeekday;
                                $newDateTo->add($duration);
                            } else {
                                break; // Пропускаем, если нет подходящего дня недели
                            }
                        } else {
                            $newDateFrom->add(new \DateInterval('P' . ($i * 7) . 'D'));
                            $newDateTo->add(new \DateInterval('P' . ($i * 7) . 'D'));
                        }
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

            return $eventsCreated;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Получение следующего дня недели для еженедельного повторения
     */
    private function getNextWeekday($startDate, $weekdays)
    {
        $currentDate = clone $startDate;
        
        for ($i = 1; $i <= 7; $i++) {
            $currentDate->add(new \DateInterval('P1D'));
            $currentWeekday = $currentDate->format('N'); // 1 (понедельник) - 7 (воскресенье)
            
            if (in_array($currentWeekday, $weekdays)) {
                return $currentDate;
            }
        }
        
        return null;
    }
}