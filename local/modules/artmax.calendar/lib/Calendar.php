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
    public function addEvent($title, $description, $dateFrom, $dateTo, $userId, $branchId = 1)
    {
        $sql = "
            INSERT INTO artmax_calendar_events 
            (TITLE, DESCRIPTION, DATE_FROM, DATE_TO, USER_ID, BRANCH_ID, CREATED_AT) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ";

        $result = $this->connection->query($sql, [$title, $description, $dateFrom, $dateTo, $userId, $branchId]);

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
            WHERE DATE_FROM >= ? AND DATE_TO <= ?
        ";

        $params = [$dateFrom, $dateTo];

        if ($userId) {
            $sql .= " AND USER_ID = ?";
            $params[] = $userId;
        }

        $sql .= " ORDER BY DATE_FROM ASC";

        $result = $this->connection->query($sql, $params);

        return $result->fetchAll();
    }

    /**
     * Получить событие по ID
     */
    public function getEvent($id)
    {
        $sql = "SELECT * FROM artmax_calendar_events WHERE ID = ?";
        $result = $this->connection->query($sql, [$id]);

        return $result->fetch();
    }

    /**
     * Обновить событие
     */
    public function updateEvent($id, $title, $description, $dateFrom, $dateTo)
    {
        $sql = "
            UPDATE artmax_calendar_events 
            SET TITLE = ?, DESCRIPTION = ?, DATE_FROM = ?, DATE_TO = ?, UPDATED_AT = NOW()
            WHERE ID = ?
        ";

        return $this->connection->query($sql, [$title, $description, $dateFrom, $dateTo, $id]);
    }

    /**
     * Удалить событие
     */
    public function deleteEvent($id)
    {
        $sql = "DELETE FROM artmax_calendar_events WHERE ID = ?";
        return $this->connection->query($sql, [$id]);
    }

    /**
     * Получить события пользователя
     */
    public function getUserEvents($userId, $limit = 10)
    {
        $sql = "
            SELECT * FROM artmax_calendar_events 
            WHERE USER_ID = ? 
            ORDER BY DATE_FROM DESC 
            LIMIT ?
        ";

        $result = $this->connection->query($sql, [$userId, $limit]);

        return $result->fetchAll();
    }

    /**
     * Проверить доступность времени
     */
    public function isTimeAvailable($dateFrom, $dateTo, $userId, $excludeId = null)
    {
        $sql = "
            SELECT COUNT(*) as count 
            FROM artmax_calendar_events 
            WHERE USER_ID = ? 
            AND (
                (DATE_FROM <= ? AND DATE_TO >= ?) OR
                (DATE_FROM <= ? AND DATE_TO >= ?) OR
                (DATE_FROM >= ? AND DATE_TO <= ?)
            )
        ";

        $params = [$userId, $dateFrom, $dateFrom, $dateTo, $dateTo, $dateFrom, $dateTo];

        if ($excludeId) {
            $sql .= " AND ID != ?";
            $params[] = $excludeId;
        }

        $result = $this->connection->query($sql, $params);
        $row = $result->fetch();

        return $row['count'] == 0;
    }

    /**
     * Получить события по филиалу
     */
    public function getEventsByBranch($branchId, $dateFrom = null, $dateTo = null, $userId = null)
    {
        $sql = "SELECT * FROM artmax_calendar_events WHERE BRANCH_ID = ?";
        $params = [$branchId];
        if ($dateFrom) {
            $sql .= " AND DATE_FROM >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $sql .= " AND DATE_TO <= ?";
            $params[] = $dateTo;
        }
        if ($userId) {
            $sql .= " AND USER_ID = ?";
            $params[] = $userId;
        }
        $sql .= " ORDER BY DATE_FROM ASC";
        $result = $this->connection->query($sql, $params);
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
            LIMIT ?
        ";

        $result = $this->connection->query($sql, [$limit]);
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