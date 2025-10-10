<?php
namespace Artmax\Calendar;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Connection;
use Bitrix\Main\Type\DateTime;
use Artmax\Calendar\TimezoneManager;

class Calendar
{
    private $connection;
    private $timezoneManager;

    public function __construct()
    {
        $this->connection = Application::getConnection();
        $this->timezoneManager = new TimezoneManager();
    }

    /**
     * Добавить новое событие
     */
    public function addEvent($title, $description, $dateFrom, $dateTo, $userId, $branchId = 1, $eventColor = '#3498db', $employeeId = null)
    {
                // Простое логирование
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "=== ADD_EVENT DEBUG ===\n", 
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "ADD_EVENT: Input parameters:\n", 
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "  - dateFrom: {$dateFrom}\n", 
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "  - dateTo: {$dateTo}\n", 
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "  - userId: {$userId}\n", 
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "  - branchId: {$branchId}\n", 
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "  - eventColor: {$eventColor}\n", 
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "  - employeeId: {$employeeId}\n", 
            FILE_APPEND | LOCK_EX);
        
        // Используем время как есть, без всяких конвертаций
        $sql = "INSERT INTO artmax_calendar_events (TITLE, DESCRIPTION, DATE_FROM, DATE_TO, ORIGINAL_DATE_FROM, ORIGINAL_DATE_TO, TIME_IS_CHANGED, USER_ID, BRANCH_ID, EVENT_COLOR, EMPLOYEE_ID) VALUES ('" . 
               $this->connection->getSqlHelper()->forSql($title) . "', '" . 
               $this->connection->getSqlHelper()->forSql($description) . "', '" .
               $this->connection->getSqlHelper()->forSql($dateFrom) . "', '" .
               $this->connection->getSqlHelper()->forSql($dateTo) . "', '" .
               $this->connection->getSqlHelper()->forSql($dateFrom) . "', '" .
               $this->connection->getSqlHelper()->forSql($dateTo) . "', 0, " . 
               (int)$userId . ", " . 
               (int)$branchId . ", '" . 
               $this->connection->getSqlHelper()->forSql($eventColor) . "', " . 
               ($employeeId ? (int)$employeeId : 'NULL') . ")";

