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
     * @return int|false ID вставленной записи или false при ошибке
     */
    public function writeEvent($eventId, $action, $initiator = null, $userId = null)
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
        
        // ACTION_DATE - всегда используем CURRENT_TIMESTAMP (текущая дата/время в момент вызова метода)

        // Формируем SQL запрос INSERT
        $sql = "INSERT INTO artmax_calendar_event_journal 
                (EVENT_ID, ACTION, ACTION_DATE, INITIATOR, USER_ID) 
                VALUES 
                ({$eventId}, '{$action}', CURRENT_TIMESTAMP, {$initiatorSql}, {$userIdSql})";

        // Выполняем запрос
        $result = $this->connection->query($sql);

        if ($result) {
            // Возвращаем ID вставленной записи
            return $this->connection->getInsertedId();
        }

        return false;
    }
}

