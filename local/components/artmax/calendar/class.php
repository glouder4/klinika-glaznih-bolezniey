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

            // Получаем события для филиала с ограничениями по дате
            $calendarObj = new \Artmax\Calendar\Calendar();
            
            // Получаем текущий месяц и год
            $currentYear = date('Y');
            $currentMonth = date('n');
            
            // Формируем диапазон дат для текущего месяца
            $dateFrom = sprintf('%04d-%02d-01', $currentYear, $currentMonth);
            $lastDay = date('t', mktime(0, 0, 0, $currentMonth, 1, $currentYear));
            $dateTo = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $lastDay);
            
            $events = $calendarObj->getEventsByBranch($branchId, $dateFrom, $dateTo, null, null); // Убираем ограничение для статической загрузки
            
            // Отладочная информация
            error_log("=== STATIC LOAD START ===");
            error_log("STATIC LOAD: dateFrom=$dateFrom, dateTo=$dateTo, events count=" . count($events));
            error_log("STATIC LOAD: currentYear=$currentYear, currentMonth=$currentMonth, lastDay=$lastDay");

            // Группируем события по датам для отображения в календаре
            $eventsByDate = [];
            foreach ($events as $event) {
                $convertedDate = $this->convertRussianDateToStandard($event['DATE_FROM']);
                $dateKey = date('Y-m-d', strtotime($convertedDate));
                error_log("STATIC LOAD: event ID={$event['ID']}, original DATE_FROM={$event['DATE_FROM']}, converted={$convertedDate}, dateKey={$dateKey}");
                if (!isset($eventsByDate[$dateKey])) {
                    $eventsByDate[$dateKey] = [];
                }
                $eventsByDate[$dateKey][] = $event;
            }
            
            error_log("STATIC LOAD: eventsByDate keys=" . implode(', ', array_keys($eventsByDate)));
            error_log("=== STATIC LOAD END ===");

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
                    $_POST['employee_id'] ?? null,
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
                
            case 'searchClients':
                $result = $this->searchClientsAction(
                    $_POST['query'] ?? '',
                    $_POST['type'] ?? 'contact'
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

            // Проверяем доступность времени для врача
            if (!$calendarObj->isTimeAvailableForDoctor($dateFrom, $dateTo, $employeeId)) {
                return ['success' => false, 'error' => 'Время уже занято для выбранного врача'];
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
    public function addScheduleAction($title, $date, $time, $employeeId = null, $repeat = false, $frequency = null, $weekdays = [], $repeatEnd = 'never', $repeatCount = null, $repeatEndDate = null, $eventColor = '#3498db')
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

            $eventsCreated = 0;
            $createdEvents = [];
            
            // Если событие повторяемое, создаем все события (включая первое)
            if ($repeat && $frequency) {
                // Логируем параметры для отладки
                error_log("addScheduleAction: date = $date, time = $time, dateFrom = " . $dateFrom->format('Y-m-d H:i:s'));
                
                error_log("addScheduleAction: Calling createRecurringEvents with frequency = $frequency, weekdays = " . implode(',', $weekdays));
                $recurringResult = $this->createRecurringEvents(null, $frequency, $weekdays, $repeatEnd, $repeatCount, $repeatEndDate, $eventColor, $employeeId, $dateFrom->format('Y-m-d H:i:s'), $title, $dateFrom->format('Y-m-d H:i:s'), $dateTo->format('Y-m-d H:i:s'), $userId);
                error_log("addScheduleAction: createRecurringEvents returned: " . json_encode($recurringResult));
                if ($recurringResult && $recurringResult['count'] > 0) {
                    $eventsCreated = $recurringResult['count'];
                    
                    // Получаем все созданные события
                    foreach ($recurringResult['ids'] as $eventId) {
                        $event = $calendarObj->getEvent($eventId);
                        if ($event) {
                            $event['EVENT_COLOR'] = $eventColor;
                            // Конвертируем даты в стандартный формат
                            $event['DATE_FROM'] = $this->convertRussianDateToStandard($event['DATE_FROM']);
                            $event['DATE_TO'] = $this->convertRussianDateToStandard($event['DATE_TO']);
                            $createdEvents[] = $event;
                        }
                    }
                }
            } else {
                // Если событие не повторяемое, создаем только одно событие
                if ($calendarObj->isTimeAvailableForDoctor($dateFrom->format('Y-m-d H:i:s'), $dateTo->format('Y-m-d H:i:s'), $employeeId)) {
                    $eventId = $calendarObj->addEvent($title, '', $dateFrom->format('Y-m-d H:i:s'), $dateTo->format('Y-m-d H:i:s'), $userId, 1, $eventColor, $employeeId);
                    if ($eventId) {
                        $eventsCreated = 1;
                        $event = $calendarObj->getEvent($eventId);
                        if ($event) {
                            $event['EVENT_COLOR'] = $eventColor;
                            // Конвертируем даты в стандартный формат
                            $event['DATE_FROM'] = $this->convertRussianDateToStandard($event['DATE_FROM']);
                            $event['DATE_TO'] = $this->convertRussianDateToStandard($event['DATE_TO']);
                            $createdEvents[] = $event;
                        }
                    }
                }
            }

            // Логируем результат
            error_log("addScheduleAction: eventsCreated = $eventsCreated, createdEvents count = " . count($createdEvents));
            error_log("addScheduleAction: result = " . json_encode($result));
            
            // Возвращаем результат
            if ($eventsCreated > 0) {
                error_log("addScheduleAction: Returning success with $eventsCreated events");
                return [
                    'success' => true, 
                    'eventId' => $mainEventId, 
                    'eventsCreated' => $eventsCreated,
                    'events' => $createdEvents
                ];
            } else {
                error_log("addScheduleAction: Returning error - no events created");
                return [
                    'success' => false, 
                    'error' => 'Все выбранные времена заняты, расписание не создано'
                ];
            }
        } catch (\Exception $e) {
            error_log("addScheduleAction error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Ошибка создания расписания: ' . $e->getMessage()];
        }
    }

    /**
     * Создание повторяющихся событий
     */
    private function createRecurringEvents($originalEventId, $frequency, $weekdays = [], $repeatEnd = 'never', $repeatCount = null, $repeatEndDate = null, $eventColor = '#3498db', $employeeId = null, $scheduleStartDate = null, $title = null, $dateFrom = null, $dateTo = null, $userId = null)
    {
        try {
            error_log("createRecurringEvents: Starting with frequency = $frequency, weekdays = " . implode(',', $weekdays));
            if (!CModule::IncludeModule('artmax.calendar')) {
                return ['count' => 0, 'ids' => []];
            }
            
            $calendarObj = new \Artmax\Calendar\Calendar();
            
            // Если переданы параметры для создания событий напрямую
            if ($title && $dateFrom && $dateTo && $userId) {
                $eventDateFrom = new \DateTime($dateFrom);
                $eventDateTo = new \DateTime($dateTo);
                $duration = $eventDateFrom->diff($eventDateTo);
                $startDate = $scheduleStartDate ? new \DateTime($scheduleStartDate) : $eventDateFrom;
            } else {
                // Старая логика для обратной совместимости
                $originalEvent = $calendarObj->getEvent($originalEventId);
                if (!$originalEvent) {
                    return ['count' => 0, 'ids' => []];
                }
                $eventDateFrom = new \DateTime($originalEvent['DATE_FROM']);
                $eventDateTo = new \DateTime($originalEvent['DATE_TO']);
                $duration = $eventDateFrom->diff($eventDateTo);
                $startDate = $scheduleStartDate ? new \DateTime($scheduleStartDate) : $eventDateFrom;
                $title = $originalEvent['TITLE'];
                $userId = $originalEvent['USER_ID'];
            }

            $eventsCreated = 0;
            $createdEventIds = [];
            
            // Для еженедельного расписания с выбранными днями недели
            if ($frequency === 'weekly' && !empty($weekdays)) {
                if ($repeatCount && $repeatCount > 0) {
                    // Для еженедельного повторения используем количество недель
                    $maxWeeks = $repeatCount;
                    $maxEvents = $repeatCount * count($weekdays);
                } else {
                    // Для бесконечного повторения
                    $maxWeeks = 100; // Максимум 100 недель для бесконечного повторения
                    $maxEvents = 100 * count($weekdays); // Максимум 100 событий для бесконечного повторения
                }
            } else {
                if ($repeatCount && $repeatCount > 0) {
                    // Если указано количество повторений, используем его
                    $maxEvents = $repeatCount;
                } else {
                    // Для бесконечного повторения
                    $maxEvents = 100; // Максимум 100 событий для бесконечного повторения
                }
            }
            
            $endDate = ($repeatEnd === 'date' && $repeatEndDate) ? new \DateTime($repeatEndDate) : null;
            
            // Логируем параметры для отладки
            error_log("createRecurringEvents: repeatEnd = $repeatEnd, repeatCount = $repeatCount, maxEvents = $maxEvents");

            if ($frequency === 'weekly' && !empty($weekdays)) {
                // Специальная обработка для еженедельного повторения с выбранными днями недели
                // Используем дату начала расписания как базовую дату
                $currentDate = clone $startDate;
                $weekNumber = 0;
                
                // Логируем параметры для отладки
                error_log("createRecurringEvents: maxWeeks = $maxWeeks, maxEvents = $maxEvents, weekdays = " . implode(',', $weekdays));
                error_log("createRecurringEvents: startDate = " . $startDate->format('Y-m-d H:i:s'));
                error_log("createRecurringEvents: eventDateFrom = " . $eventDateFrom->format('Y-m-d H:i:s'));
                
                // Используем количество недель из параметров
                $weekNumber = 0; // Начинаем с недели 0 (первая неделя - та, на которую приходится дата начала)

                while ($weekNumber < $maxWeeks) {
                    // Логируем текущую неделю
                    error_log("createRecurringEvents: weekNumber = $weekNumber, eventsCreated = $eventsCreated, currentDate = " . $currentDate->format('Y-m-d'));
                    
                    // Находим понедельник недели, в которой находится currentDate
                    $dayOfWeek = $currentDate->format('N'); // 1 = понедельник, 7 = воскресенье
                    $mondayOffset = $dayOfWeek - 1;
                    $weekStart = clone $currentDate;
                    $weekStart->sub(new \DateInterval('P' . $mondayOffset . 'D'));
                    
                    // Логируем начало недели
                    error_log("createRecurringEvents: weekStart = " . $weekStart->format('Y-m-d') . " (Monday of week containing " . $currentDate->format('Y-m-d') . ")");
                    
                    // Создаем события для каждого выбранного дня недели в текущей неделе
                    foreach ($weekdays as $weekday) {
                        $eventDate = clone $weekStart;
                        $eventDate->add(new \DateInterval('P' . ($weekday - 1) . 'D'));
                        
                        // Логируем проверяемую дату
                        error_log("createRecurringEvents: Checking weekday $weekday, eventDate = " . $eventDate->format('Y-m-d') . ", startDate = " . $startDate->format('Y-m-d'));
                        
                        // Проверяем, что дата события не раньше даты начала расписания
                        if ($eventDate >= $startDate) {
                            error_log("createRecurringEvents: Date " . $eventDate->format('Y-m-d') . " >= startDate " . $startDate->format('Y-m-d') . " - proceeding with event creation");
                            // Проверяем ограничение по дате
                            if ($endDate && $eventDate > $endDate) {
                                break 2; // Выходим из обоих циклов
                            }
                            
                            // Дополнительная проверка по количеству событий (защита от переполнения)
                            if ($eventsCreated >= $maxEvents) {
                                break 2; // Выходим из обоих циклов
                            }
                            
                            $eventDateTo = clone $eventDate;
                            $eventDateTo->add($duration);
                            
                            
                            // Проверяем доступность времени для события
                            if ($calendarObj->isTimeAvailableForDoctor($eventDate->format('Y-m-d H:i:s'), $eventDateTo->format('Y-m-d H:i:s'), $employeeId)) {
                                // Создаем событие
                                error_log("createRecurringEvents: Creating event for date " . $eventDate->format('Y-m-d H:i:s'));
                                $recurringEventId = $calendarObj->addEvent(
                                    $title,
                                    '', // Описание пустое для расписания
                                    $eventDate->format('Y-m-d H:i:s'),
                                    $eventDateTo->format('Y-m-d H:i:s'),
                                    $userId,
                                    1,
                                    $eventColor,
                                    $employeeId
                                );

                                if ($recurringEventId) {
                                    $eventsCreated++;
                                    $createdEventIds[] = $recurringEventId;
                                    error_log("createRecurringEvents: Event created with ID $recurringEventId, total events: $eventsCreated");
                                }
                            } else {
                                error_log("createRecurringEvents: Time not available for " . $eventDate->format('Y-m-d H:i:s'));
                            }
                            // Если время занято, просто пропускаем этот день
                        } else {
                            error_log("createRecurringEvents: Date " . $eventDate->format('Y-m-d') . " < startDate " . $startDate->format('Y-m-d') . " - skipping this date");
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
                    if ($calendarObj->isTimeAvailableForDoctor($newDateFrom->format('Y-m-d H:i:s'), $newDateTo->format('Y-m-d H:i:s'), $employeeId)) {
                        // Создаем событие
                        $recurringEventId = $calendarObj->addEvent(
                            $title,
                            '', // Описание пустое для расписания
                            $newDateFrom->format('Y-m-d H:i:s'),
                            $newDateTo->format('Y-m-d H:i:s'),
                            $userId,
                            1,
                            $eventColor,
                            $employeeId
                        );

                        if ($recurringEventId) {
                            $eventsCreated++;
                            $createdEventIds[] = $recurringEventId;
                        }
                    }
                    // Если время занято, просто пропускаем этот день
                }
            }

            return ['count' => $eventsCreated, 'ids' => $createdEventIds];
        } catch (\Exception $e) {
            return ['count' => 0, 'ids' => []];
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

            // Проверяем доступность времени для врача (исключая текущее событие)
            if (!$calendarObj->isTimeAvailableForDoctor($dateFrom, $dateTo, $employeeId, $eventId)) {
                return ['success' => false, 'error' => 'Время уже занято для выбранного врача'];
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
    private function getNextWeekday($startDate, $weekdays, $iteration = 1)
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

    /**
     * Поиск клиентов в Bitrix 24 CRM
     * Использует стандартный сервис crm.api.entity.search
     */
    public function searchClientsAction($query, $type = 'contact')
    {
        if (!$GLOBALS['USER'] || !$GLOBALS['USER']->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            $clients = [];
            
            if (strlen($query) >= 2) {
                // Подключаем модуль CRM
                if (!CModule::IncludeModule('crm')) {
                    return ['success' => false, 'error' => 'Модуль CRM не установлен'];
                }
                
                // Используем стандартный сервис для поиска
                if ($type === 'deal') {
                    $clients = $this->searchDealsViaStandardService($query);
                } else {
                    $clients = $this->searchContactsViaStandardService($query);
                }
            }
            
            return ['success' => true, 'clients' => $clients];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Поиск контактов через прямой запрос к CCrmContact
     */
    private function searchContactsViaStandardService($query)
    {
        // Сразу используем прямой поиск через CCrmContact
        return $this->searchContactsViaDirectQuery($query);
    }
    
    /**
     * Поиск сделок через прямой запрос к CCrmDeal
     */
    private function searchDealsViaStandardService($query)
    {
        // Сразу используем прямой поиск через CCrmDeal
        return $this->searchDealsViaDirectQuery($query);
    }
    
    /**
     * Fallback поиск сделок через прямой запрос к CCrmDeal
     */
    private function searchDealsViaDirectQuery($query)
    {
        $deals = [];
        
        try {
            // Подготавливаем фильтр
            $arFilter = [];
            $searchParts = preg_split('/[\s]+/', $query, 2, PREG_SPLIT_NO_EMPTY);
            
            if (count($searchParts) < 2) {
                $arFilter['LOGIC'] = 'OR';
                $arFilter['%TITLE'] = $query;
                $arFilter['%COMPANY_TITLE'] = $query;
            } else {
                $arFilter['LOGIC'] = 'OR';
                $arFilter["__INNER_FILTER_TITLE_1"] = ['%TITLE' => $searchParts[0], '%TITLE' => $searchParts[1]];
                $arFilter["__INNER_FILTER_COMPANY_1"] = ['%COMPANY_TITLE' => $searchParts[0], '%COMPANY_TITLE' => $searchParts[1]];
            }
            
            $arSelect = [
                'ID', 'TITLE', 'OPPORTUNITY', 'STAGE_ID', 'COMPANY_TITLE', 'CURRENCY_ID'
            ];
            
            $arOrder = ['TITLE' => 'ASC'];
            
            // Выполняем поиск
            $dbDeals = \CCrmDeal::GetListEx($arOrder, $arFilter, false, ['nTopCount' => 10], $arSelect);
            
            while ($deal = $dbDeals->Fetch()) {
                $deals[] = [
                    'id' => $deal['ID'],
                    'title' => $deal['TITLE'] ?: 'Сделка #' . $deal['ID'],
                    'amount' => $deal['OPPORTUNITY'] ?: '',
                    'stage' => $deal['STAGE_ID'] ?: '',
                    'company' => $deal['COMPANY_TITLE'] ?: '',
                    'currency' => $deal['CURRENCY_ID'] ?: 'RUB'
                ];
            }
            
        } catch (\Exception $e) {
            error_log('Ошибка прямого поиска сделок: ' . $e->getMessage());
        }
        
        return $deals;
    }
    
    /**
     * Fallback поиск контактов через прямой запрос к CCrmContact
     */
    private function searchContactsViaDirectQuery($query)
    {
        $contacts = [];
        
        try {
            // Подготавливаем фильтр
            $arFilter = [];
            $searchParts = preg_split('/[\s]+/', $query, 2, PREG_SPLIT_NO_EMPTY);
            
            if (count($searchParts) < 2) {
                $arFilter['LOGIC'] = 'OR';
                $arFilter['%NAME'] = $query;
                $arFilter['%LAST_NAME'] = $query;
                $arFilter['%SECOND_NAME'] = $query;
                $arFilter['%EMAIL'] = $query;
                $arFilter['%PHONE'] = $query;
                $arFilter['%COMPANY_TITLE'] = $query;
            } else {
                $arFilter['LOGIC'] = 'OR';
                $arFilter["__INNER_FILTER_NAME_1"] = ['%NAME' => $searchParts[0], '%LAST_NAME' => $searchParts[1]];
                $arFilter["__INNER_FILTER_NAME_2"] = ['%LAST_NAME' => $searchParts[0], '%NAME' => $searchParts[1]];
                $arFilter["__INNER_FILTER_NAME_3"] = ['%NAME' => $searchParts[0], '%SECOND_NAME' => $searchParts[1]];
            }
            
            $arSelect = [
                'ID', 'NAME', 'SECOND_NAME', 'LAST_NAME', 
                'EMAIL', 'PHONE', 'COMPANY_TITLE', 'POST', 'ADDRESS'
            ];
            
            $arOrder = ['LAST_NAME' => 'ASC', 'NAME' => 'ASC'];
            
            // Выполняем поиск
            $dbContacts = \CCrmContact::GetListEx($arOrder, $arFilter, false, ['nTopCount' => 10], $arSelect);
            
            while ($contact = $dbContacts->Fetch()) {
                // Формируем полное имя
                $fullName = trim($contact['NAME'] . ' ' . $contact['LAST_NAME'] . ' ' . $contact['SECOND_NAME']);
                if (empty($fullName)) {
                    $fullName = 'Контакт #' . $contact['ID'];
                }
                
                $contacts[] = [
                    'id' => $contact['ID'],
                    'name' => $fullName,
                    'firstName' => $contact['NAME'],
                    'lastName' => $contact['LAST_NAME'],
                    'secondName' => $contact['SECOND_NAME'],
                    'phone' => $contact['PHONE'] ?? '',
                    'email' => $contact['EMAIL'] ?? '',
                    'company' => $contact['COMPANY_TITLE'] ?? '',
                    'post' => $contact['POST'] ?? '',
                    'address' => $contact['ADDRESS'] ?? ''
                ];
            }
            
        } catch (\Exception $e) {
            error_log('Ошибка fallback поиска контактов: ' . $e->getMessage());
        }
        
        return $contacts;
    }
    
    
    /**
     * Сохранение сделки для события
     */
    public function saveEventDealAction($eventId, $dealData)
    {
        if (!$GLOBALS['USER'] || !$GLOBALS['USER']->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            // Подключаем модуль
            if (!CModule::IncludeModule('artmax.calendar')) {
                return ['success' => false, 'error' => 'Модуль artmax.calendar не установлен'];
            }

            // Проверяем, что событие существует
            $calendar = new \Artmax\Calendar\Calendar();
            $event = $calendar->getEvent($eventId);
            
            if (!$event) {
                return ['success' => false, 'error' => 'Событие не найдено'];
            }

            // Проверяем права на редактирование события
            if ($event['USER_ID'] != $GLOBALS['USER']->GetID()) {
                return ['success' => false, 'error' => 'Нет прав на редактирование события'];
            }

            // Декодируем JSON данные сделки
            $dealDataArray = json_decode($dealData, true);
            if (!$dealDataArray || !isset($dealDataArray['id'])) {
                return ['success' => false, 'error' => 'Неверные данные сделки'];
            }
            
            // Обновляем событие, добавляя ID сделки
            $result = $calendar->updateEventDeal($eventId, $dealDataArray['id']);

            if ($result) {
                return ['success' => true, 'message' => 'Сделка сохранена'];
            } else {
                return ['success' => false, 'error' => 'Ошибка сохранения сделки'];
            }

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Получение контакта для события
     */
    public function getEventContactsAction($eventId)
    {
        if (!$GLOBALS['USER'] || !$GLOBALS['USER']->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            // Подключаем модуль
            if (!CModule::IncludeModule('artmax.calendar')) {
                return ['success' => false, 'error' => 'Модуль artmax.calendar не установлен'];
            }

            // Получаем событие с контактом
            $calendar = new \Artmax\Calendar\Calendar();
            $event = $calendar->getEvent($eventId);
            
            if (!$event) {
                return ['success' => false, 'error' => 'Событие не найдено'];
            }

            $contact = null;
            if (!empty($event['CONTACT_ENTITY_ID'])) {
                // Получаем данные контакта из CRM
                $contact = $this->getContactFromCRM($event['CONTACT_ENTITY_ID']);
            }

            return ['success' => true, 'contact' => $contact];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Получение сделки для события
     */
    public function getEventDealsAction($eventId)
    {
        if (!$GLOBALS['USER'] || !$GLOBALS['USER']->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            // Подключаем модуль
            if (!CModule::IncludeModule('artmax.calendar')) {
                return ['success' => false, 'error' => 'Модуль artmax.calendar не установлен'];
            }

            // Получаем событие со сделкой
            $calendar = new \Artmax\Calendar\Calendar();
            $event = $calendar->getEvent($eventId);
            
            if (!$event) {
                return ['success' => false, 'error' => 'Событие не найдено'];
            }

            $deal = null;
            if (!empty($event['DEAL_ENTITY_ID'])) {
                // Получаем данные сделки из CRM
                $deal = $this->getDealFromCRM($event['DEAL_ENTITY_ID']);
            }

            return ['success' => true, 'deal' => $deal];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Создание сделки для события календаря
     */
    public function createDealForEventAction($eventId, $contactId)
    {
        if (!$GLOBALS['USER'] || !$GLOBALS['USER']->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            // Подключаем модули
            if (!CModule::IncludeModule('artmax.calendar') || !CModule::IncludeModule('crm')) {
                return ['success' => false, 'error' => 'Необходимые модули не установлены'];
            }

            // Получаем данные события
            $calendar = new \Artmax\Calendar\Calendar();
            $event = $calendar->getEvent($eventId);
            
            if (!$event) {
                return ['success' => false, 'error' => 'Событие не найдено'];
            }

            // Получаем данные контакта для формирования названия сделки
            $contact = $this->getContactFromCRM($contactId);
            if (!$contact) {
                return ['success' => false, 'error' => 'Контакт не найден'];
            }

            // Формируем название сделки: Имя + Фамилия + Номер телефона
            $dealTitle = trim($contact['name']);
            if (!empty($contact['phone'])) {
                $dealTitle .= ' - ' . $contact['phone'];
            }
            if (empty($dealTitle)) {
                $dealTitle = 'Сделка для события #' . $eventId;
            }

            // Создаем сделку
            $deal = new \CCrmDeal(true);
            $dealFields = [
                'TITLE' => $dealTitle,
                'CONTACT_IDS' => [$contactId],
                'ASSIGNED_BY_ID' => \CCrmSecurityHelper::GetCurrentUserID(),
                'STAGE_ID' => 'NEW',
                'OPPORTUNITY' => 0,
                'CURRENCY_ID' => 'RUB',
                'OPENED' => 'Y'
            ];

            $dealId = $deal->Add($dealFields);
            
            if ($dealId) {
                // Привязываем сделку к событию
                $calendar->updateEventDeal($eventId, $dealId);
                
                return [
                    'success' => true, 
                    'dealId' => $dealId,
                    'message' => 'Сделка успешно создана и привязана к событию'
                ];
            } else {
                return ['success' => false, 'error' => 'Ошибка создания сделки: ' . $deal->LAST_ERROR];
            }

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Создание нового контакта в CRM
     */
    public function createContactAction($contactData)
    {
        if (!$GLOBALS['USER'] || !$GLOBALS['USER']->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            if (!CModule::IncludeModule('crm')) {
                return ['success' => false, 'error' => 'Модуль CRM не установлен'];
            }

            // Создаем новый контакт
            $contactEntity = new \CCrmContact(true);
            
            $userId = \CCrmSecurityHelper::GetCurrentUserID();
            $name = $contactData['name'];
            $lastname = $contactData['lastname'] ?? '';
            $phone = $contactData['phone'] ?? '';
            $email = $contactData['email'] ?? '';
            
            // Формируем поля контакта
            $contactFields = [
                'NAME' => $name,
                'LAST_NAME' => $lastname,
                'OPENED' => 'Y',
                'ASSIGNED_BY_ID' => $userId,
            ];
            
            // Добавляем мультиполя для телефона
            if (!empty($phone)) {
                $contactFields['FM'] = [
                    'PHONE' => [
                        'n0' => [
                            'VALUE' => $phone,
                            'VALUE_TYPE' => 'WORK',
                        ],
                    ],
                ];
            }
            
            // Добавляем мультиполя для email
            if (!empty($email)) {
                if (isset($contactFields['FM'])) {
                    $contactFields['FM']['EMAIL'] = [
                        'n0' => [
                            'VALUE' => $email,
                            'VALUE_TYPE' => 'WORK',
                        ],
                    ];
                } else {
                    $contactFields['FM'] = [
                        'EMAIL' => [
                            'n0' => [
                                'VALUE' => $email,
                                'VALUE_TYPE' => 'WORK',
                            ],
                        ],
                    ];
                }
            }
            
            $contactId = $contactEntity->Add($contactFields);
            
            if ($contactId) {
                // Получаем данные созданного контакта
                $createdContact = $this->getContactFromCRM($contactId);
                
                return [
                    'success' => true, 
                    'contactId' => $contactId,
                    'contact' => $createdContact,
                    'message' => 'Контакт успешно создан'
                ];
            } else {
                return ['success' => false, 'error' => 'Ошибка создания контакта: ' . $contactEntity->LAST_ERROR];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    

    /**
     * Сохранение контакта к событию
     */
    public function saveEventContactAction($eventId, $contactId, $contactData)
    {
        if (!$GLOBALS['USER'] || !$GLOBALS['USER']->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            if (!CModule::IncludeModule('artmax.calendar')) {
                return ['success' => false, 'error' => 'Модуль календаря не установлен'];
            }

            $calendar = new \Artmax\Calendar\Calendar();
            
            // Обновляем событие с ID контакта
            $result = $calendar->updateEventContact($eventId, $contactId);
            
            if ($result) {
                return [
                    'success' => true, 
                    'message' => 'Контакт успешно привязан к событию',
                    'contact' => $contactData
                ];
            } else {
                return ['success' => false, 'error' => 'Ошибка привязки контакта к событию'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Получение данных события
     */
    public function getEventDataAction($eventId)
    {
        if (!$GLOBALS['USER'] || !$GLOBALS['USER']->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            if (!CModule::IncludeModule('artmax.calendar')) {
                return ['success' => false, 'error' => 'Модуль календаря не установлен'];
            }

            $calendar = new \Artmax\Calendar\Calendar();
            $event = $calendar->getEvent($eventId);
            
            if ($event) {
                return [
                    'success' => true,
                    'event' => $event
                ];
            } else {
                return ['success' => false, 'error' => 'Событие не найдено'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Сохранение заметки к событию
     */
    public function saveEventNoteAction($eventId, $noteText)
    {
        if (!$GLOBALS['USER'] || !$GLOBALS['USER']->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            if (!CModule::IncludeModule('artmax.calendar')) {
                return ['success' => false, 'error' => 'Модуль календаря не установлен'];
            }

            $calendar = new \Artmax\Calendar\Calendar();
            
            // Обновляем событие с заметкой
            $result = $calendar->updateEventNote($eventId, $noteText);
            
            if ($result) {
                return [
                    'success' => true, 
                    'message' => 'Заметка успешно сохранена'
                ];
            } else {
                return ['success' => false, 'error' => 'Ошибка сохранения заметки'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Получение списка сотрудников из CRM
     */
    public function getEmployeesAction()
    {
        if (!$GLOBALS['USER'] || !$GLOBALS['USER']->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            if (!CModule::IncludeModule('crm')) {
                return ['success' => false, 'error' => 'Модуль CRM не установлен'];
            }

            // Получаем список пользователей из Bitrix
            $userEntity = new \CUser();
            $users = $userEntity->GetList(
                ($by = "ID"),
                ($order = "ASC"),
                [
                    'ACTIVE' => 'Y',
                    'UF_DEPARTMENT' => true // Получаем только пользователей с департаментами
                ],
                [
                    'FIELDS' => ['ID', 'NAME', 'LAST_NAME', 'LOGIN', 'EMAIL']
                ]
            );

            $employees = [];
            while ($user = $users->Fetch()) {
                if (!empty($user['NAME']) || !empty($user['LAST_NAME'])) {
                    $employees[] = [
                        'ID' => $user['ID'],
                        'NAME' => $user['NAME'] ?: '',
                        'LAST_NAME' => $user['LAST_NAME'] ?: '',
                        'LOGIN' => $user['LOGIN'] ?: '',
                        'EMAIL' => $user['EMAIL'] ?: ''
                    ];
                }
            }

            return [
                'success' => true,
                'employees' => $employees
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Поиск сотрудников по запросу
     */
    public function searchEmployeesAction($query)
    {
        if (!$GLOBALS['USER'] || !$GLOBALS['USER']->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            if (!CModule::IncludeModule('crm')) {
                return ['success' => false, 'error' => 'Модуль CRM не установлен'];
            }

            $query = trim($query);
            if (empty($query)) {
                return $this->getEmployeesAction();
            }

            // Получаем список пользователей из Bitrix с поиском
            $userEntity = new \CUser();
            $employees = [];
            $foundIds = [];
            
            // Поиск по имени
            $usersByName = $userEntity->GetList(
                ($by = "ID"),
                ($order = "ASC"),
                [
                    'ACTIVE' => 'Y',
                    'UF_DEPARTMENT' => true, // Получаем только пользователей с департаментами
                    'NAME' => $query . ' &' // Поиск по имени с оператором &
                ],
                [
                    'FIELDS' => ['ID', 'NAME', 'LAST_NAME', 'LOGIN', 'EMAIL']
                ]
            );
            
            while ($user = $usersByName->Fetch()) {
                if (!in_array($user['ID'], $foundIds) && (!empty($user['NAME']) || !empty($user['LAST_NAME']))) {
                    $employees[] = [
                        'ID' => $user['ID'],
                        'NAME' => $user['NAME'] ?: '',
                        'LAST_NAME' => $user['LAST_NAME'] ?: '',
                        'LOGIN' => $user['LOGIN'] ?: '',
                        'EMAIL' => $user['EMAIL'] ?: ''
                    ];
                    $foundIds[] = $user['ID'];
                }
            }
            
            // Поиск по фамилии
            $usersByLastName = $userEntity->GetList(
                ($by = "ID"),
                ($order = "ASC"),
                [
                    'ACTIVE' => 'Y',
                    'UF_DEPARTMENT' => true,
                    'LAST_NAME' => $query . ' &' // Поиск по фамилии с оператором &
                ],
                [
                    'FIELDS' => ['ID', 'NAME', 'LAST_NAME', 'LOGIN', 'EMAIL']
                ]
            );
            
            while ($user = $usersByLastName->Fetch()) {
                if (!in_array($user['ID'], $foundIds) && (!empty($user['NAME']) || !empty($user['LAST_NAME']))) {
                    $employees[] = [
                        'ID' => $user['ID'],
                        'NAME' => $user['NAME'] ?: '',
                        'LAST_NAME' => $user['LAST_NAME'] ?: '',
                        'LOGIN' => $user['LOGIN'] ?: '',
                        'EMAIL' => $user['EMAIL'] ?: ''
                    ];
                    $foundIds[] = $user['ID'];
                }
            }
            
            // Поиск по логину
            $usersByLogin = $userEntity->GetList(
                ($by = "ID"),
                ($order = "ASC"),
                [
                    'ACTIVE' => 'Y',
                    'UF_DEPARTMENT' => true,
                    'LOGIN' => $query . ' &' // Поиск по логину с оператором &
                ],
                [
                    'FIELDS' => ['ID', 'NAME', 'LAST_NAME', 'LOGIN', 'EMAIL']
                ]
            );
            
            while ($user = $usersByLogin->Fetch()) {
                if (!in_array($user['ID'], $foundIds) && (!empty($user['NAME']) || !empty($user['LAST_NAME']))) {
                    $employees[] = [
                        'ID' => $user['ID'],
                        'NAME' => $user['NAME'] ?: '',
                        'LAST_NAME' => $user['LAST_NAME'] ?: '',
                        'LOGIN' => $user['LOGIN'] ?: '',
                        'EMAIL' => $user['EMAIL'] ?: ''
                    ];
                    $foundIds[] = $user['ID'];
                }
            }

            return [
                'success' => true,
                'employees' => $employees
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Сохранение настроек филиала
     */
    public function saveBranchSettingsAction($branchId, $timezoneName, $employeeIds)
    {
        if (!$GLOBALS['USER'] || !$GLOBALS['USER']->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            if (!CModule::IncludeModule('artmax.calendar')) {
                return ['success' => false, 'error' => 'Модуль календаря не установлен'];
            }

            $calendar = new \Artmax\Calendar\Calendar();
            
            // Сохраняем часовой пояс
            if (!empty($timezoneName)) {
                $timezoneManager = new \Artmax\Calendar\TimezoneManager();
                $timezoneManager->setBranchTimezone($branchId, $timezoneName);
            }
            
            // Сохраняем сотрудников филиала
            $employeeIdsArray = json_decode($employeeIds, true);
            if (is_array($employeeIdsArray)) {
                $calendar->updateBranchEmployees($branchId, $employeeIdsArray);
            }

            return [
                'success' => true,
                'message' => 'Настройки филиала успешно сохранены'
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Получение сотрудников филиала
     */
    public function getBranchEmployeesAction($branchId)
    {
        if (!$GLOBALS['USER'] || !$GLOBALS['USER']->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            if (!CModule::IncludeModule('artmax.calendar')) {
                return ['success' => false, 'error' => 'Модуль календаря не установлен'];
            }

            $calendar = new \Artmax\Calendar\Calendar();
            $employees = $calendar->getBranchEmployees($branchId);

            return [
                'success' => true,
                'employees' => $employees
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Ошибка получения сотрудников филиала: ' . $e->getMessage()];
        }
    }

    /**
     * Получение контакта из CRM по ID
     */
    public function getContactFromCRM($contactId)
    {
        if (!CModule::IncludeModule('crm')) {
            return null;
        }

        try {
            // Получаем контакт с дополнительными полями
            $arSelect = [
                'ID', 'NAME', 'LAST_NAME', 'SECOND_NAME', 
                'EMAIL', 'PHONE', 'COMPANY_TITLE', 'POST', 'ADDRESS'
            ];
            
            $contact = \CCrmContact::GetByID($contactId, $arSelect);
            if ($contact) {
                // Получаем телефоны из мультиполей
                $phones = [];
                $arPhoneFilter = [
                    'ENTITY_ID'  => 'CONTACT',
                    'ELEMENT_ID' => $contactId,
                    'TYPE_ID'    => 'PHONE',
                ];
                $dbPhones = \CCrmFieldMulti::GetListEx([], $arPhoneFilter, false, ['nTopCount' => 10], ['VALUE']);
                while ($arPhone = $dbPhones->fetch()) {
                    if (!empty($arPhone['VALUE'])) {
                        $phones[] = $arPhone['VALUE'];
                    }
                }
                
                // Получаем email из мультиполей
                $emails = [];
                $arEmailFilter = [
                    'ENTITY_ID'  => 'CONTACT',
                    'ELEMENT_ID' => $contactId,
                    'TYPE_ID'    => 'EMAIL',
                ];
                $dbEmails = \CCrmFieldMulti::GetListEx([], $arEmailFilter, false, ['nTopCount' => 10], ['VALUE']);
                while ($arEmail = $dbEmails->fetch()) {
                    if (!empty($arEmail['VALUE'])) {
                        $emails[] = $arEmail['VALUE'];
                    }
                }
                
                // Если в мультиполях ничего не найдено, берем из основных полей
                if (empty($phones) && !empty($contact['PHONE'])) {
                    $phones[] = $contact['PHONE'];
                }
                if (empty($emails) && !empty($contact['EMAIL'])) {
                    $emails[] = $contact['EMAIL'];
                }
                
                // Формируем полное имя
                $fullName = trim($contact['NAME'] . ' ' . $contact['LAST_NAME'] . ' ' . $contact['SECOND_NAME']);
                if (empty($fullName)) {
                    $fullName = 'Контакт #' . $contact['ID'];
                }
                
                return [
                    'id' => $contact['ID'],
                    'name' => $fullName,
                    'phone' => implode(', ', $phones),
                    'email' => implode(', ', $emails),
                    'company' => $contact['COMPANY_TITLE'] ?? ''
                ];
            }
        } catch (\Exception $e) {
            error_log('Ошибка получения контакта из CRM: ' . $e->getMessage());
        }

        return null;
    }
    
    /**
     * Получение сделки из CRM по ID
     */
    private function getDealFromCRM($dealId)
    {
        if (!CModule::IncludeModule('crm')) {
            return null;
        }

        try {
            $deal = \CCrmDeal::GetByID($dealId);
            if ($deal) {
                return [
                    'id' => $deal['ID'],
                    'title' => $deal['TITLE'] ?? 'Сделка #' . $deal['ID'],
                    'amount' => $deal['OPPORTUNITY'] ?? '',
                    'currency' => $deal['CURRENCY_ID'] ?? 'RUB',
                    'stage' => $deal['STAGE_ID'] ?? '',
                    'company' => $deal['COMPANY_TITLE'] ?? ''
                ];
            }
        } catch (\Exception $e) {
            error_log('Ошибка получения сделки из CRM: ' . $e->getMessage());
        }

        return null;
    }
    
    /**
     * Поиск контактов в Bitrix 24 CRM через REST API
     */
    private function searchBitrix24Contacts($query)
    {
        $contacts = [];
        
        try {
            // Подключаем модуль REST API
            if (!CModule::IncludeModule('rest')) {
                error_log('Модуль REST API не установлен');
                return $contacts;
            }
            
            // Получаем токен приложения
            $appId = 'local.1'; // ID локального приложения
            $appSecret = ''; // Секрет приложения (если нужен)
            
            // Создаем клиент REST API
            $restClient = new \CRestServer();
            
            // Подготавливаем параметры для поиска
            $searchParams = [
                'filter' => [
                    'LOGIC' => 'OR',
                    ['NAME' => '%' . $query . '%'],
                    ['LAST_NAME' => '%' . $query . '%'],
                    ['SECOND_NAME' => '%' . $query . '%'],
                    ['EMAIL' => '%' . $query . '%'],
                    ['PHONE' => '%' . $query . '%'],
                    ['COMPANY_TITLE' => '%' . $query . '%']
                ],
                'select' => [
                    'ID',
                    'NAME',
                    'LAST_NAME',
                    'SECOND_NAME',
                    'EMAIL',
                    'PHONE',
                    'COMPANY_TITLE',
                    'POST',
                    'ADDRESS'
                ],
                'order' => ['LAST_NAME' => 'ASC', 'NAME' => 'ASC'],
                'start' => 0,
                'limit' => 10
            ];
            
            // Если запрос похож на телефон, добавляем поиск по всем UF полям
            if (preg_match('/^[\d\s\-\+\(\)]+$/', $query)) {
                // Получаем список всех UF полей контакта
                $ufFields = $this->getContactUFFields();
                
                // Добавляем поиск по всем UF полям
                foreach ($ufFields as $ufField) {
                    $searchParams['filter'][] = [$ufField => '%' . $query . '%'];
                    $searchParams['select'][] = $ufField;
                }
            }
            
            // Выполняем REST API запрос
            $result = $restClient->callMethod('crm.contact.list', $searchParams);
            
            if ($result && isset($result['result'])) {
                foreach ($result['result'] as $contact) {
                    // Формируем полное имя
                    $fullName = trim(($contact['NAME'] ?? '') . ' ' . ($contact['LAST_NAME'] ?? '') . ' ' . ($contact['SECOND_NAME'] ?? ''));
                    if (empty($fullName)) {
                        $fullName = 'Контакт #' . $contact['ID'];
                    }
                    
                    // Собираем телефоны
                    $phones = [];
                    if (!empty($contact['PHONE'])) {
                        $phones[] = $contact['PHONE'];
                    }
                    
                    // Проверяем все UF поля на наличие телефонов
                    foreach ($contact as $fieldCode => $fieldValue) {
                        if (strpos($fieldCode, 'UF_') === 0 && 
                            !empty($fieldValue) && 
                            is_string($fieldValue) &&
                            preg_match('/[\d\s\-\+\(\)]{7,}/', $fieldValue) && // Содержит паттерн телефона
                            !in_array($fieldValue, $phones)) {
                            $phones[] = $fieldValue;
                        }
                    }
                    
                    // Собираем email адреса
                    $emails = [];
                    if (!empty($contact['EMAIL'])) {
                        $emails[] = $contact['EMAIL'];
                    }
                    
                    // Проверяем UF поля на наличие email
                    foreach ($contact as $fieldCode => $fieldValue) {
                        if (strpos($fieldCode, 'UF_') === 0 && 
                            !empty($fieldValue) && 
                            is_string($fieldValue) &&
                            filter_var($fieldValue, FILTER_VALIDATE_EMAIL) && // Валидный email
                            !in_array($fieldValue, $emails)) {
                            $emails[] = $fieldValue;
                        }
                    }
                    
                    $contacts[] = [
                        'id' => $contact['ID'],
                        'name' => $fullName,
                        'firstName' => $contact['NAME'] ?? '',
                        'lastName' => $contact['LAST_NAME'] ?? '',
                        'secondName' => $contact['SECOND_NAME'] ?? '',
                        'phone' => implode(', ', $phones),
                        'email' => implode(', ', $emails),
                        'company' => $contact['COMPANY_TITLE'] ?? '',
                        'post' => $contact['POST'] ?? '',
                        'address' => $contact['ADDRESS'] ?? ''
                    ];
                }
            }
            
        } catch (\Exception $e) {
            error_log('Ошибка поиска контактов через REST API: ' . $e->getMessage());
        }
        
        return $contacts;
    }
    
    /**
     * Получение списка UF полей контакта
     */
    private function getContactUFFields()
    {
        $ufFields = [];
        
        try {
            if (CModule::IncludeModule('rest')) {
                $restClient = new \CRestServer();
                $result = $restClient->callMethod('crm.contact.userfield.list');
                
                if ($result && isset($result['result'])) {
                    foreach ($result['result'] as $field) {
                        if (isset($field['FIELD_NAME'])) {
                            $ufFields[] = $field['FIELD_NAME'];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('Ошибка получения UF полей: ' . $e->getMessage());
        }
        
        return $ufFields;
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