        // Логируем SQL запрос
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "ADD_EVENT: SQL Query:\n", 
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "  - SQL: {$sql}\n", 
            FILE_APPEND | LOCK_EX);

        $result = $this->connection->query($sql);

        if ($result) {
            $eventId = $this->connection->getInsertedId();
            
            // Логируем успешное создание
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "ADD_EVENT: Event created successfully with ID: {$eventId}\n", 
                FILE_APPEND | LOCK_EX);
            
            // Проверяем, что реально сохранилось в БД
            $checkSql = "SELECT DATE_FROM, DATE_TO, EVENT_COLOR FROM artmax_calendar_events WHERE ID = {$eventId}";
            $checkResult = $this->connection->query($checkSql);
            if ($checkResult) {
                $savedEvent = $checkResult->fetch();
                if ($savedEvent) {
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                        "ADD_EVENT: What was actually saved in DB:\n", 
                        FILE_APPEND | LOCK_EX);
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                        "  - DATE_FROM: " . ($savedEvent['DATE_FROM'] ?? 'NULL') . "\n", 
                        FILE_APPEND | LOCK_EX);
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                        "  - DATE_TO: " . ($savedEvent['DATE_TO'] ?? 'NULL') . "\n", 
                        FILE_APPEND | LOCK_EX);
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                        "  - EVENT_COLOR: " . ($savedEvent['EVENT_COLOR'] ?? 'NULL') . "\n", 
                        FILE_APPEND | LOCK_EX);
                }
            }
            
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "=== END ADD_EVENT DEBUG ===\n", 
                FILE_APPEND | LOCK_EX);
            
            return $eventId;
        }

        // Логируем ошибку
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "ADD_EVENT: Failed to create event\n", 
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "=== END ADD_EVENT DEBUG ===\n", 
            FILE_APPEND | LOCK_EX);

        return false;
    } 

    /**
     * Получить события за период
     */
    public function getEvents($dateFrom, $dateTo, $userId = null)
    {
        // Используем время как есть, без всяких конвертаций
        $sql = "
            SELECT * FROM artmax_calendar_events 
            WHERE DATE_FROM >= '" . $this->connection->getSqlHelper()->forSql($dateFrom) . "' AND DATE_TO <= '" . $this->connection->getSqlHelper()->forSql($dateTo) . "'
        ";

        if ($userId) {
            $sql .= " AND USER_ID = " . (int)$userId;
        }

        $sql .= " ORDER BY DATE_FROM ASC";

        $result = $this->connection->query($sql);

        return $result->fetchAll();
    }
    /**
     * Получить timezone филиала
     */
    private function getBranchTimezone($branchId)
    {
        $sql = "SELECT TIMEZONE_NAME FROM artmax_calendar_branches WHERE ID = " . (int)$branchId;
        $result = $this->connection->query($sql);
        if ($result) {
            $branch = $result->fetch();
            return $branch['TIMEZONE_NAME'] ?? 'Europe/Moscow'; // По умолчанию Москва
        }
        return 'Europe/Moscow';
    }

    /**
     * Конвертировать время в timezone филиала
     */
    private function convertTimeToBranchTimezone($timeString, $branchTimezone)
    {
        try {
            // Создаем DateTime объект из строки времени (предполагаем, что это UTC)
            $utcDateTime = new \DateTime($timeString, new \DateTimeZone('UTC'));

            // Конвертируем в timezone филиала
            $utcDateTime->setTimezone(new \DateTimeZone($branchTimezone));

            // Возвращаем в нужном формате
            return $utcDateTime->format('d.m.Y H:i:s');
        } catch (\Exception $e) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log',
                "GET_EVENT: Error converting time: " . $e->getMessage() . "\n",
                FILE_APPEND | LOCK_EX);
            return $timeString; // Возвращаем как есть при ошибке
        }
    }

    /**
     * Получить событие по ID
     */
    public function getEvent($id)
    {

        // Используем время как есть, без всяких конвертаций
        $sql = "
            SELECT 
                ID,
                DATE_FORMAT(DATE_FROM, '%Y-%m-%d %H:%i:%s') AS DATE_FROM,
                DATE_FORMAT(DATE_TO, '%Y-%m-%d %H:%i:%s') AS DATE_TO,
                DATE_FORMAT(DATE_FROM, '%d.%m.%Y %H:%i:%s') AS DATE_FROM_RAW,
                DATE_FORMAT(DATE_TO, '%d.%m.%Y %H:%i:%s') AS DATE_TO_RAW,
                TITLE,
                DESCRIPTION,
                USER_ID,
                BRANCH_ID,
                EVENT_COLOR,
                CONTACT_ENTITY_ID,
                DEAL_ENTITY_ID,
                NOTE,
                EMPLOYEE_ID,
                CONFIRMATION_STATUS,
                STATUS,
                TIME_IS_CHANGED,
                VISIT_STATUS,
                CREATED_AT,
                UPDATED_AT
            FROM artmax_calendar_events 
            WHERE ID = " . (int)$id;
        
        $result = $this->connection->query($sql);



        if (!$result) {
            return false;
        }
        
        $event = $result->fetch();

        
        if ($event) {
            
            // Преобразуем объекты в строки для JSON
            $event['DATE_FROM'] = $event['DATE_FROM_RAW'];

            $event['DATE_TO'] = $event['DATE_TO_RAW'];
        } else {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "GET_EVENT: Event not found\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "=== END GET_EVENT DEBUG ===\n", 
                FILE_APPEND | LOCK_EX);
        }

        return $event;
    }

    /**
     * Обновить событие
     */
    public function updateEvent($id, $title, $description, $dateFrom, $dateTo, $eventColor = null, $branchId = null, $employeeId = null)
    {
        // Получаем существующее событие для проверки изменения времени
        $existingEvent = $this->getEvent($id);
        if (!$existingEvent) {
            return false;
        }
        
        // Если branchId не передан, используем из существующего события
        if (!$branchId) {
            $branchId = $existingEvent['BRANCH_ID'];
        }
        
        // Проверяем, изменилось ли время
        $timeChanged = 0;
        if ($existingEvent['ORIGINAL_DATE_FROM'] && $existingEvent['ORIGINAL_DATE_TO']) {
            // Если есть оригинальные даты, сравниваем с ними
            if ($existingEvent['ORIGINAL_DATE_FROM'] != $dateFrom || $existingEvent['ORIGINAL_DATE_TO'] != $dateTo) {
                $timeChanged = 1;
            }
        } else {
            // Если нет оригинальных дат, сравниваем с текущими
            if ($existingEvent['DATE_FROM'] != $dateFrom || $existingEvent['DATE_TO'] != $dateTo) {
                $timeChanged = 1;
            }
        }
        
        // Используем время как есть, без всяких конвертаций
        $sql = "
            UPDATE artmax_calendar_events 
            SET TITLE = '" . $this->connection->getSqlHelper()->forSql($title) . "', 
                DESCRIPTION = '" . $this->connection->getSqlHelper()->forSql($description) . "', 
                DATE_FROM = '" . $this->connection->getSqlHelper()->forSql($dateFrom) . "', 
                DATE_TO = '" . $this->connection->getSqlHelper()->forSql($dateTo) . "', 
                TIME_IS_CHANGED = " . $timeChanged . ",
                UPDATED_AT = NOW()";
        
        if ($eventColor !== null) {
            $sql .= ", EVENT_COLOR = '" . $this->connection->getSqlHelper()->forSql($eventColor) . "'";
        }
        
        if ($branchId !== null) {
            $sql .= ", BRANCH_ID = " . (int)$branchId;
        }
        
        if ($employeeId !== null) {
            $sql .= ", EMPLOYEE_ID = " . ($employeeId ? (int)$employeeId : 'NULL');
        }
        
        $sql .= " WHERE ID = " . (int)$id;

        // Используем время как есть, без всяких конвертаций
        return $this->connection->query($sql);
    }

    /**
     * Назначить врача событию
     */
    public function assignDoctor($eventId, $employeeId)
    {
        $sql = "
            UPDATE artmax_calendar_events 
            SET EMPLOYEE_ID = " . ($employeeId ? (int)$employeeId : 'NULL') . ",
                UPDATED_AT = NOW()
            WHERE ID = " . (int)$eventId;
        
        return $this->connection->query($sql);
    }

    /**
     * Удалить событие
     */
    public function deleteEvent($id)
    {
        // Используем время как есть, без всяких конвертаций
        $sql = "DELETE FROM artmax_calendar_events WHERE ID = " . (int)$id;
        return $this->connection->query($sql);
    }

    /**
     * Проверить доступность времени для конкретного врача в конкретном филиале
     */
    public function isTimeAvailableForDoctor($dateFrom, $dateTo, $employeeId, $excludeId = null, $branchId = null)
    {
        // Если врач не указан, считаем время доступным (не проверяем конфликты)
        if (!$employeeId || $employeeId === '' || $employeeId === '0') {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "IS_TIME_AVAILABLE_FOR_DOCTOR: No employeeId provided, returning true (no conflict check)\n", 
                FILE_APPEND | LOCK_EX);
            return true;
        }

        // Логируем параметры
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "IS_TIME_AVAILABLE_FOR_DOCTOR: Checking for employeeId=" . $employeeId . ", branchId=" . $branchId . ", dateFrom=" . $dateFrom . ", dateTo=" . $dateTo . "\n", 
            FILE_APPEND | LOCK_EX);
        
        // Извлекаем дату из dateFrom для фильтрации по конкретному дню
        // Используем простой способ извлечения даты без DateTime
        $dateOnly = substr($dateFrom, 0, 10); // Берем первые 10 символов (YYYY-MM-DD)
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "IS_TIME_AVAILABLE_FOR_DOCTOR: Extracted dateOnly=" . $dateOnly . "\n", 
            FILE_APPEND | LOCK_EX);
        
        // Проверяем пересечение временных интервалов только для конкретного врача, только активных записей, только на конкретную дату И только в том же филиале
        if ($employeeId === null) {
            $sql = "SELECT COUNT(*) as count FROM artmax_calendar_events WHERE EMPLOYEE_ID IS NULL AND STATUS != 'cancelled' AND DATE(DATE_FROM) = '$dateOnly' AND DATE_FROM < '" . $this->connection->getSqlHelper()->forSql($dateTo) . "' AND DATE_TO > '" . $this->connection->getSqlHelper()->forSql($dateFrom) . "'";
        } else {
            $sql = "SELECT COUNT(*) as count FROM artmax_calendar_events WHERE EMPLOYEE_ID = " . (int)$employeeId . " AND STATUS != 'cancelled' AND DATE(DATE_FROM) = '$dateOnly' AND DATE_FROM < '" . $this->connection->getSqlHelper()->forSql($dateTo) . "' AND DATE_TO > '" . $this->connection->getSqlHelper()->forSql($dateFrom) . "'";
        }
        
        // Добавляем проверку филиала, если он указан
        if ($branchId) {
            $sql .= " AND BRANCH_ID = " . (int)$branchId;
        }
        
        if ($excludeId) {
            $sql .= " AND ID != " . (int)$excludeId;
        }
        
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "IS_TIME_AVAILABLE_FOR_DOCTOR: SQL = " . $sql . "\n", 
            FILE_APPEND | LOCK_EX);
        
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "IS_TIME_AVAILABLE_FOR_DOCTOR: dateOnly = " . $dateOnly . ", dateFrom = " . $dateFrom . ", dateTo = " . $dateTo . "\n", 
            FILE_APPEND | LOCK_EX);
        
        // Проверяем, какие события есть у врача на эту дату в этом филиале
        $checkSql = "SELECT ID, TITLE, DATE_FROM, DATE_TO, BRANCH_ID FROM artmax_calendar_events WHERE EMPLOYEE_ID = " . (int)$employeeId . " AND DATE(DATE_FROM) = '$dateOnly'";
        if ($branchId) {
            $checkSql .= " AND BRANCH_ID = " . (int)$branchId;
        }
        $checkResult = $this->connection->query($checkSql);
        $existingEvents = $checkResult->fetchAll();
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "IS_TIME_AVAILABLE_FOR_DOCTOR: Existing events on $dateOnly" . ($branchId ? " in branch $branchId" : "") . ": " . json_encode($existingEvents) . "\n", 
            FILE_APPEND | LOCK_EX);
        
        $result = $this->connection->query($sql);
        $row = $result->fetch();
        
        $count = $row['count'];
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "IS_TIME_AVAILABLE_FOR_DOCTOR: Found " . $count . " conflicting events for doctor" . ($branchId ? " in branch $branchId" : "") . "\n", 
            FILE_APPEND | LOCK_EX);
        
        // Если есть конфликты, показываем какие именно события конфликтуют
        if ($count > 0) {
            if ($employeeId === null) {
                $conflictSql = "SELECT ID, TITLE, DATE_FROM, DATE_TO, BRANCH_ID FROM artmax_calendar_events WHERE EMPLOYEE_ID IS NULL AND STATUS != 'cancelled' AND DATE(DATE_FROM) = '$dateOnly' AND DATE_FROM < '" . $this->connection->getSqlHelper()->forSql($dateTo) . "' AND DATE_TO > '" . $this->connection->getSqlHelper()->forSql($dateFrom) . "'";
            } else {
                $conflictSql = "SELECT ID, TITLE, DATE_FROM, DATE_TO, BRANCH_ID FROM artmax_calendar_events WHERE EMPLOYEE_ID = " . (int)$employeeId . " AND STATUS != 'cancelled' AND DATE(DATE_FROM) = '$dateOnly' AND DATE_FROM < '" . $this->connection->getSqlHelper()->forSql($dateTo) . "' AND DATE_TO > '" . $this->connection->getSqlHelper()->forSql($dateFrom) . "'";
            }
            
            // Добавляем проверку филиала для конфликтов
            if ($branchId) {
                $conflictSql .= " AND BRANCH_ID = " . (int)$branchId;
            }
            
            if ($excludeId) {
                $conflictSql .= " AND ID != " . (int)$excludeId;
            }
            $conflictResult = $this->connection->query($conflictSql);
            $conflicts = $conflictResult->fetchAll();
            
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "IS_TIME_AVAILABLE_FOR_DOCTOR: Conflicting events: " . json_encode($conflicts) . "\n", 
                FILE_APPEND | LOCK_EX);
        }
        
        return $count == 0; 
    }

    /**
     * Получить занятые временные слоты для врача на конкретную дату
     */
    public function getOccupiedTimesForDoctor($date, $employeeId, $excludeEventId = null)
    {
        $occupiedTimes = [];
        
        // Если врач не указан, возвращаем пустой массив
        if (!$employeeId) {
            return $occupiedTimes;
        }

        // Формируем дату начала и конца дня
        $dateFrom = $date . ' 00:00:00';
        $dateTo = $date . ' 23:59:59';

        // Получаем все активные события врача на эту дату
        if ($employeeId === null) {
            $sql = "SELECT DATE_FORMAT(DATE_FROM, '%H:%i') as TIME_FROM, DATE_FORMAT(DATE_TO, '%H:%i') as TIME_TO FROM artmax_calendar_events 
                    WHERE EMPLOYEE_ID IS NULL 
                    AND STATUS != 'cancelled'
                    AND DATE_FROM >= '" . $this->connection->getSqlHelper()->forSql($dateFrom) . "' 
                    AND DATE_TO <= '" . $this->connection->getSqlHelper()->forSql($dateTo) . "'";
        } else {
            $sql = "SELECT DATE_FORMAT(DATE_FROM, '%H:%i') as TIME_FROM, DATE_FORMAT(DATE_TO, '%H:%i') as TIME_TO FROM artmax_calendar_events 
                    WHERE EMPLOYEE_ID = " . (int)$employeeId . " 
                    AND STATUS != 'cancelled'
                    AND DATE_FROM >= '" . $this->connection->getSqlHelper()->forSql($dateFrom) . "' 
                    AND DATE_TO <= '" . $this->connection->getSqlHelper()->forSql($dateTo) . "'";
        }

        if ($excludeEventId) {
            $sql .= " AND ID != " . (int)$excludeEventId;
        }

        $result = $this->connection->query($sql);
        
        while ($row = $result->fetch()) {
            // Используем время как строку из DATE_FORMAT
            $startTime = $row['TIME_FROM'];
            $endTime = $row['TIME_TO'];
            
            // Генерируем временные слоты с интервалом 30 минут
            $currentTime = strtotime($startTime);
            $endTimestamp = strtotime($endTime);
            
            while ($currentTime < $endTimestamp) {
                $timeSlot = date('H:i', $currentTime);
                $occupiedTimes[] = $timeSlot;
                $currentTime += 30 * 60; // Добавляем 30 минут
            }
        }

        return array_unique($occupiedTimes);
    }

    /**
     * Обновить статус события
     */
    public function updateEventStatus($eventId, $status)
    {
        $sql = "
            UPDATE artmax_calendar_events 
            SET STATUS = '" . $this->connection->getSqlHelper()->forSql($status) . "',
                UPDATED_AT = NOW()
            WHERE ID = " . (int)$eventId;
        
        return $this->connection->query($sql);
    }

    /**
     * Получить события по филиалу
     */
    public function getEventsByBranch($branchId, $dateFrom = null, $dateTo = null, $userId = null, $limit = null, $employeeId = null)
    {
        // Проверяем branchId
        if (!$branchId || !is_numeric($branchId)) {
            return [];
        }

        // Формируем SELECT с DATE_FORMAT — чтобы поля пришли как строки
        $sql = "
        SELECT 
            ID,
            TITLE,
            DESCRIPTION,
            DATE_FORMAT(DATE_FROM, '%d.%m.%Y %H:%i:%s') AS DATE_FROM,
            DATE_FORMAT(DATE_TO, '%d.%m.%Y %H:%i:%s') AS DATE_TO,
            USER_ID,
            BRANCH_ID,
            EVENT_COLOR,
            CONTACT_ENTITY_ID,
            DEAL_ENTITY_ID,
            CONFIRMATION_STATUS,
            STATUS,
            TIME_IS_CHANGED,
            VISIT_STATUS,
            DATE_FORMAT(CREATED_AT, '%d.%m.%Y %H:%i:%s') AS CREATED_AT,
            DATE_FORMAT(UPDATED_AT, '%d.%m.%Y %H:%i:%s') AS UPDATED_AT
        FROM artmax_calendar_events 
        WHERE BRANCH_ID = " . (int)$branchId;

        // Фильтр по дате начала (>=)
        if ($dateFrom) {
            $safeDateFrom = $this->connection->getSqlHelper()->forSql($dateFrom);
            $sql .= " AND DATE_FROM >= '$safeDateFrom'";
        }

        // Фильтр по дате окончания (<=)
        if ($dateTo) {
            $safeDateTo = $this->connection->getSqlHelper()->forSql($dateTo);
            $sql .= " AND DATE_TO <= '$safeDateTo'";
        }

        // Фильтр по пользователю
        if ($userId) {
            $sql .= " AND USER_ID = " . (int)$userId;
        }
        
        // Фильтр по врачу (EMPLOYEE_ID)
        if ($employeeId) {
            $sql .= " AND EMPLOYEE_ID = " . (int)$employeeId;
        }

        // Сортировка
        $sql .= " ORDER BY DATE_FROM ASC";

        // Лимит
        if ($limit !== null && $limit > 0) {
            $sql .= " LIMIT " . (int)$limit;
        }

        // Логирование SQL запроса
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "\n=== getEventsByBranch SQL ===\n", 
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "branchId: " . $branchId . "\n", 
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "dateFrom: " . ($dateFrom ?? 'null') . "\n", 
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "dateTo: " . ($dateTo ?? 'null') . "\n", 
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "userId: " . ($userId ?? 'null') . "\n", 
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "employeeId: " . ($employeeId ?? 'null') . "\n", 
            FILE_APPEND | LOCK_EX);
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "SQL: " . $sql . "\n", 
            FILE_APPEND | LOCK_EX);

        try {
            $result = $this->connection->query($sql);
            $events = $result->fetchAll();

            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "Events found: " . count($events) . "\n", 
                FILE_APPEND | LOCK_EX);
            
            // Выводим первые 3 события для проверки
            foreach (array_slice($events, 0, 3) as $idx => $event) {
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "Event #" . ($idx + 1) . ": ID=" . $event['ID'] . ", USER_ID=" . ($event['USER_ID'] ?? 'null') . ", TITLE=" . ($event['TITLE'] ?? 'null') . "\n", 
                    FILE_APPEND | LOCK_EX);
            }

            // Если нужно — можно дополнительно обработать, но уже не обязательно
            return $events ?: [];
        } catch (\Exception $e) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "ERROR: " . $e->getMessage() . "\n", 
                FILE_APPEND | LOCK_EX);
            // Логируйте при необходимости
            // error_log("DB Error in getEventsByBranch: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Получить все события
     */
    public function getAllEvents($limit = 100)
    {
        // Используем время как есть, без всяких конвертаций
        $sql = "
            SELECT e.*, b.NAME as BRANCH_NAME 
            FROM artmax_calendar_branches b ON e.BRANCH_ID = b.ID
            ORDER BY e.DATE_FROM DESC 
            LIMIT " . (int)$limit;

        $result = $this->connection->query($sql);
        return $result->fetchAll();
    }

    /**
     * Получить статистику событий
     */
    public function getEventsStats()
    {
        // Используем время как есть, без всяких конвертаций
        $sql = "
            SELECT 
                COUNT(*) as total_events,
                COUNT(DISTINCT USER_ID) as unique_users,
                COUNT(DISTINCT BRANCH_ID) as branches_used
            FROM artmax_calendar_events
        ";

        $result = $this->connection->query($sql);
        return $result->fetch();
    }

    /**
     * Конвертирует дату из российского формата (день.месяц.год) в стандартный (год-месяц-день)
     * @param string $dateString Дата в формате "04.08.2025 09:00:00"
     * @return string Дата в формате "2025-08-04 09:00:00"
     */
    private function convertRussianDateToStandard($dateString)
    {
        // Логируем входные данные
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "CONVERT_DATE: Input dateString=" . $dateString . "\n", 
            FILE_APPEND | LOCK_EX);
        
        // Проверяем, что строка не пустая
        if (empty($dateString)) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "CONVERT_DATE: Empty string, returning as is\n", 
                FILE_APPEND | LOCK_EX);
            return $dateString;
        }

        // Если дата уже в стандартном формате, возвращаем как есть
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $dateString)) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "CONVERT_DATE: Already in standard format, returning as is\n", 
                FILE_APPEND | LOCK_EX);
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
            
            $result = "{$year}-{$month}-{$day} {$hour}:{$minute}:{$second}";
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "CONVERT_DATE: Russian format converted to: " . $result . "\n", 
                FILE_APPEND | LOCK_EX);
            return $result;
        }

        // Если формат не распознан, пытаемся использовать strtotime как fallback
        $timestamp = strtotime($dateString);
        if ($timestamp !== false) {
            $result = date('Y-m-d H:i:s', $timestamp);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "CONVERT_DATE: strtotime fallback result: " . $result . "\n", 
                FILE_APPEND | LOCK_EX);
            return $result;
        }

        // Если ничего не получилось, возвращаем исходную строку
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "CONVERT_DATE: No conversion possible, returning original: " . $dateString . "\n", 
            FILE_APPEND | LOCK_EX);
        return $dateString;
    }

    /**
     * Обновить сделку для события
     */
    public function updateEventDeal($eventId, $dealId)
    {
        $sql = "UPDATE artmax_calendar_events SET DEAL_ENTITY_ID = " . (int)$dealId . " WHERE ID = " . (int)$eventId;
        
        // Логируем SQL запрос
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "SQL Query updateEventDeal: {$sql}\n", 
            FILE_APPEND | LOCK_EX);
        
        try {
            $this->connection->query($sql);
            
            // Логируем успешное выполнение
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "Successfully updated deal for event {$eventId} with deal ID {$dealId}\n", 
                FILE_APPEND | LOCK_EX);
            
            return true;
        } catch (\Exception $e) {
            error_log('Ошибка обновления сделки события: ' . $e->getMessage());
            
            // Логируем ошибку
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "Error updating deal for event {$eventId}: " . $e->getMessage() . "\n", 
                FILE_APPEND | LOCK_EX);
            
            return false;
        }
    }
    
    /**
     * Обновить контакт для события
     */
    public function updateEventContact($eventId, $contactId)
    {
        $sql = "UPDATE artmax_calendar_events SET CONTACT_ENTITY_ID = " . (int)$contactId . " WHERE ID = " . (int)$eventId;
        
        // Логируем SQL запрос
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "SQL Query updateEventContact: {$sql}\n", 
            FILE_APPEND | LOCK_EX);
        
        try {
            $this->connection->query($sql);
            
            // Логируем успешное выполнение
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "Successfully updated contact for event {$eventId} with contact ID {$contactId}\n", 
                FILE_APPEND | LOCK_EX);
            
            return true;
        } catch (\Exception $e) {
            error_log('Ошибка обновления контакта события: ' . $e->getMessage());
            
            // Логируем ошибку
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "Error updating contact for event {$eventId}: " . $e->getMessage() . "\n", 
                FILE_APPEND | LOCK_EX);
            
            return false;
        }
    }

    /**
     * Обновить заметку для события
     */
    public function updateEventNote($eventId, $noteText)
    {
        $noteText = $this->connection->getSqlHelper()->forSql($noteText);
        $sql = "UPDATE artmax_calendar_events SET NOTE = '{$noteText}' WHERE ID = " . (int)$eventId;
        
        // Логируем SQL запрос
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "SQL Query updateEventNote: {$sql}\n", 
            FILE_APPEND | LOCK_EX);
        
        try {
            $this->connection->query($sql);
            
            // Логируем успешное выполнение
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "Successfully updated note for event {$eventId}\n", 
                FILE_APPEND | LOCK_EX);
            
            return true;
        } catch (\Exception $e) {
            error_log('Ошибка обновления заметки события: ' . $e->getMessage());
            
            // Логируем ошибку
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "Error updating note for event {$eventId}: " . $e->getMessage() . "\n", 
                FILE_APPEND | LOCK_EX);
            
            return false;
        }
    }

    /**
     * Обновить сотрудников филиала
     */
    public function updateBranchEmployees($branchId, $employeeIds)
    {
        try {
            // Удаляем всех сотрудников филиала
            $deleteSql = "DELETE FROM artmax_calendar_branch_employees WHERE BRANCH_ID = " . (int)$branchId;
            $this->connection->query($deleteSql);
            
            // Добавляем новых сотрудников
            if (!empty($employeeIds)) {
                $insertValues = [];
                foreach ($employeeIds as $employeeId) {
                    $employeeId = (int)$employeeId;
                    if ($employeeId > 0) {
                        $insertValues[] = "(" . (int)$branchId . ", " . $employeeId . ")";
                    }
                }
                
                if (!empty($insertValues)) {
                    $insertSql = "INSERT INTO artmax_calendar_branch_employees (BRANCH_ID, EMPLOYEE_ID) VALUES " . implode(', ', $insertValues);
                    $this->connection->query($insertSql);
                }
            }
            
            return true;
        } catch (\Exception $e) {
            error_log('Ошибка обновления сотрудников филиала: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Получить сотрудников филиала
     */
    public function getBranchEmployees($branchId)
    {
        try {
            // Сначала получаем ID сотрудников филиала
            $sql = "SELECT EMPLOYEE_ID FROM artmax_calendar_branch_employees WHERE BRANCH_ID = " . (int)$branchId;
            $result = $this->connection->query($sql);
            
            // Отладочная информация
            error_log("getBranchEmployees: SQL = " . $sql);
            error_log("getBranchEmployees: branchId = " . $branchId);
            
            $employeeIds = [];
            while ($row = $result->fetch()) {
                $employeeIds[] = $row['EMPLOYEE_ID'];
                error_log("getBranchEmployees: Found employee ID = " . $row['EMPLOYEE_ID']);
            }
            
            error_log("getBranchEmployees: Total employee IDs found = " . count($employeeIds));
            
            if (empty($employeeIds)) {
                error_log("getBranchEmployees: No employees found for branch " . $branchId);
                return [];
            }
            
            // Теперь получаем полную информацию о пользователях через CUser::GetList
            $userEntity = new \CUser();
            $employees = [];
            
            $users = $userEntity->GetList(
                ($by = "ID"),
                ($order = "ASC"),
                [
                    'ID' => implode('|', $employeeIds), // Фильтр по ID сотрудников
                    'ACTIVE' => 'Y'
                ],
                [
                    'FIELDS' => ['ID', 'NAME', 'LAST_NAME', 'LOGIN', 'EMAIL']
                ]
            );
            
            while ($user = $users->Fetch()) {
                $employees[] = [
                    'ID' => $user['ID'],
                    'NAME' => $user['NAME'] ?: '',
                    'LAST_NAME' => $user['LAST_NAME'] ?: '',
                    'LOGIN' => $user['LOGIN'] ?: '',
                    'EMAIL' => $user['EMAIL'] ?: ''
                ];
                error_log("getBranchEmployees: Added employee = " . $user['NAME'] . " " . $user['LAST_NAME']);
            }
            
            error_log("getBranchEmployees: Final employees count = " . count($employees));
            return $employees;
        } catch (\Exception $e) {
            error_log('Ошибка получения сотрудников филиала: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Обновить статус подтверждения события
     */
    public function updateEventConfirmationStatus($eventId, $confirmationStatus)
    {
        $sql = "UPDATE artmax_calendar_events SET CONFIRMATION_STATUS = '" . 
               $this->connection->getSqlHelper()->forSql($confirmationStatus) . "' WHERE ID = " . (int)$eventId;
        
        // Логируем SQL запрос
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "SQL Query: {$sql}\n", 
            FILE_APPEND | LOCK_EX);
        
        try {
            $result = $this->connection->query($sql);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "Query executed successfully\n", 
                FILE_APPEND | LOCK_EX);
            return true;
        } catch (\Exception $e) {
            $errorMessage = 'Ошибка обновления статуса подтверждения события: ' . $e->getMessage();
            error_log($errorMessage);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "SQL Error: {$errorMessage}\n", 
                FILE_APPEND | LOCK_EX);
            return false;
        }
    }

    /**
     * Обновить статус визита события
     */
    public function updateEventVisitStatus($eventId, $visitStatus)
    {
        $sql = "UPDATE artmax_calendar_events SET VISIT_STATUS = '" . 
               $this->connection->getSqlHelper()->forSql($visitStatus) . "' WHERE ID = " . (int)$eventId;
        
        try {
            $result = $this->connection->query($sql);
            return true;
        } catch (\Exception $e) {
            $errorMessage = 'Ошибка обновления статуса визита события: ' . $e->getMessage();
            error_log($errorMessage);
            return false;
        }
    }

    /**
     * Получает доступные времена для переноса записи (только пустые слоты без контактов и сделок)
     */
    public function getAvailableTimesForMove($date, $excludeEventId = null, $employeeId = null)
    {
        // Генерируем все возможные времена с 8:00 до 18:00 с интервалом 30 минут
        $availableTimes = [];
        $startHour = 8;
        $endHour = 18;
        
        for ($hour = $startHour; $hour < $endHour; $hour++) {
            for ($minute = 0; $minute < 60; $minute += 30) {
                $timeString = sprintf('%02d:%02d', $hour, $minute);
                $availableTimes[] = [
                    'time' => $timeString,
                    'available' => true
                ];
            }
        }
        
        // Получаем все события на эту дату
        $dateFrom = $date . ' 00:00:00';
        $dateTo = $date . ' 23:59:59';
        
        $sql = "
            SELECT 
                ID,
                DATE_FORMAT(DATE_FROM, '%H:%i') AS TIME_FROM,
                DATE_FORMAT(DATE_TO, '%H:%i') AS TIME_TO,
                CONTACT_ENTITY_ID,
                DEAL_ENTITY_ID
            FROM artmax_calendar_events 
            WHERE DATE_FROM >= '$dateFrom' 
              AND DATE_FROM <= '$dateTo'
              AND STATUS != 'cancelled'";
        
        if ($excludeEventId) {
            $sql .= " AND ID != " . (int)$excludeEventId;
        }
        
        if ($employeeId) {
            $sql .= " AND EMPLOYEE_ID = " . (int)$employeeId;
        }
        
        $result = $this->connection->query($sql);
        
        // Отмечаем занятые времена
        while ($event = $result->fetch()) {
            $timeFrom = $event['TIME_FROM'];
            $timeTo = $event['TIME_TO'];
            
            // Проверяем, есть ли контакт или сделка
            $hasContactOrDeal = !empty($event['CONTACT_ENTITY_ID']) || !empty($event['DEAL_ENTITY_ID']);
            
            // Если есть контакт или сделка, помечаем время как недоступное
            if ($hasContactOrDeal) {
                foreach ($availableTimes as &$timeSlot) {
                    if ($timeSlot['time'] >= $timeFrom && $timeSlot['time'] < $timeTo) {
                        $timeSlot['available'] = false;
                    }
                }
            }
        }
        
        // Возвращаем только доступные времена
        $filteredTimes = array_filter($availableTimes, function($timeSlot) {
            return $timeSlot['available'];
        });
        
        // Переиндексируем массив, чтобы индексы были последовательными
        return array_values($filteredTimes);
    }

    /**
     * Получает расписание врача для переноса записи (только времена событий БЕЗ контактов/сделок)
     */
    public function getDoctorScheduleForMove($date, $employeeId, $excludeEventId = null)
    {
        // Получаем все события врача на эту дату БЕЗ контактов и сделок
        $dateFrom = $date . ' 00:00:00';
        $dateTo = $date . ' 23:59:59';
        
        $sql = "
            SELECT 
                ID,
                DATE_FORMAT(DATE_FROM, '%H:%i') AS TIME_FROM,
                DATE_FORMAT(DATE_TO, '%H:%i') AS TIME_TO,
                CONTACT_ENTITY_ID,
                DEAL_ENTITY_ID
            FROM artmax_calendar_events 
            WHERE DATE_FROM >= '$dateFrom' 
              AND DATE_FROM <= '$dateTo'
              AND EMPLOYEE_ID = " . (int)$employeeId . "
              AND STATUS != 'cancelled'
              AND (CONTACT_ENTITY_ID IS NULL OR CONTACT_ENTITY_ID = 0)
              AND (DEAL_ENTITY_ID IS NULL OR DEAL_ENTITY_ID = 0)";
        
        if ($excludeEventId) {
            $sql .= " AND ID != " . (int)$excludeEventId;
        }
        
        $result = $this->connection->query($sql);
        $availableTimes = [];
        
        // Собираем времена из существующих событий БЕЗ контактов/сделок
        while ($event = $result->fetch()) {
            $timeFrom = $event['TIME_FROM'];
            $timeTo = $event['TIME_TO'];
            
            // Добавляем время начала события
            $availableTimes[] = [
                'time' => $timeFrom,
                'available' => true
            ];
        }
        
        // Сортируем по времени
        usort($availableTimes, function($a, $b) {
            return strcmp($a['time'], $b['time']);
        });
        
        return $availableTimes;
    }

    /**
     * Переносит событие на новое время с обменом местами
     */
    public function moveEvent($eventId, $newDateFrom, $newDateTo, $employeeId = null, $branchId = null)
    {
        try {
            // Получаем данные о переносимом событии
            $movingEvent = $this->getEvent($eventId);
            if (!$movingEvent) {
                return false;
            }

            // Получаем новую дату для поиска события на этом месте
            $newDate = date('Y-m-d', strtotime($newDateFrom));
            $newTimeFrom = date('H:i:s', strtotime($newDateFrom));
            $newTimeTo = date('H:i:s', strtotime($newDateTo));

            // Ищем событие на новом месте (в том же временном интервале)
            $sql = "
                SELECT ID, TITLE, DESCRIPTION, DATE_FROM, DATE_TO, EVENT_COLOR, 
                       CONTACT_ENTITY_ID, DEAL_ENTITY_ID, EMPLOYEE_ID, BRANCH_ID
                FROM artmax_calendar_events 
                WHERE DATE_FROM = '$newDateFrom' 
                  AND DATE_TO = '$newDateTo'
                  AND STATUS != 'cancelled'
                  AND ID != " . (int)$eventId;

            $result = $this->connection->query($sql);
            $targetEvent = $result->fetch();

            // Начинаем транзакцию
            $this->connection->startTransaction();

            if ($targetEvent) {
                // Если на новом месте есть событие - обмениваемся местами
                
                // 1. Обновляем событие на новом месте (становится переносимым)
                // Оно получает старое время, старого врача и старый филиал от переносимого события
                $oldDateFrom = $this->convertRussianDateToStandard($movingEvent['DATE_FROM']);
                $oldDateTo = $this->convertRussianDateToStandard($movingEvent['DATE_TO']);
                $oldEmployeeId = $movingEvent['EMPLOYEE_ID'];
                $oldBranchId = $movingEvent['BRANCH_ID'];
                
                // Логируем для отладки
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "MOVE_EVENT: Original dates from moving event:\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "  - Original DATE_FROM: " . $movingEvent['DATE_FROM'] . "\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "  - Converted DATE_FROM: " . $oldDateFrom . "\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "  - Original DATE_TO: " . $movingEvent['DATE_TO'] . "\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "  - Converted DATE_TO: " . $oldDateTo . "\n", 
                    FILE_APPEND | LOCK_EX);

                $targetSet = [];
                $targetSet[] = "DATE_FROM = '" . $this->connection->getSqlHelper()->forSql($oldDateFrom) . "'";
                $targetSet[] = "DATE_TO = '" . $this->connection->getSqlHelper()->forSql($oldDateTo) . "'";
                $targetSet[] = "ORIGINAL_DATE_FROM = '" . $this->connection->getSqlHelper()->forSql($oldDateFrom) . "'";
                $targetSet[] = "ORIGINAL_DATE_TO = '" . $this->connection->getSqlHelper()->forSql($oldDateTo) . "'";
                $targetSet[] = "TIME_IS_CHANGED = 0";
                $targetSet[] = "UPDATED_AT = NOW()";
                
                // Меняем врача и филиал на старые от переносимого события
                if ($oldEmployeeId) {
                    $targetSet[] = "EMPLOYEE_ID = " . (int)$oldEmployeeId;
                } else {
                    $targetSet[] = "EMPLOYEE_ID = NULL";
                }
                if ($oldBranchId) {
                    $targetSet[] = "BRANCH_ID = " . (int)$oldBranchId;
                }

                $updateTargetSql = "UPDATE artmax_calendar_events SET " . implode(', ', $targetSet) . " WHERE ID = " . (int)$targetEvent['ID'];
                $this->connection->query($updateTargetSql);

                // 2. Обновляем переносимое событие (получает новое время и флаг изменения)
                $movingSet = [];
                $movingSet[] = "DATE_FROM = '" . $this->connection->getSqlHelper()->forSql($newDateFrom) . "'";
                $movingSet[] = "DATE_TO = '" . $this->connection->getSqlHelper()->forSql($newDateTo) . "'";
                $movingSet[] = "TIME_IS_CHANGED = 1";
                if ($employeeId) {
                    $movingSet[] = "EMPLOYEE_ID = " . (int)$employeeId;
                }
                if ($branchId) {
                    $movingSet[] = "BRANCH_ID = " . (int)$branchId;
                }
                $movingSet[] = "UPDATED_AT = NOW()";

                $updateMovingSql = "UPDATE artmax_calendar_events SET " . implode(', ', $movingSet) . " WHERE ID = " . (int)$eventId;
                $this->connection->query($updateMovingSql);

            } else {
                // Если на новом месте пусто - просто переносим событие
                $movingSet = [];
                $movingSet[] = "DATE_FROM = '" . $this->connection->getSqlHelper()->forSql($newDateFrom) . "'";
                $movingSet[] = "DATE_TO = '" . $this->connection->getSqlHelper()->forSql($newDateTo) . "'";
                $movingSet[] = "TIME_IS_CHANGED = 1";
                if ($employeeId) {
                    $movingSet[] = "EMPLOYEE_ID = " . (int)$employeeId;
                }
                if ($branchId) {
                    $movingSet[] = "BRANCH_ID = " . (int)$branchId;
                }
                $movingSet[] = "UPDATED_AT = NOW()";

                $updateMovingSql = "UPDATE artmax_calendar_events SET " . implode(', ', $movingSet) . " WHERE ID = " . (int)$eventId;
                $this->connection->query($updateMovingSql);
            }

            // Подтверждаем транзакцию
            $this->connection->commitTransaction();
            return true;

        } catch (\Exception $e) {
            // Откатываем транзакцию в случае ошибки
            $this->connection->rollbackTransaction();
            error_log('Ошибка переноса события: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Получает все события пользователя
     * @param int $userId ID пользователя
     * @return array|false Массив событий или false при ошибке
     */
    public function getEventsByUser($userId)
    {
        if (!$userId) {
            return false;
        }

        try {
            $sql = "SELECT * FROM artmax_calendar_events WHERE USER_ID = " . (int)$userId . " ORDER BY DATE_FROM ASC";
            $result = $this->connection->query($sql);
            
            $events = [];
            while ($row = $result->fetch()) {
                $events[] = $row;
            }
            
            return $events;
        } catch (\Exception $e) {
            error_log('Ошибка получения событий пользователя: ' . $e->getMessage());
            return false;
        }
    }
}
