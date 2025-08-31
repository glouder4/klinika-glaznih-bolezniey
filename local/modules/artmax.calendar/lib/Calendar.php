<?php
namespace Artmax\Calendar;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Connection;
use Bitrix\Main\Type\DateTime;

class Calendar
{
    private $connection;

    public function __construct()
    {
        $this->connection = Application::getConnection();
    }

    /**
     * Добавить новое событие
     */
    public function addEvent($title, $description, $dateFrom, $dateTo, $userId, $branchId = 1, $eventColor = '#3498db')
    {
        $sql = "INSERT INTO artmax_calendar_events (TITLE, DESCRIPTION, DATE_FROM, DATE_TO, USER_ID, BRANCH_ID, EVENT_COLOR) VALUES ('" . 
               $this->connection->getSqlHelper()->forSql($title) . "', '" . 
               $this->connection->getSqlHelper()->forSql($description) . "', '" . 
               $this->connection->getSqlHelper()->forSql($dateFrom) . "', '" . 
               $this->connection->getSqlHelper()->forSql($dateTo) . "', " . 
               (int)$userId . ", " . 
               (int)$branchId . ", '" . 
               $this->connection->getSqlHelper()->forSql($eventColor) . "')";

        $result = $this->connection->query($sql);

        if ($result) {
            return $this->connection->getInsertedId();
        }

        return false;
    } 

    /**
     * Получить события за период
     */
    public function getEvents($dateFrom, $dateTo, $userId = null)
    {
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
     * Получить событие по ID
     */
    public function getEvent($id)
    {
        $sql = "SELECT * FROM artmax_calendar_events WHERE ID = " . (int)$id;
        $result = $this->connection->query($sql);

        return $result->fetch();
    }

    /**
     * Обновить событие
     */
    public function updateEvent($id, $title, $description, $dateFrom, $dateTo)
    {
        $sql = "
            UPDATE artmax_calendar_events 
            SET TITLE = '" . $this->connection->getSqlHelper()->forSql($title) . "', 
                DESCRIPTION = '" . $this->connection->getSqlHelper()->forSql($description) . "', 
                DATE_FROM = '" . $this->connection->getSqlHelper()->forSql($dateFrom) . "', 
                DATE_TO = '" . $this->connection->getSqlHelper()->forSql($dateTo) . "', 
                UPDATED_AT = NOW()
            WHERE ID = " . (int)$id;

        return $this->connection->query($sql);
    }

    /**
     * Удалить событие
     */
    public function deleteEvent($id)
    {
        $sql = "DELETE FROM artmax_calendar_events WHERE ID = " . (int)$id;
        return $this->connection->query($sql);
    }

    /**
     * Получить события пользователя
     */
    public function getUserEvents($userId, $limit = 10)
    {
        $sql = "
            SELECT * FROM artmax_calendar_events 
            WHERE USER_ID = " . (int)$userId . " 
            ORDER BY DATE_FROM DESC 
            LIMIT " . (int)$limit;

        $result = $this->connection->query($sql);

        return $result->fetchAll();
    }  

        /**
     * Проверить доступность времени
     */
    public function isTimeAvailable($dateFrom, $dateTo, $userId, $excludeId = null)
    {
        // Преобразуем формат даты из ISO в MySQL формат
        $dateFrom = str_replace('T', ' ', substr($dateFrom, 0, 19));
        $dateTo = str_replace('T', ' ', substr($dateTo, 0, 19));
        
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
        // Проверяем, что branchId не null и является числом
        if (!$branchId || !is_numeric($branchId)) {
            return [];
        }
        
        $sql = "SELECT * FROM artmax_calendar_events WHERE BRANCH_ID = " . (int)$branchId;
        
        if ($dateFrom) {
            $sql .= " AND DATE_FROM >= '" . $this->connection->getSqlHelper()->forSql($dateFrom) . "'";
        }
        if ($dateTo) {
            $sql .= " AND DATE_TO <= '" . $this->connection->getSqlHelper()->forSql($dateTo) . "'";
        }
        if ($userId) {
            $sql .= " AND USER_ID = " . (int)$userId;
        }
        
        $sql .= " ORDER BY DATE_FROM ASC";
        
        // Добавляем LIMIT, если он передан
        if ($limit !== null && $limit > 0) {
            $sql .= " LIMIT " . (int)$limit;
        }
        
        $result = $this->connection->query($sql);
        return $result->fetchAll();
    }

    /**
     * Получить все события
     */
    public function getAllEvents($limit = 100)
    {
        $sql = "
            SELECT e.*, b.NAME as BRANCH_NAME 
            FROM artmax_calendar_events e
            LEFT JOIN artmax_calendar_branches b ON e.BRANCH_ID = b.ID
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
}
