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
            SELECT ts.*, b.NAME as BRANCH_NAME 
            FROM artmax_calendar_timezone_settings ts
            JOIN artmax_calendar_branches b ON ts.BRANCH_ID = b.ID
            WHERE ts.BRANCH_ID = " . (int)$branchId . " AND ts.IS_ACTIVE = 1
        ";

        $result = $this->connection->query($sql);
        $timezone = $result->fetch();

        if ($timezone) {
            $this->timezoneCache[$branchId] = $timezone;
        }

        return $timezone;
    }

    /**
     * Получить все активные часовые пояса
     */
    public function getAllTimezones()
    {
        $sql = "
            SELECT ts.*, b.NAME as BRANCH_NAME 
            FROM artmax_calendar_timezone_settings ts
            JOIN artmax_calendar_branches b ON ts.BRANCH_ID = b.ID
            WHERE ts.IS_ACTIVE = 1
            ORDER BY b.NAME
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
     * Получить текущее смещение часового пояса для филиала (с учетом DST)
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
            return $timezone['TIMEZONE_OFFSET'];
        }
    }

    /**
     * Проверить, активно ли летнее время для филиала
     */
    public function isDSTActive($branchId)
    {
        $timezone = $this->getBranchTimezone($branchId);
        if (!$timezone || !$timezone['DST_ENABLED']) {
            return false;
        }

        try {
            $dateTimeZone = new DateTimeZone($timezone['TIMEZONE_NAME']);
            $dateTime = new DateTime('now', $dateTimeZone);
            return $dateTime->format('I') == '1'; // 1 = DST активен, 0 = DST неактивен
        } catch (Exception $e) {
            return false;
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
     * Обновить настройки часового пояса для филиала
     */
    public function updateBranchTimezone($branchId, $timezoneData)
    {
        $sql = "
            INSERT INTO artmax_calendar_timezone_settings 
            (BRANCH_ID, TIMEZONE_NAME, TIMEZONE_OFFSET, DST_ENABLED, DST_START_MONTH, DST_START_DAY, DST_START_HOUR, DST_END_MONTH, DST_END_DAY, DST_END_HOUR, IS_ACTIVE)
            VALUES (
                " . (int)$branchId . ",
                '" . $this->connection->getSqlHelper()->forSql($timezoneData['timezone_name']) . "',
                " . (int)$timezoneData['timezone_offset'] . ",
                " . (int)$timezoneData['dst_enabled'] . ",
                " . (int)$timezoneData['dst_start_month'] . ",
                " . (int)$timezoneData['dst_start_day'] . ",
                " . (int)$timezoneData['dst_start_hour'] . ",
                " . (int)$timezoneData['dst_end_month'] . ",
                " . (int)$timezoneData['dst_end_day'] . ",
                " . (int)$timezoneData['dst_end_hour'] . ",
                1
            )
            ON DUPLICATE KEY UPDATE
                TIMEZONE_NAME = VALUES(TIMEZONE_NAME),
                TIMEZONE_OFFSET = VALUES(TIMEZONE_OFFSET),
                DST_ENABLED = VALUES(DST_ENABLED),
                DST_START_MONTH = VALUES(DST_START_MONTH),
                DST_START_DAY = VALUES(DST_START_DAY),
                DST_START_HOUR = VALUES(DST_START_HOUR),
                DST_END_MONTH = VALUES(DST_END_MONTH),
                DST_END_DAY = VALUES(DST_END_DAY),
                DST_END_HOUR = VALUES(DST_END_HOUR),
                UPDATED_AT = CURRENT_TIMESTAMP
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
