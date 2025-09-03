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
    public function addEvent($title, $description, $dateFrom, $dateTo, $userId, $branchId = 1, $eventColor = '#3498db')
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
        $sql = "INSERT INTO artmax_calendar_events (TITLE, DESCRIPTION, DATE_FROM, DATE_TO, USER_ID, BRANCH_ID, EVENT_COLOR) VALUES ('" . 
               $this->connection->getSqlHelper()->forSql($title) . "', '" . 
               $this->connection->getSqlHelper()->forSql($description) . "', '" .
               $this->connection->getSqlHelper()->forSql($dateFrom) . "', '" .
               $this->connection->getSqlHelper()->forSql($dateTo) . "', " . 
               (int)$userId . ", " . 
               (int)$branchId . ", '" . 
               $this->connection->getSqlHelper()->forSql($eventColor) . "')";

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
        $sql = "SELECT TIMEZONE_NAME FROM artmax_calendar_timezone_settings WHERE BRANCH_ID = " . (int)$branchId;
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
                DATE_FORMAT(DATE_FROM, '%d.%m.%Y %H:%i:%s') AS DATE_FROM_RAW,
                DATE_FORMAT(DATE_TO, '%d.%m.%Y %H:%i:%s') AS DATE_TO_RAW,
                TITLE,
                DESCRIPTION,
                USER_ID,
                BRANCH_ID,
                EVENT_COLOR,
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
    public function updateEvent($id, $title, $description, $dateFrom, $dateTo, $eventColor = null, $branchId = null)
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
        
        $sql .= " WHERE ID = " . (int)$id;

        // Используем время как есть, без всяких конвертаций
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
     * Проверить доступность времени
     */
    public function isTimeAvailable($dateFrom, $dateTo, $userId, $excludeId = null)
    {
        // Используем время как есть, без всяких конвертаций
        // Логируем параметры
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "IS_TIME_AVAILABLE: Checking for userId=" . $userId . ", dateFrom=" . $dateFrom . ", dateTo=" . $dateTo . "\n", 
            FILE_APPEND | LOCK_EX);
        
        // Проверяем пересечение временных интервалов
        // Интервалы пересекаются, если: начало существующего < конец нового И конец существующего > начало нового
        $sql = "SELECT COUNT(*) as count FROM artmax_calendar_events WHERE USER_ID = " . (int)$userId . " AND DATE_FROM < '" . $this->connection->getSqlHelper()->forSql($dateTo) . "' AND DATE_TO > '" . $this->connection->getSqlHelper()->forSql($dateFrom) . "'";
        
        if ($excludeId) {
            $sql .= " AND ID != " . (int)$excludeId;
        }
        
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "IS_TIME_AVAILABLE: SQL = " . $sql . "\n", 
            FILE_APPEND | LOCK_EX);
        
        $result = $this->connection->query($sql);
        $row = $result->fetch();
        
        $count = $row['count'];
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "IS_TIME_AVAILABLE: Found " . $count . " conflicting events\n", 
            FILE_APPEND | LOCK_EX);
        
        // Если есть конфликты, показываем какие именно события конфликтуют
        if ($count > 0) {
            // Используем время как есть, без всяких конвертаций
            $conflictSql = "SELECT ID, TITLE, DATE_FROM, DATE_TO FROM artmax_calendar_events WHERE USER_ID = " . (int)$userId . " AND DATE_FROM < '" . $this->connection->getSqlHelper()->forSql($dateTo) . "' AND DATE_TO > '" . $this->connection->getSqlHelper()->forSql($dateFrom) . "'";
            $conflictResult = $this->connection->query($conflictSql);
            $conflicts = $conflictResult->fetchAll();
            
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "IS_TIME_AVAILABLE: Conflicting events: " . json_encode($conflicts) . "\n", 
                FILE_APPEND | LOCK_EX);
        }
        
        return $count == 0; 
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
}
