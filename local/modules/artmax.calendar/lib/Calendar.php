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
        
        // Используем время как есть, без всяких конвертаций
        $sql = "INSERT INTO artmax_calendar_events (TITLE, DESCRIPTION, DATE_FROM, DATE_TO, USER_ID, BRANCH_ID, EVENT_COLOR, EMPLOYEE_ID) VALUES ('" . 
               $this->connection->getSqlHelper()->forSql($title) . "', '" . 
               $this->connection->getSqlHelper()->forSql($description) . "', '" .
               $this->connection->getSqlHelper()->forSql($dateFrom) . "', '" .
               $this->connection->getSqlHelper()->forSql($dateTo) . "', " . 
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
        // Если branchId не передан, получаем его из существующего события
        if (!$branchId) {
            $existingEvent = $this->getEvent($id);
            if ($existingEvent) {
                $branchId = $existingEvent['BRANCH_ID'];
            }
        }
        
        // Используем время как есть, без всяких конвертаций
        $sql = "
            UPDATE artmax_calendar_events 
            SET TITLE = '" . $this->connection->getSqlHelper()->forSql($title) . "', 
                DESCRIPTION = '" . $this->connection->getSqlHelper()->forSql($description) . "', 
                DATE_FROM = '" . $this->connection->getSqlHelper()->forSql($dateFrom) . "', 
                DATE_TO = '" . $this->connection->getSqlHelper()->forSql($dateTo) . "', 
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
     * Проверить доступность времени для конкретного врача
     */
    public function isTimeAvailableForDoctor($dateFrom, $dateTo, $employeeId, $excludeId = null)
    {
        // Если врач не указан, проверяем конфликты с событиями без врача
        if (!$employeeId) {
            $employeeId = null;
        }

        // Логируем параметры
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "IS_TIME_AVAILABLE_FOR_DOCTOR: Checking for employeeId=" . $employeeId . ", dateFrom=" . $dateFrom . ", dateTo=" . $dateTo . "\n", 
            FILE_APPEND | LOCK_EX);
        
        // Проверяем пересечение временных интервалов только для конкретного врача и только активных записей
        if ($employeeId === null) {
            $sql = "SELECT COUNT(*) as count FROM artmax_calendar_events WHERE EMPLOYEE_ID IS NULL AND STATUS != 'cancelled' AND DATE_FROM < '" . $this->connection->getSqlHelper()->forSql($dateTo) . "' AND DATE_TO > '" . $this->connection->getSqlHelper()->forSql($dateFrom) . "'";
        } else {
            $sql = "SELECT COUNT(*) as count FROM artmax_calendar_events WHERE EMPLOYEE_ID = " . (int)$employeeId . " AND STATUS != 'cancelled' AND DATE_FROM < '" . $this->connection->getSqlHelper()->forSql($dateTo) . "' AND DATE_TO > '" . $this->connection->getSqlHelper()->forSql($dateFrom) . "'";
        }
        
        if ($excludeId) {
            $sql .= " AND ID != " . (int)$excludeId;
        }
        
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "IS_TIME_AVAILABLE_FOR_DOCTOR: SQL = " . $sql . "\n", 
            FILE_APPEND | LOCK_EX);
        
        $result = $this->connection->query($sql);
        $row = $result->fetch();
        
        $count = $row['count'];
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "IS_TIME_AVAILABLE_FOR_DOCTOR: Found " . $count . " conflicting events for doctor\n", 
            FILE_APPEND | LOCK_EX);
        
        // Если есть конфликты, показываем какие именно события конфликтуют
        if ($count > 0) {
            if ($employeeId === null) {
                $conflictSql = "SELECT ID, TITLE, DATE_FROM, DATE_TO FROM artmax_calendar_events WHERE EMPLOYEE_ID IS NULL AND STATUS != 'cancelled' AND DATE_FROM < '" . $this->connection->getSqlHelper()->forSql($dateTo) . "' AND DATE_TO > '" . $this->connection->getSqlHelper()->forSql($dateFrom) . "'";
            } else {
                $conflictSql = "SELECT ID, TITLE, DATE_FROM, DATE_TO FROM artmax_calendar_events WHERE EMPLOYEE_ID = " . (int)$employeeId . " AND STATUS != 'cancelled' AND DATE_FROM < '" . $this->connection->getSqlHelper()->forSql($dateTo) . "' AND DATE_TO > '" . $this->connection->getSqlHelper()->forSql($dateFrom) . "'";
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
    public function getEventsByBranch($branchId, $dateFrom = null, $dateTo = null, $userId = null, $limit = null)
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

        // Сортировка
        $sql .= " ORDER BY DATE_FROM ASC";

        // Лимит
        if ($limit !== null && $limit > 0) {
            $sql .= " LIMIT " . (int)$limit;
        }

        try {
            $result = $this->connection->query($sql);
            $events = $result->fetchAll();

            // Если нужно — можно дополнительно обработать, но уже не обязательно
            return $events ?: [];
        } catch (\Exception $e) {
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
            
            $employeeIds = [];
            while ($row = $result->fetch()) {
                $employeeIds[] = $row['EMPLOYEE_ID'];
            }
            
            if (empty($employeeIds)) {
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
            }
            
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
}
