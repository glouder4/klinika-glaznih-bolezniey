<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();




use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class ArtmaxCalendarComponent extends CBitrixComponent{

    public function executeComponent()
    {
        // Проверяем, является ли это AJAX запросом
        if ($this->isAjaxRequest()) {
            $this->handleAjaxRequest();
            // Принудительно завершаем выполнение для AJAX запросов
            die();
        } else {
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

            // Группируем события по датам для отображения в календаре
            $eventsByDate = [];
            foreach ($events as $event) {
                $dateKey = date('Y-m-d', strtotime($this->convertRussianDateToStandard($event['DATE_FROM'])));
                if (!isset($eventsByDate[$dateKey])) {
                    $eventsByDate[$dateKey] = [];
                }
                $eventsByDate[$dateKey][] = $event;
            }

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
            'EVENTS_BY_DATE' => $eventsByDate,
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
        
        $isAjax = isset($_POST['action']) || 
                  (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                   strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        
        return $isAjax;
    }

    /**
     * Обработка AJAX запросов
     */
    private function handleAjaxRequest()
    {
        
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'addEvent':
                
                $result = $this->addEventAction(
                    $_POST['title'] ?? '',
                    $_POST['description'] ?? '',
                    $_POST['dateFrom'] ?? '',
                    $_POST['dateTo'] ?? '',
                    (int)($_POST['branchId'] ?? 1),
                    $_POST['eventColor'] ?? '#3498db'
                );
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
                
            case 'getEvent':
                $result = $this->getEventAction(
                    (int)($_POST['eventId'] ?? 0)
                );
                break;
                
            case 'updateEvent':
                $result = $this->updateEventAction(
                    (int)($_POST['eventId'] ?? 0),
                    $_POST['title'] ?? '',
                    $_POST['description'] ?? '',
                    $_POST['dateFrom'] ?? '',
                    $_POST['dateTo'] ?? '',
                    $_POST['eventColor'] ?? '#3498db',
                    (int)($_POST['branchId'] ?? 1)
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

        
        header('Content-Type: application/json');
        
        echo json_encode($result);
        
        exit;
        die();
    }



    /**
     * Добавление события
     */
    public function addEventAction($title, $description, $dateFrom, $dateTo, $branchId, $eventColor = '#3498db')
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

            // Проверяем доступность времени
            
            if (!$calendarObj->isTimeAvailable($dateFrom, $dateTo, $userId)) {
                return ['success' => false, 'error' => 'Время уже занято'];
            }
            
            $eventId = $calendarObj->addEvent($title, $description, $dateFrom, $dateTo, $userId, $branchId, $eventColor);

            if ($eventId) {
                return ['success' => true, 'eventId' => $eventId];
            } else {
                return ['success' => false, 'error' => 'Ошибка добавления события'];
            }
        } catch (\Exception $e) {
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
     * Получение события по ID
     */
    public function getEventAction($eventId)
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

            // Проверяем права на просмотр (только автор события)
            if ($event['USER_ID'] != $GLOBALS['USER']->GetID()) {
                return ['success' => false, 'error' => 'Нет прав на просмотр'];
            }

            return ['success' => true, 'event' => $event];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Обновление события
     */
    public function updateEventAction($eventId, $title, $description, $dateFrom, $dateTo, $eventColor, $branchId)
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

            // Получаем существующее событие для проверки прав
            $existingEvent = $calendarObj->getEvent($eventId);
            if (!$existingEvent) {
                return ['success' => false, 'error' => 'Событие не найдено'];
            }

            // Проверяем права на редактирование (только автор события)
            if ($existingEvent['USER_ID'] != $userId) {
                return ['success' => false, 'error' => 'Нет прав на редактирование'];
            }

            // Проверяем доступность времени (исключая текущее событие)
            if (!$calendarObj->isTimeAvailable($dateFrom, $dateTo, $userId, $eventId)) {
                return ['success' => false, 'error' => 'Время уже занято'];
            }

            // Обновляем событие
            $result = $calendarObj->updateEvent($eventId, $title, $description, $dateFrom, $dateTo, $eventColor, $branchId);

            if ($result) {
                return ['success' => true];
            } else {
                return ['success' => false, 'error' => 'Ошибка обновления события'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
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

    /**
     * Конвертирует дату из российского формата (день.месяц.год) в стандартный (год-месяц-день)
     * @param string $dateString Дата в формате "04.08.2025 09:00:00"
     * @return string Дата в формате "2025-08-04 09:00:00"
     */
    private function convertRussianDateToStandard($dateString)
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
}