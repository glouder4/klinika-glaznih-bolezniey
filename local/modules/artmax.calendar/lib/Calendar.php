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
        // Обрабатываем EMPLOYEE_ID: если передан и не пустой, преобразуем в int, иначе NULL
        $employeeIdValue = ($employeeId !== null && $employeeId !== '' && $employeeId !== '0') 
            ? (int)$employeeId 
            : 'NULL';
        
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
               $employeeIdValue . ")";

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
            $checkSql = "SELECT DATE_FROM, DATE_TO, EVENT_COLOR, EMPLOYEE_ID FROM artmax_calendar_events WHERE ID = {$eventId}";
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
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                        "  - EMPLOYEE_ID: " . ($savedEvent['EMPLOYEE_ID'] ?? 'NULL') . "\n", 
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
    public function getBranchTimezone($branchId)
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
    public function convertTimeToBranchTimezone($timeString, $branchTimezone)
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
                DATE_FORMAT(ORIGINAL_DATE_FROM, '%d.%m.%Y %H:%i:%s') AS ORIGINAL_DATE_FROM,
                DATE_FORMAT(ORIGINAL_DATE_TO, '%d.%m.%Y %H:%i:%s') AS ORIGINAL_DATE_TO,
                TITLE,
                DESCRIPTION,
                USER_ID,
                BRANCH_ID,
                EVENT_COLOR,
                CONTACT_ENTITY_ID,
                DEAL_ENTITY_ID,
                ACTIVITY_ID,
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
        
        // Нормализуем даты для корректного сравнения
        // $existingEvent['DATE_FROM'] в формате dd.mm.yyyy HH:ii:ss, $dateFrom в формате yyyy-mm-dd HH:ii:ss
        $normalizeDate = function($dateStr) {
            if (empty($dateStr)) return '';
            // Если дата в формате dd.mm.yyyy HH:ii:ss, конвертируем в yyyy-mm-dd HH:ii:ss
            if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})\s+(\d{2}):(\d{2}):(\d{2})$/', $dateStr, $matches)) {
                return sprintf('%s-%s-%s %s:%s:%s', $matches[3], $matches[2], $matches[1], $matches[4], $matches[5], $matches[6]);
            }
            // Если уже в формате yyyy-mm-dd HH:ii:ss, возвращаем как есть
            return $dateStr;
        };
        
        // Проверяем, изменилось ли время
        $timeChanged = 0;
        if ($existingEvent['ORIGINAL_DATE_FROM'] && $existingEvent['ORIGINAL_DATE_TO']) {
            // Если есть оригинальные даты, сравниваем с ними
            $originalFromNormalized = $normalizeDate($existingEvent['ORIGINAL_DATE_FROM']);
            $originalToNormalized = $normalizeDate($existingEvent['ORIGINAL_DATE_TO']);
            if ($originalFromNormalized != $dateFrom || $originalToNormalized != $dateTo) {
                $timeChanged = 1;
            }
        } else {
            // Если нет оригинальных дат, сравниваем с текущими
            $existingFromNormalized = $normalizeDate($existingEvent['DATE_FROM']);
            $existingToNormalized = $normalizeDate($existingEvent['DATE_TO']);
            if ($existingFromNormalized != $dateFrom || $existingToNormalized != $dateTo) {
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
    public function updateEventStatus($eventId, $status, $userId = null)
    {
        // Получаем старое значение статуса для логирования
        $oldEvent = $this->getEvent($eventId);
        $oldStatus = $oldEvent ? ($oldEvent['STATUS'] ?? 'active') : null;
        
        $sql = "
            UPDATE artmax_calendar_events 
            SET STATUS = '" . $this->connection->getSqlHelper()->forSql($status) . "',
                UPDATED_AT = NOW()
            WHERE ID = " . (int)$eventId;
        
        $result = $this->connection->query($sql);
        
        // Если запись отменена, переводим сделку в "Проиграна" (Неуспешная) и создаем пустую запись
        if ($result && $status === 'cancelled') {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "UPDATE_EVENT_STATUS: Запись отменена (cancelled), вызываем updateDealStatusOnCancel для события ID={$eventId}\n", 
                FILE_APPEND | LOCK_EX);
            
            // Переводим сделку в "Проиграна"
            $this->updateDealStatusOnCancel($eventId);
            
            // Создаем пустую запись на место отмененной
            if ($oldEvent) {
                // Получаем даты в стандартном формате из БД напрямую
                $dateSql = "SELECT DATE_FORMAT(DATE_FROM, '%Y-%m-%d %H:%i:%s') AS DATE_FROM_STANDARD, 
                                   DATE_FORMAT(DATE_TO, '%Y-%m-%d %H:%i:%s') AS DATE_TO_STANDARD
                            FROM artmax_calendar_events 
                            WHERE ID = " . (int)$eventId;
                $dateResult = $this->connection->query($dateSql);
                $dateRow = $dateResult->fetch();
                
                if ($dateRow) {
                    $dateFromStandard = $dateRow['DATE_FROM_STANDARD'];
                    $dateToStandard = $dateRow['DATE_TO_STANDARD'];
                    
                    // Создаем пустую запись напрямую через SQL, минуя проверки доступности
                    // так как место освободилось после отмены записи
                    $employeeIdValue = (!empty($oldEvent['EMPLOYEE_ID']) && $oldEvent['EMPLOYEE_ID'] !== '0') 
                        ? (int)$oldEvent['EMPLOYEE_ID'] 
                        : 'NULL';
                    
                    $insertSql = "INSERT INTO artmax_calendar_events 
                                  (TITLE, DESCRIPTION, DATE_FROM, DATE_TO, ORIGINAL_DATE_FROM, ORIGINAL_DATE_TO, 
                                   TIME_IS_CHANGED, USER_ID, BRANCH_ID, EVENT_COLOR, EMPLOYEE_ID, 
                                   CONTACT_ENTITY_ID, DEAL_ENTITY_ID, ACTIVITY_ID, STATUS) 
                                  VALUES (
                                      'Пустая запись', 
                                      '', 
                                      '" . $this->connection->getSqlHelper()->forSql($dateFromStandard) . "', 
                                      '" . $this->connection->getSqlHelper()->forSql($dateToStandard) . "', 
                                      '" . $this->connection->getSqlHelper()->forSql($dateFromStandard) . "', 
                                      '" . $this->connection->getSqlHelper()->forSql($dateToStandard) . "', 
                                      0, 
                                      " . (int)($userId ?: $oldEvent['USER_ID']) . ", 
                                      " . (int)$oldEvent['BRANCH_ID'] . ", 
                                      '" . $this->connection->getSqlHelper()->forSql($oldEvent['EVENT_COLOR'] ?? '#3498db') . "', 
                                      {$employeeIdValue}, 
                                      NULL, 
                                      NULL, 
                                      NULL, 
                                      'active'
                                  )";
                    
                    $insertResult = $this->connection->query($insertSql);
                    
                    if ($insertResult) {
                        $newEventId = $this->connection->getInsertedId();
                        
                        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                            "UPDATE_EVENT_STATUS: Создана пустая запись ID={$newEventId} на место отмененной записи ID={$eventId}\n" .
                            "  - DATE_FROM: {$dateFromStandard}\n" .
                            "  - DATE_TO: {$dateToStandard}\n" .
                            "  - BRANCH_ID: {$oldEvent['BRANCH_ID']}\n" .
                            "  - EMPLOYEE_ID: " . ($oldEvent['EMPLOYEE_ID'] ?? 'NULL') . "\n" .
                            "  - CONTACT_ENTITY_ID: NULL (пустая запись)\n" .
                            "  - DEAL_ENTITY_ID: NULL (пустая запись)\n", 
                            FILE_APPEND | LOCK_EX);
                    } else {
                        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                            "UPDATE_EVENT_STATUS: Ошибка создания пустой записи для отмененной записи ID={$eventId}\n", 
                            FILE_APPEND | LOCK_EX);
                    }
                } else {
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                        "UPDATE_EVENT_STATUS: Не удалось получить даты для создания пустой записи\n", 
                        FILE_APPEND | LOCK_EX);
                }
            }
        }
        
        // Логируем изменение статуса
        if ($result && $oldStatus && $oldStatus != $status) {
            $journal = new \Artmax\Calendar\Journal();
            
            if ($status === 'cancelled') {
                $journal->writeEvent(
                    $eventId,
                    'EVENT_CANCELLED',
                    'Artmax\Calendar\Calendar::updateEventStatus',
                    $userId,
                    'STATUS=' . $oldStatus . '->' . $status
                );
            } elseif ($oldStatus === 'cancelled' && $status === 'active') {
                $journal->writeEvent(
                    $eventId,
                    'EVENT_RESTORED',
                    'Artmax\Calendar\Calendar::updateEventStatus',
                    $userId,
                    'STATUS=' . $oldStatus . '->' . $status
                );
            } else {
                $journal->writeEvent(
                    $eventId,
                    'EVENT_STATUS_CHANGED',
                    'Artmax\Calendar\Calendar::updateEventStatus',
                    $userId,
                    'STATUS=' . $oldStatus . '->' . $status
                );
            }
        }
        
        return $result;
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
            EMPLOYEE_ID,
            BRANCH_ID,
            EVENT_COLOR,
            CONTACT_ENTITY_ID,
            DEAL_ENTITY_ID,
            ACTIVITY_ID,
            NOTE,
            CONFIRMATION_STATUS,
            STATUS,
            TIME_IS_CHANGED,
            VISIT_STATUS,
            DATE_FORMAT(CREATED_AT, '%d.%m.%Y %H:%i:%s') AS CREATED_AT,
            DATE_FORMAT(UPDATED_AT, '%d.%m.%Y %H:%i:%s') AS UPDATED_AT
        FROM artmax_calendar_events 
        WHERE BRANCH_ID = " . (int)$branchId . "
        AND (STATUS IS NULL OR STATUS != 'cancelled')";

        // Фильтр по диапазону дат - событие попадает в диапазон, если оно пересекается с ним
        // Событие пересекается с диапазоном, если: DATE_FROM <= dateTo AND DATE_TO >= dateFrom
        if ($dateFrom && $dateTo) {
            $safeDateFrom = $this->connection->getSqlHelper()->forSql($dateFrom);
            $safeDateTo = $this->connection->getSqlHelper()->forSql($dateTo);
            // Добавляем время к начальной дате (начало дня) и конечной (конец дня)
            $safeDateFromWithTime = $safeDateFrom . ' 00:00:00';
            $safeDateToWithTime = $safeDateTo . ' 23:59:59';
            // Событие попадает в диапазон, если оно начинается до конца диапазона И заканчивается после начала диапазона
            $sql .= " AND DATE_FROM <= '$safeDateToWithTime' AND DATE_TO >= '$safeDateFromWithTime'";
        } elseif ($dateFrom) {
            // Если указана только начальная дата - события, которые заканчиваются после начала диапазона
            $safeDateFrom = $this->connection->getSqlHelper()->forSql($dateFrom);
            $safeDateFromWithTime = $safeDateFrom . ' 00:00:00';
            $sql .= " AND DATE_TO >= '$safeDateFromWithTime'";
        } elseif ($dateTo) {
            // Если указана только конечная дата - события, которые начинаются до конца диапазона
            $safeDateTo = $this->connection->getSqlHelper()->forSql($dateTo);
            $safeDateToWithTime = $safeDateTo . ' 23:59:59';
            $sql .= " AND DATE_FROM <= '$safeDateToWithTime'";
        }

        // Фильтр по пользователю
        if ($userId) {
            $sql .= " AND USER_ID = " . (int)$userId;
        }
        
        // Фильтр по врачу (EMPLOYEE_ID) - ИСПРАВЛЕНО: врач видит записи где он назначен ИЛИ создал
        if ($employeeId) {
            $sql .= " AND (EMPLOYEE_ID = " . (int)$employeeId . " OR USER_ID = " . (int)$employeeId . ")";
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
            
            // Обновляем поле подтверждения в сделке
            $this->updateDealConfirmationField($eventId, $confirmationStatus);
            
            // Если запись подтверждена, переводим сделку в статус "В работе"
            if ($confirmationStatus === 'confirmed') {
                $this->updateDealStatusOnConfirmation($eventId);
            }
            
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
            
            // Обновляем поле визита в сделке
            $this->updateDealVisitField($eventId, $visitStatus);
            
            // Если клиент пришел, обновляем привязанную сделку на "Завершена успешно"
            if ($visitStatus === 'client_came') {
                $this->updateDealStatusOnVisit($eventId);
            }
            
            // Если клиент не пришел, обновляем привязанную сделку на "Проиграна"
            if ($visitStatus === 'client_did_not_come') {
                $this->updateDealStatusOnCancel($eventId);
            }
            
            return true;
        } catch (\Exception $e) {
            $errorMessage = 'Ошибка обновления статуса визита события: ' . $e->getMessage();
            error_log($errorMessage);
            return false;
        }
    }
    
    /**
     * Обновить статус сделки при подтверждении визита (клиент пришел)
     * @param int $eventId ID события
     */
    private function updateDealStatusOnVisit($eventId)
    {
        // Получаем данные события
        $event = $this->getEvent($eventId);
        
        if (!$event || empty($event['DEAL_ENTITY_ID']) || !\CModule::IncludeModule('crm')) {
            return;
        }
        
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "UPDATE_VISIT_STATUS: Начинаем обновление сделки ID={$event['DEAL_ENTITY_ID']} на WON\n", 
            FILE_APPEND | LOCK_EX);
        
        $dealId = (int)$event['DEAL_ENTITY_ID'];
        $deal = new \CCrmDeal(false);
        
        // Обновляем статус сделки на "Завершена успешно"
        $updateFields = [
            'STAGE_ID' => 'WON',
        ];
        
        $dealUpdateResult = $deal->Update($dealId, $updateFields);
        
        if ($dealUpdateResult) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "UPDATE_VISIT_STATUS: Сделка ID={$dealId} переведена в статус 'Завершена успешно' (WON)\n", 
                FILE_APPEND | LOCK_EX);
        } else {
            $dealError = $deal->LAST_ERROR;
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "UPDATE_VISIT_STATUS: Ошибка обновления сделки ID={$dealId}: {$dealError}\n", 
                FILE_APPEND | LOCK_EX);
        }
    }
    
    /**
     * Обновить статус сделки при отмене записи или когда клиент не пришел
     * @param int $eventId ID события
     */
    private function updateDealStatusOnCancel($eventId)
    {
        // Получаем данные события
        $event = $this->getEvent($eventId);
        
        if (!$event) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "UPDATE_DEAL_ON_CANCEL: Событие ID={$eventId} не найдено\n", 
                FILE_APPEND | LOCK_EX);
            return;
        }
        
        if (empty($event['DEAL_ENTITY_ID'])) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "UPDATE_DEAL_ON_CANCEL: У события ID={$eventId} нет привязанной сделки (DEAL_ENTITY_ID пуст)\n", 
                FILE_APPEND | LOCK_EX);
            return;
        }
        
        if (!\CModule::IncludeModule('crm')) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "UPDATE_DEAL_ON_CANCEL: Модуль CRM не подключен\n", 
                FILE_APPEND | LOCK_EX);
            return;
        }
        
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "UPDATE_DEAL_ON_CANCEL: Начинаем обновление сделки ID={$event['DEAL_ENTITY_ID']} на LOSE\n", 
            FILE_APPEND | LOCK_EX);
        
        $dealId = (int)$event['DEAL_ENTITY_ID'];
        $deal = new \CCrmDeal(false);
        
        // Обновляем статус сделки на "Проиграна"
        $updateFields = [
            'STAGE_ID' => 'LOSE',
        ];
        
        $dealUpdateResult = $deal->Update($dealId, $updateFields);
        
        if ($dealUpdateResult) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "UPDATE_DEAL_ON_CANCEL: Сделка ID={$dealId} переведена в статус 'Проиграна' (LOSE)\n", 
                FILE_APPEND | LOCK_EX);
        } else {
            $dealError = $deal->LAST_ERROR;
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "UPDATE_DEAL_ON_CANCEL: Ошибка обновления сделки ID={$dealId}: {$dealError}\n", 
                FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * Обновить статус сделки при подтверждении записи
     * @param int $eventId ID события
     */
    private function updateDealStatusOnConfirmation($eventId)
    {
        // Получаем данные события
        $event = $this->getEvent($eventId);
        
        if (!$event || empty($event['DEAL_ENTITY_ID']) || !\CModule::IncludeModule('crm')) {
            return;
        }
        
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "UPDATE_CONFIRMATION_STATUS: Начинаем обновление сделки ID={$event['DEAL_ENTITY_ID']} на EXECUTING\n", 
            FILE_APPEND | LOCK_EX);
        
        $dealId = (int)$event['DEAL_ENTITY_ID'];
        $deal = new \CCrmDeal(false);
        
        // Обновляем статус сделки на "В работе"
        $updateFields = [
            'STAGE_ID' => 'EXECUTING',
        ];
        
        $dealUpdateResult = $deal->Update($dealId, $updateFields);
        
        if ($dealUpdateResult) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "UPDATE_CONFIRMATION_STATUS: Сделка ID={$dealId} переведена в статус 'В работе' (EXECUTING)\n", 
                FILE_APPEND | LOCK_EX);
        } else {
            $dealError = $deal->LAST_ERROR;
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "UPDATE_CONFIRMATION_STATUS: Ошибка обновления сделки ID={$dealId}: {$dealError}\n", 
                FILE_APPEND | LOCK_EX);
        }
    }
    
    /**
     * Обновить поле "Подтверждение" в сделке
     * @param int $eventId ID события
     * @param string $confirmationStatus Статус подтверждения (pending, confirmed, not_confirmed)
     */
    private function updateDealConfirmationField($eventId, $confirmationStatus)
    {
        // Получаем данные события
        $event = $this->getEvent($eventId);
        
        if (!$event || empty($event['DEAL_ENTITY_ID']) || !\CModule::IncludeModule('crm')) {
            return;
        }
        
        $dealId = (int)$event['DEAL_ENTITY_ID'];
        $fieldCode = \Bitrix\Main\Config\Option::get('artmax.calendar', 'deal_confirmation_field', 'UF_CRM_CALENDAR_CONFIRM');
        
        // Получаем ID значения списка по XML_ID
        $enumValue = \CUserFieldEnum::GetList(
            [],
            [
                'USER_FIELD_NAME' => $fieldCode,
                'XML_ID' => $confirmationStatus
            ]
        )->Fetch();
        
        if (!$enumValue) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "UPDATE_DEAL_CONFIRMATION_FIELD: Не найдено значение для XML_ID={$confirmationStatus}\n", 
                FILE_APPEND | LOCK_EX);
            return;
        }
        
        $deal = new \CCrmDeal(false);
        $updateFields = [
            $fieldCode => $enumValue['ID']
        ];
        
        $dealUpdateResult = $deal->Update($dealId, $updateFields);
        
        if ($dealUpdateResult) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "UPDATE_DEAL_CONFIRMATION_FIELD: Сделка ID={$dealId} обновлена. Подтверждение={$confirmationStatus}\n", 
                FILE_APPEND | LOCK_EX);
        } else {
            $dealError = $deal->LAST_ERROR;
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "UPDATE_DEAL_CONFIRMATION_FIELD: Ошибка обновления сделки ID={$dealId}: {$dealError}\n", 
                FILE_APPEND | LOCK_EX);
        }
    }
    
    /**
     * Обновить поле "Визит" в сделке
     * @param int $eventId ID события
     * @param string $visitStatus Статус визита (not_specified, client_came, client_did_not_come)
     */
    private function updateDealVisitField($eventId, $visitStatus)
    {
        // Получаем данные события
        $event = $this->getEvent($eventId);
        
        if (!$event || empty($event['DEAL_ENTITY_ID']) || !\CModule::IncludeModule('crm')) {
            return;
        }
        
        $dealId = (int)$event['DEAL_ENTITY_ID'];
        $fieldCode = \Bitrix\Main\Config\Option::get('artmax.calendar', 'deal_visit_field', 'UF_CRM_CALENDAR_VISIT');
        
        // Получаем ID значения списка по XML_ID
        $enumValue = \CUserFieldEnum::GetList(
            [],
            [
                'USER_FIELD_NAME' => $fieldCode,
                'XML_ID' => $visitStatus
            ]
        )->Fetch();
        
        if (!$enumValue) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "UPDATE_DEAL_VISIT_FIELD: Не найдено значение для XML_ID={$visitStatus}\n", 
                FILE_APPEND | LOCK_EX);
            return;
        }
        
        $deal = new \CCrmDeal(false);
        $updateFields = [
            $fieldCode => $enumValue['ID']
        ];
        
        $dealUpdateResult = $deal->Update($dealId, $updateFields);
        
        if ($dealUpdateResult) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "UPDATE_DEAL_VISIT_FIELD: Сделка ID={$dealId} обновлена. Визит={$visitStatus}\n", 
                FILE_APPEND | LOCK_EX);
        } else {
            $dealError = $deal->LAST_ERROR;
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "UPDATE_DEAL_VISIT_FIELD: Ошибка обновления сделки ID={$dealId}: {$dealError}\n", 
                FILE_APPEND | LOCK_EX);
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
    public function getDoctorScheduleForMove($date, $employeeId, $excludeEventId = null, $branchId = null)
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
        
        // Фильтруем по филиалу, если он указан
        if ($branchId) {
            $sql .= " AND BRANCH_ID = " . (int)$branchId;
        }
        
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
    public function moveEvent($eventId, $newDateFrom, $newDateTo, $employeeId = null, $branchId = null, $userId = null)
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

            // Ищем событие на новом месте (начинается в то же время)
            // Если найдем - просто меняемся местами
            $safeNewDateFrom = $this->connection->getSqlHelper()->forSql($newDateFrom);
            
            $sql = "
                SELECT ID, TITLE, DESCRIPTION, DATE_FROM, DATE_TO, EVENT_COLOR, 
                       CONTACT_ENTITY_ID, DEAL_ENTITY_ID, EMPLOYEE_ID, BRANCH_ID
                FROM artmax_calendar_events 
                WHERE DATE_FROM = '$safeNewDateFrom'
                  AND STATUS != 'cancelled'
                  AND ID != " . (int)$eventId;
            
            // Если указан employeeId, ищем среди событий этого врача
            if ($employeeId) {
                $sql .= " AND EMPLOYEE_ID = " . (int)$employeeId;
            }
            
            // Если указан branchId, ищем среди событий этого филиала
            if ($branchId) {
                $sql .= " AND BRANCH_ID = " . (int)$branchId;
            }

            $result = $this->connection->query($sql);
            $targetEvent = $result->fetch();
            
            // Логируем результат поиска
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "MOVE_EVENT: Поиск события на новом месте:\n" . 
                "  - newDateFrom: $newDateFrom\n" . 
                "  - employeeId: " . ($employeeId ?? 'null') . "\n" . 
                "  - branchId: " . ($branchId ?? 'null') . "\n" . 
                "  - SQL: $sql\n" . 
                "  - Найдено событие: " . ($targetEvent ? 'да (ID=' . $targetEvent['ID'] . ', DATE_FROM=' . $targetEvent['DATE_FROM'] . ', DATE_TO=' . $targetEvent['DATE_TO'] . ')' : 'нет') . "\n", 
                FILE_APPEND | LOCK_EX);

            // Сохраняем старые значения для логирования
            $oldMovingDateFrom = $this->convertRussianDateToStandard($movingEvent['DATE_FROM']);
            $oldMovingDateTo = $this->convertRussianDateToStandard($movingEvent['DATE_TO']);
            $oldMovingEmployeeId = !empty($movingEvent['EMPLOYEE_ID']) ? (int)$movingEvent['EMPLOYEE_ID'] : null;
            $oldMovingBranchId = (int)$movingEvent['BRANCH_ID'];
            
            // Нормализуем новые значения
            $newMovingEmployeeId = $employeeId ? (int)$employeeId : null;
            $newMovingBranchId = $branchId ? (int)$branchId : $oldMovingBranchId;
            
            // Начинаем транзакцию (Bitrix method)
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "MOVE_EVENT: Начинаем транзакцию\n", 
                FILE_APPEND | LOCK_EX);
                
            $this->connection->query("START TRANSACTION");

            if ($targetEvent) {
                // Если на новом месте есть событие - обмениваемся местами
                
                // Сохраняем старые значения целевого события для логирования
                $oldTargetDateFrom = $targetEvent['DATE_FROM'];
                $oldTargetDateTo = $targetEvent['DATE_TO'];
                $oldTargetEmployeeId = !empty($targetEvent['EMPLOYEE_ID']) ? (int)$targetEvent['EMPLOYEE_ID'] : null;
                $oldTargetBranchId = (int)$targetEvent['BRANCH_ID'];
                
                // Вычисляем длительность целевого события (в секундах)
                $targetDateFromObj = new \DateTime($oldTargetDateFrom);
                $targetDateToObj = new \DateTime($oldTargetDateTo);
                $targetDuration = $targetDateToObj->getTimestamp() - $targetDateFromObj->getTimestamp();
                
                // 1. Обновляем событие на новом месте (становится переносимым)
                // Оно получает старое время начала от переносимого события, но сохраняет свою длительность
                $oldDateFrom = $oldMovingDateFrom;
                $oldDateFromObj = new \DateTime($oldDateFrom);
                $oldDateToObj = clone $oldDateFromObj;
                $oldDateToObj->add(new \DateInterval('PT' . $targetDuration . 'S'));
                $oldDateTo = $oldDateToObj->format('Y-m-d H:i:s');
                $oldEmployeeId = $oldMovingEmployeeId;
                $oldBranchId = $oldMovingBranchId;
                
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

                // 2. Обновляем переносимое событие (получает новое время начала, но сохраняет свою длительность)
                // Вычисляем длительность переносимого события (в секундах)
                $movingDateFromObj = new \DateTime($oldMovingDateFrom);
                $movingDateToObj = new \DateTime($oldMovingDateTo);
                $movingDuration = $movingDateToObj->getTimestamp() - $movingDateFromObj->getTimestamp();
                
                // Применяем длительность к новому времени начала
                $newDateFromObj = new \DateTime($newDateFrom);
                $newDateToObj = clone $newDateFromObj;
                $newDateToObj->add(new \DateInterval('PT' . $movingDuration . 'S'));
                $calculatedNewDateTo = $newDateToObj->format('Y-m-d H:i:s');
                
                $movingSet = [];
                $movingSet[] = "DATE_FROM = '" . $this->connection->getSqlHelper()->forSql($newDateFrom) . "'";
                $movingSet[] = "DATE_TO = '" . $this->connection->getSqlHelper()->forSql($calculatedNewDateTo) . "'";
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
                // Логирование для этого случая будет добавлено после COMMIT
                $movingSet = [];
                $movingSet[] = "DATE_FROM = '" . $this->connection->getSqlHelper()->forSql($newDateFrom) . "'";
                $movingSet[] = "DATE_TO = '" . $this->connection->getSqlHelper()->forSql($newDateTo) . "'";
                $movingSet[] = "TIME_IS_CHANGED = 1";
                if ($employeeId) {
                    $movingSet[] = "EMPLOYEE_ID = " . (int)$employeeId;
                } else {
                    // Если employeeId не передан, но был в событии, он может быть NULL
                    $movingSet[] = "EMPLOYEE_ID = " . ($oldMovingEmployeeId ? (int)$oldMovingEmployeeId : 'NULL');
                }
                if ($branchId) {
                    $movingSet[] = "BRANCH_ID = " . (int)$branchId;
                }
                $movingSet[] = "UPDATED_AT = NOW()";

                $updateMovingSql = "UPDATE artmax_calendar_events SET " . implode(', ', $movingSet) . " WHERE ID = " . (int)$eventId;
                $this->connection->query($updateMovingSql);
            }

            // Обновляем бронирование для перенесенных событий
            $this->updateBookingAfterMove($eventId, $newDateFrom, $newDateTo, $employeeId, $branchId);
            
            // Если был обмен местами, обновляем бронирование для события на старом месте
            if ($targetEvent) {
                $this->updateBookingAfterMove($targetEvent['ID'], $oldDateFrom, $oldDateTo, $oldEmployeeId, $oldBranchId);
            }

            // Подтверждаем транзакцию
            $this->connection->query("COMMIT");
            
            // Записываем в журнал изменения для переносимого события
            if ($userId) {
                $journal = new \Artmax\Calendar\Journal();
                
                // Логируем изменения переносимого события
                // DATE_FROM
                if ($oldMovingDateFrom != $newDateFrom) {
                    $journal->writeEvent(
                        $eventId,
                        'EVENT_MOVED_DATE_FROM',
                        'Artmax\Calendar\Calendar::moveEvent',
                        $userId,
                        'DATE_FROM=' . $movingEvent['DATE_FROM'] . '->' . $newDateFrom
                    );
                }
                
                // DATE_TO
                if ($oldMovingDateTo != $newDateTo) {
                    $journal->writeEvent(
                        $eventId,
                        'EVENT_MOVED_DATE_TO',
                        'Artmax\Calendar\Calendar::moveEvent',
                        $userId,
                        'DATE_TO=' . $movingEvent['DATE_TO'] . '->' . $newDateTo
                    );
                }
                
                // EMPLOYEE_ID - определяем финальное значение в зависимости от наличия обмена местами
                $finalMovingEmployeeId = $targetEvent ? $newMovingEmployeeId : ($employeeId ? (int)$employeeId : $oldMovingEmployeeId);
                if ($oldMovingEmployeeId != $finalMovingEmployeeId) {
                    $journal->writeEvent(
                        $eventId,
                        'EVENT_MOVED_EMPLOYEE',
                        'Artmax\Calendar\Calendar::moveEvent',
                        $userId,
                        'EMPLOYEE_ID=' . ($oldMovingEmployeeId ?? 'null') . '->' . ($finalMovingEmployeeId ?? 'null')
                    );
                }
                
                // BRANCH_ID - определяем финальное значение в зависимости от наличия обмена местами
                $finalMovingBranchId = $targetEvent ? $newMovingBranchId : ($branchId ? (int)$branchId : $oldMovingBranchId);
                if ($oldMovingBranchId != $finalMovingBranchId) {
                    $journal->writeEvent(
                        $eventId,
                        'EVENT_MOVED_BRANCH',
                        'Artmax\Calendar\Calendar::moveEvent',
                        $userId,
                        'BRANCH_ID=' . $oldMovingBranchId . '->' . $finalMovingBranchId
                    );
                }
                
                // Если был обмен местами, логируем изменения целевого события
                if ($targetEvent) {
                    // Даты из SQL запроса в формате Y-m-d H:i:s, нормализуем для сравнения
                    $oldTargetDateFromNormalized = $oldTargetDateFrom;
                    $oldTargetDateToNormalized = $oldTargetDateTo;
                    
                    // DATE_FROM целевого события
                    if ($oldTargetDateFromNormalized != $oldDateFrom) {
                        $journal->writeEvent(
                            $targetEvent['ID'],
                            'EVENT_MOVED_DATE_FROM',
                            'Artmax\Calendar\Calendar::moveEvent',
                            $userId,
                            'DATE_FROM=' . $oldTargetDateFrom . '->' . $oldDateFrom
                        );
                    }
                    
                    // DATE_TO целевого события
                    if ($oldTargetDateToNormalized != $oldDateTo) {
                        $journal->writeEvent(
                            $targetEvent['ID'],
                            'EVENT_MOVED_DATE_TO',
                            'Artmax\Calendar\Calendar::moveEvent',
                            $userId,
                            'DATE_TO=' . $oldTargetDateTo . '->' . $oldDateTo
                        );
                    }
                    
                    // EMPLOYEE_ID целевого события
                    if ($oldTargetEmployeeId != $oldEmployeeId) {
                        $journal->writeEvent(
                            $targetEvent['ID'],
                            'EVENT_MOVED_EMPLOYEE',
                            'Artmax\Calendar\Calendar::moveEvent',
                            $userId,
                            'EMPLOYEE_ID=' . ($oldTargetEmployeeId ?? 'null') . '->' . ($oldEmployeeId ?? 'null')
                        );
                    }
                    
                    // BRANCH_ID целевого события
                    if ($oldTargetBranchId != $oldBranchId) {
                        $journal->writeEvent(
                            $targetEvent['ID'],
                            'EVENT_MOVED_BRANCH',
                            'Artmax\Calendar\Calendar::moveEvent',
                            $userId,
                            'BRANCH_ID=' . $oldTargetBranchId . '->' . $oldBranchId
                        );
                    }
                }
            }
            
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "MOVE_EVENT: Транзакция успешно завершена\n", 
                FILE_APPEND | LOCK_EX);
            
            // Возвращаем информацию о затронутых событиях
            $result = [
                'success' => true,
                'movedEventId' => (int)$eventId,
                'affectedEvents' => [(int)$eventId]
            ];
            
            // Если был обмен местами, добавляем ID целевого события
            if ($targetEvent) {
                $result['targetEventId'] = (int)$targetEvent['ID'];
                $result['affectedEvents'][] = (int)$targetEvent['ID'];
                
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "MOVE_EVENT: Обмен местами - затронуты события: " . implode(', ', $result['affectedEvents']) . "\n", 
                    FILE_APPEND | LOCK_EX);
            } else {
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "MOVE_EVENT: Простой перенос - затронуто событие: " . $eventId . "\n", 
                    FILE_APPEND | LOCK_EX);
            }
                
            return $result;

        } catch (\Exception $e) {
            // Откатываем транзакцию в случае ошибки
            $this->connection->query("ROLLBACK");
            
            $errorMessage = 'Ошибка переноса события: ' . $e->getMessage();
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "MOVE_EVENT ERROR: $errorMessage\n", 
                FILE_APPEND | LOCK_EX);
                
            error_log($errorMessage);
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
    
    /**
     * Создать активность (бронирование) в CRM сделке
     * @param int $dealId ID сделки
     * @param string $title Название события
     * @param string $dateFrom Дата и время начала
     * @param string $dateTo Дата и время окончания
     * @param int $responsibleId ID ответственного
     * @return int|false ID созданной активности или false
     */
    public function createCrmActivity($dealId, $title, $dateFrom, $dateTo, $responsibleId)
    {
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "createCrmActivity: Начало. DealID=$dealId, Title=$title, DateFrom=$dateFrom, DateTo=$dateTo, ResponsibleID=$responsibleId\n", 
            FILE_APPEND | LOCK_EX);
            
        if (!\CModule::IncludeModule('crm')) {
            error_log('CRM модуль не подключен');
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "createCrmActivity ERROR: CRM модуль не подключен\n", 
                FILE_APPEND | LOCK_EX);
            return false;
        }
        
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "createCrmActivity: CRM модуль подключен успешно\n", 
            FILE_APPEND | LOCK_EX);
        
        try {
            // Конвертируем даты в формат MySQL datetime (Y-m-d H:i:s)
            $startTimestamp = strtotime($dateFrom);
            $endTimestamp = strtotime($dateTo);
            
            // Важно: формат должен быть MySQL datetime для корректного отображения в CRM
            $startTime = date('d.m.Y H:i:s', $startTimestamp);
            $endTime = date('d.m.Y H:i:s', $endTimestamp);
            
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "createCrmActivity: Конвертированные даты - StartTime=$startTime, EndTime=$endTime\n", 
                FILE_APPEND | LOCK_EX);
            
            // Проверяем, существует ли класс CCrmActivity
            if (!class_exists('\CCrmActivity')) {
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "createCrmActivity ERROR: Класс CCrmActivity не найден\n", 
                    FILE_APPEND | LOCK_EX);
                return false;
            }
            
            // Создаем активность типа "Встреча" (TYPE_ID = 1)
            $activity = new \CCrmActivity(false);
            
            $fields = [
                'OWNER_TYPE_ID' => 2, // 2 = Deal (Сделка)
                'OWNER_ID' => $dealId,
                'TYPE_ID' => 2, // 2 = Meeting (Встреча)
                'SUBJECT' => $title,
                'DESCRIPTION' => 'Запись из календаря клиники',
                'DESCRIPTION_TYPE' => 3, // 3 = bbCode
                'DEADLINE' => $endTime, // Дата завершения
                'START_TIME' => $startTime,
                'END_TIME' => $endTime,
                'COMPLETED' => 'N',
                'RESPONSIBLE_ID' => $responsibleId,
                'PRIORITY' => 2, // Средний приоритет
            ];
            
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "createCrmActivity: Поля для создания активности: " . print_r($fields, true) . "\n", 
                FILE_APPEND | LOCK_EX);
            
            $activityId = $activity->Add($fields);
            
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "createCrmActivity: activity->Add() вернул: " . var_export($activityId, true) . "\n", 
                FILE_APPEND | LOCK_EX);
            
            if ($activityId) {
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "CRM_ACTIVITY: Создана активность ID=$activityId для сделки ID=$dealId\n", 
                    FILE_APPEND | LOCK_EX);
                return $activityId;
            } else {
                $error = $activity->LAST_ERROR;
                
                // Получаем глобальную переменную APPLICATION с ошибками
                global $APPLICATION;
                $appError = $APPLICATION->GetException();
                
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "CRM_ACTIVITY ERROR: LAST_ERROR='" . $error . "', APP_ERROR='" . ($appError ? $appError->GetString() : 'нет') . "'\n", 
                    FILE_APPEND | LOCK_EX);
                    
                error_log('Ошибка создания CRM активности: ' . $error);
                return false;
            }
            
        } catch (\Exception $e) {
            error_log('Ошибка создания CRM активности: ' . $e->getMessage());
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "createCrmActivity EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", 
                FILE_APPEND | LOCK_EX);
            return false;
        }
    }
    
    /**
     * Обновить активность в CRM
     * @param int $activityId ID активности
     * @param string $title Название
     * @param string $dateFrom Дата начала
     * @param string $dateTo Дата окончания
     * @return bool
     */
    public function updateCrmActivity($activityId, $title, $dateFrom, $dateTo)
    {
        if (!\CModule::IncludeModule('crm')) {
            error_log('CRM модуль не подключен');
            return false;
        }
        
        try {
            // Конвертируем даты в формат MySQL (Y-m-d H:i:s)
            $startTime = date('Y-m-d H:i:s', strtotime($dateFrom));
            $endTime = date('Y-m-d H:i:s', strtotime($dateTo));
            
            $activity = new \CCrmActivity(false);
            
            $fields = [
                'SUBJECT' => $title,
                'START_TIME' => $startTime,
                'END_TIME' => $endTime,
            ];
            
            $result = $activity->Update($activityId, $fields);
            
            if ($result) {
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "CRM_ACTIVITY: Обновлена активность ID=$activityId\n", 
                    FILE_APPEND | LOCK_EX);
                return true;
            } else {
                $error = $activity->LAST_ERROR;
                error_log('Ошибка обновления CRM активности: ' . $error);
                return false;
            }
            
        } catch (\Exception $e) {
            error_log('Ошибка обновления CRM активности: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Удалить активность из CRM
     * @param int $activityId ID активности
     * @return bool
     */
    public function deleteCrmActivity($activityId)
    {
        if (!\CModule::IncludeModule('crm')) {
            return false;
        }
        
        try {
            $activity = new \CCrmActivity(false);
            $result = $activity->Delete($activityId);
            
            if ($result) {
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "CRM_ACTIVITY: Удалена активность ID=$activityId\n", 
                    FILE_APPEND | LOCK_EX);
            }
            
            return $result;
        } catch (\Exception $e) {
            error_log('Ошибка удаления CRM активности: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Сохранить ID активности CRM к событию
     * @param int $eventId ID события
     * @param int $activityId ID активности CRM
     * @return bool
     */
    public function saveEventActivityId($eventId, $activityId)
    {
        $sql = "UPDATE artmax_calendar_events SET ACTIVITY_ID = " . (int)$activityId . " WHERE ID = " . (int)$eventId;
        
        try {
            $this->connection->query($sql);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "Сохранен ACTIVITY_ID=$activityId для события ID=$eventId\n", 
                FILE_APPEND | LOCK_EX);
            return true;
        } catch (\Exception $e) {
            error_log('Ошибка сохранения ACTIVITY_ID: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Обновляет бронирование в CRM после переноса события
     */
    private function updateBookingAfterMove($eventId, $newDateFrom, $newDateTo, $employeeId = null, $branchId = null)
    {
        try {
            // Получаем актуальные данные события
            $event = $this->getEvent($eventId);
            if (!$event) {
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "UPDATE_BOOKING_AFTER_MOVE: Событие не найдено ID=$eventId\n", 
                    FILE_APPEND | LOCK_EX);
                return false;
            }

            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "UPDATE_BOOKING_AFTER_MOVE: Начинаем обновление для события ID=$eventId\n" .
                "  - newDateFrom: $newDateFrom\n" .
                "  - newDateTo: $newDateTo\n" .
                "  - DEAL_ENTITY_ID: {$event['DEAL_ENTITY_ID']}\n" .
                "  - ACTIVITY_ID: {$event['ACTIVITY_ID']}\n", 
                FILE_APPEND | LOCK_EX);

            // Обновляем CRM активность если есть
            if (!empty($event['ACTIVITY_ID'])) {
                $activityUpdated = $this->updateCrmActivity(
                    $event['ACTIVITY_ID'],
                    $event['TITLE'],
                    $newDateFrom,
                    $newDateTo
                );
                
                if ($activityUpdated) {
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                        "UPDATE_BOOKING_AFTER_MOVE: Обновлена активность CRM ID={$event['ACTIVITY_ID']}\n", 
                        FILE_APPEND | LOCK_EX);
                }
            }

            // Обновляем бронирование в сделке если есть
            if (!empty($event['DEAL_ENTITY_ID']) && \CModule::IncludeModule('crm')) {
                
                $responsibleId = $employeeId ?: $event['EMPLOYEE_ID'] ?: \CCrmSecurityHelper::GetCurrentUserID();
                $finalBranchId = $branchId ?: $event['BRANCH_ID'];
                
                // Получаем часовой пояс филиала
                $branchTimezone = $this->getBranchTimezone($finalBranchId);
                
                // Преобразуем формат даты из Y-m-d H:i:s в d.m.Y H:i:s для бронирования
                $dateFromObj = \DateTime::createFromFormat('Y-m-d H:i:s', $newDateFrom, new \DateTimeZone($branchTimezone));
                $dateToObj = \DateTime::createFromFormat('Y-m-d H:i:s', $newDateTo, new \DateTimeZone($branchTimezone));
                
                if (!$dateFromObj || !$dateToObj) {
                    // Пробуем другой формат
                    $dateFromObj = \DateTime::createFromFormat('d.m.Y H:i:s', $newDateFrom, new \DateTimeZone($branchTimezone));
                    $dateToObj = \DateTime::createFromFormat('d.m.Y H:i:s', $newDateTo, new \DateTimeZone($branchTimezone));
                }
                
                if ($dateFromObj && $dateToObj) {
                    // Используем исходное время как есть
                    $bookingDateTime = $dateFromObj->format('d.m.Y H:i:s');
                    
                    // Вычисляем длительность
                    $durationSeconds = $dateToObj->getTimestamp() - $dateFromObj->getTimestamp();
                    $bookingValue = "user|{$responsibleId}|{$bookingDateTime}|{$durationSeconds}|{$event['TITLE']}";
                    
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                        "UPDATE_BOOKING_AFTER_MOVE: Вычисление бронирования:\n" .
                        "  - newDateFrom: $newDateFrom\n" .
                        "  - newDateTo: $newDateTo\n" .
                        "  - branchTimezone: $branchTimezone\n" .
                        "  - bookingDateTime: $bookingDateTime\n" .
                        "  - durationSeconds: $durationSeconds\n" .
                        "  - responsibleId: $responsibleId\n" .
                        "  - ФИНАЛЬНАЯ СТРОКА: $bookingValue\n", 
                        FILE_APPEND | LOCK_EX);
                    
                    $bookingFieldCode = \Bitrix\Main\Config\Option::get('artmax.calendar', 'deal_booking_field', 'UF_CRM_CALENDAR_BOOKING');
                    
                    $deal = new \CCrmDeal(false);
                    $updateFields = [
                        $bookingFieldCode => [$bookingValue]
                    ];
                    $deal->Update($event['DEAL_ENTITY_ID'], $updateFields);
                    
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                        "UPDATE_BOOKING_AFTER_MOVE: Обновлено бронирование в сделке ID={$event['DEAL_ENTITY_ID']}\n", 
                        FILE_APPEND | LOCK_EX);
                }
            }

            return true;

        } catch (\Exception $e) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "UPDATE_BOOKING_AFTER_MOVE ERROR: " . $e->getMessage() . "\n", 
                FILE_APPEND | LOCK_EX);
            return false;
        }
    }
}
