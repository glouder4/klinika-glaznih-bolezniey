<?php
namespace Artmax\Calendar;

use Bitrix\Main\Application;
use DateTime;
use DateTimeZone;
use Exception;

/**
 * Менеджер часовых поясов для филиалов
 */
class TimezoneManager
{
    private $connection;
    private $timezoneCache = [];

    public function __construct()
    {
        $this->connection = Application::getConnection();
    }

    /**
     * Получить настройки часового пояса для филиала
     */
    public function getBranchTimezone($branchId)
    {
        if (isset($this->timezoneCache[$branchId])) {
            return $this->timezoneCache[$branchId];
        }

        $sql = "
            SELECT ID, NAME as BRANCH_NAME, TIMEZONE_NAME
            FROM artmax_calendar_branches
            WHERE ID = " . (int)$branchId . "
        ";

        $result = $this->connection->query($sql);
        $timezone = $result->fetch();

        if ($timezone) {
            $this->timezoneCache[$branchId] = $timezone;
        }

        return $timezone;
    }

    /**
     * Получить все часовые пояса филиалов
     */
    public function getAllTimezones()
    {
        $sql = "
            SELECT ID, NAME as BRANCH_NAME, TIMEZONE_NAME
            FROM artmax_calendar_branches
            ORDER BY NAME
        ";

        $result = $this->connection->query($sql);
        return $result->fetchAll();
    }

    /**
     * Конвертировать время из локального времени филиала в UTC
     */
    public function convertToUTC($localDateTime, $branchId)
    {
        $timezone = $this->getBranchTimezone($branchId);
        if (!$timezone) {
            throw new Exception("Часовой пояс для филиала {$branchId} не найден");
        }

        try {
            // Создаем объект DateTime в локальном времени филиала
            $localDate = new DateTime($localDateTime, new DateTimeZone($timezone['TIMEZONE_NAME']));
            
            // Конвертируем в UTC
            $localDate->setTimezone(new DateTimeZone('UTC'));
            
            return $localDate->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            throw new Exception("Ошибка конвертации времени в UTC: " . $e->getMessage());
        }
    }

    /**
     * Конвертировать время из UTC в локальное время филиала
     */
    public function convertFromUTC($utcDateTime, $branchId)
    {
        $timezone = $this->getBranchTimezone($branchId);
        if (!$timezone) {
            throw new Exception("Часовой пояс для филиала {$branchId} не найден");
        }

        try {
            // Создаем объект DateTime в UTC
            $utcDate = new DateTime($utcDateTime, new DateTimeZone('UTC'));
            
            // Конвертируем в локальное время филиала
            $utcDate->setTimezone(new DateTimeZone($timezone['TIMEZONE_NAME']));
            
            return $utcDate->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            throw new Exception("Ошибка конвертации времени из UTC: " . $e->getMessage());
        }
    }

    /**
     * Получить текущее смещение часового пояса для филиала
     */
    public function getCurrentOffset($branchId)
    {
        $timezone = $this->getBranchTimezone($branchId);
        if (!$timezone) {
            return 0;
        }

        try {
            $dateTimeZone = new DateTimeZone($timezone['TIMEZONE_NAME']);
            $dateTime = new DateTime('now', $dateTimeZone);
            return $dateTime->getOffset() / 3600; // Возвращаем в часах
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Получить список всех доступных часовых поясов
     */
    public function getAvailableTimezones()
    {
        return [
            'Europe/Moscow' => 'Москва (UTC+3)',
            'Europe/London' => 'Лондон (UTC+0)',
            'Europe/Paris' => 'Париж (UTC+1)',
            'Europe/Berlin' => 'Берлин (UTC+1)',
            'America/New_York' => 'Нью-Йорк (UTC-5)',
            'America/Los_Angeles' => 'Лос-Анджелес (UTC-8)',
            'Asia/Tokyo' => 'Токио (UTC+9)',
            'Asia/Shanghai' => 'Шанхай (UTC+8)',
            'Asia/Yekaterinburg' => 'Екатеринбург (UTC+5)',
            'Asia/Novosibirsk' => 'Новосибирск (UTC+7)',
            'Asia/Vladivostok' => 'Владивосток (UTC+10)',
            'Asia/Krasnoyarsk' => 'Красноярск (UTC+7)',
            'Asia/Omsk' => 'Омск (UTC+6)',
            'Asia/Irkutsk' => 'Иркутск (UTC+8)',
            'Asia/Magadan' => 'Магадан (UTC+11)',
            'Asia/Kamchatka' => 'Камчатка (UTC+12)'
        ];
    }

    /**
     * Установить часовой пояс для филиала
     */
    public function setBranchTimezone($branchId, $timezoneName)
    {
        $sql = "
            UPDATE artmax_calendar_branches 
            SET TIMEZONE_NAME = '" . $this->connection->getSqlHelper()->forSql($timezoneName) . "',
                UPDATED_AT = CURRENT_TIMESTAMP
            WHERE ID = " . (int)$branchId . "
        ";

        $result = $this->connection->query($sql);
        
        if ($result) {
            // Очищаем кэш для этого филиала
            unset($this->timezoneCache[$branchId]);
            return true;
        }

        return false;
    }

    /**
     * Получить форматированное время для отображения в календаре
     */
    public function formatTimeForDisplay($utcDateTime, $branchId, $format = 'H:i')
    {
        try {
            $localDateTime = $this->convertFromUTC($utcDateTime, $branchId);
            $dateTime = new DateTime($localDateTime);
            return $dateTime->format($format);
        } catch (Exception $e) {
            // Fallback на исходное время
            $dateTime = new DateTime($utcDateTime);
            return $dateTime->format($format);
        }
    }

    /**
     * Получить форматированную дату для отображения в календаре
     */
    public function formatDateForDisplay($utcDateTime, $branchId, $format = 'Y-m-d')
    {
        try {
            $localDateTime = $this->convertFromUTC($utcDateTime, $branchId);
            $dateTime = new DateTime($localDateTime);
            return $dateTime->format($format);
        } catch (Exception $e) {
            // Fallback на исходную дату
            $dateTime = new DateTime($utcDateTime);
            return $dateTime->format($format);
        }
    }
}
