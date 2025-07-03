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
    public function addEvent($title, $description, $dateFrom, $dateTo, $userId)
    {
        $sql = "
            INSERT INTO artmax_calendar_events 
            (TITLE, DESCRIPTION, DATE_FROM, DATE_TO, USER_ID) 
            VALUES (?, ?, ?, ?, ?)
        ";

        $stmt = $this->connection->prepare($sql);
        $result = $stmt->execute([$title, $description, $dateFrom, $dateTo, $userId]);

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

        $stmt = $this->connection->prepare($sql);
        $result = $stmt->execute($params);

        return $result->fetchAll();
    }

    /**
     * Получить событие по ID
     */
    public function getEvent($id)
    {
        $sql = "SELECT * FROM artmax_calendar_events WHERE ID = ?";
        $stmt = $this->connection->prepare($sql);
        $result = $stmt->execute([$id]);

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

        $stmt = $this->connection->prepare($sql);
        return $stmt->execute([$title, $description, $dateFrom, $dateTo, $id]);
    }

    /**
     * Удалить событие
     */
    public function deleteEvent($id)
    {
        $sql = "DELETE FROM artmax_calendar_events WHERE ID = ?";
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute([$id]);
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

        $stmt = $this->connection->prepare($sql);
        $result = $stmt->execute([$userId, $limit]);

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

        $stmt = $this->connection->prepare($sql);
        $result = $stmt->execute($params);
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
        $stmt = $this->connection->prepare($sql);
        $result = $stmt->execute($params);
        return $result->fetchAll();
    }
} 