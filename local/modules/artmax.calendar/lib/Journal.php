<?php
namespace Artmax\Calendar;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Connection;

/**
 * Класс для работы с журналом событий
 * Класс read-only: только запись в журнал, без возможности удаления или изменения
 */
class Journal
{
    private $connection;

    public function __construct()
    {
        $this->connection = Application::getConnection();
    }

    /**
     * Записать событие в журнал
     * 
     * @param int $eventId ID события
     * @param string $action Действие (created, updated, deleted, moved, etc.)
     * @param string|null $initiator Название класса и функции инициатора (например: "Artmax\Calendar\Calendar::addEvent")
     * @param int|null $userId ID пользователя, выполнившего действие
     * @param string|null $actionValue Значение действия (что записалось, что отвязалось и т.п.)
     * @return int|false ID вставленной записи или false при ошибке
     */
    public function writeEvent($eventId, $action, $initiator = null, $userId = null, $actionValue = null)
    {
        // Валидация обязательных полей
        if (empty($eventId) || !is_numeric($eventId)) {
            return false;
        }
        
        if (empty($action) || !is_string($action)) {
            return false;
        }

        // Подготовка значений для INSERT
        $eventId = (int)$eventId;
        $action = $this->connection->getSqlHelper()->forSql($action);
        
        // INITIATOR - опциональное поле
        $initiatorSql = 'NULL';
        if (!empty($initiator) && is_string($initiator)) {
            $initiatorSql = "'" . $this->connection->getSqlHelper()->forSql($initiator) . "'";
        }
        
        // USER_ID - опциональное поле
        $userIdSql = 'NULL';
        if (!empty($userId) && is_numeric($userId)) {
            $userIdSql = (int)$userId;
        }
        
        // ACTION_VALUE - опциональное поле
        $actionValueSql = 'NULL';
        if (!empty($actionValue)) {
            if (is_array($actionValue) || is_object($actionValue)) {
                $actionValue = json_encode($actionValue, JSON_UNESCAPED_UNICODE);
            }
            $actionValueSql = "'" . $this->connection->getSqlHelper()->forSql((string)$actionValue) . "'";
        }
        
        // ACTION_DATE - всегда используем CURRENT_TIMESTAMP (текущая дата/время в момент вызова метода)

        // Формируем SQL запрос INSERT
        $sql = "INSERT INTO artmax_calendar_event_journal 
                (EVENT_ID, ACTION, ACTION_DATE, ACTION_VALUE, INITIATOR, USER_ID) 
                VALUES 
                ({$eventId}, '{$action}', CURRENT_TIMESTAMP, {$actionValueSql}, {$initiatorSql}, {$userIdSql})";

        // Выполняем запрос
        $result = $this->connection->query($sql);

        if ($result) {
            // Возвращаем ID вставленной записи
            return $this->connection->getInsertedId();
        }

        return false;
    }

    /**
     * Получить записи журнала для события
     * 
     * @param int $eventId ID события
     * @return array Массив записей журнала, отсортированных по ACTION_DATE (от новых к старым)
     */
    public function getEventJournal($eventId)
    {
        if (empty($eventId) || !is_numeric($eventId)) {
            return [];
        }

        $eventId = (int)$eventId;

        $sql = "
            SELECT 
                ID,
                EVENT_ID,
                ACTION,
                DATE_FORMAT(ACTION_DATE, '%d.%m.%Y %H:%i:%s') AS ACTION_DATE_FORMATTED,
                ACTION_DATE,
                ACTION_VALUE,
                INITIATOR,
                USER_ID
            FROM artmax_calendar_event_journal 
            WHERE EVENT_ID = {$eventId}
            ORDER BY ACTION_DATE ASC
        ";

        $result = $this->connection->query($sql);
        
        if (!$result) {
            return [];
        }

        $entries = [];
        while ($row = $result->fetch()) {
            $entries[] = $row;
        }

        return $entries;
    }
}

