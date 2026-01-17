<?php
namespace Artmax\Calendar;

use Bitrix\Main\Application;

/**
 * Класс для работы с настройками модуля календаря
 */
class ModuleSettings
{
    private $connection;
    
    public function __construct()
    {
        try {
            $this->connection = Application::getConnection();
        } catch (\Exception $e) {
            error_log('ModuleSettings constructor error: ' . $e->getMessage());
            throw new \RuntimeException('Не удалось получить подключение к базе данных: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Получить значение настройки
     * 
     * @param string $key Ключ настройки
     * @param mixed $default Значение по умолчанию
     * @return mixed
     */
    public function get($key, $default = null)
    {
        try {
            $helper = $this->connection->getSqlHelper();
            $sql = "SELECT SETTING_VALUE, SETTING_TYPE FROM artmax_calendar_module_settings WHERE SETTING_KEY = '" . $helper->forSql($key) . "' LIMIT 1";
            $result = $this->connection->query($sql);
            $row = $result->fetch();
            
            if (!$row) {
                return $default;
            }
            
            $value = $row['SETTING_VALUE'];
            $type = $row['SETTING_TYPE'];
            
            // Преобразуем значение в зависимости от типа
            switch ($type) {
                case 'int':
                    return (int)$value;
                case 'bool':
                    return $value === '1' || $value === 'true' || $value === true;
                case 'json':
                    return json_decode($value, true);
                default:
                    return $value;
            }
        } catch (\Exception $e) {
            error_log('Ошибка получения настройки ' . $key . ': ' . $e->getMessage());
            return $default;
        }
    }
    
    /**
     * Установить значение настройки
     * 
     * @param string $key Ключ настройки
     * @param mixed $value Значение настройки
     * @param string $type Тип значения (string, int, bool, json)
     * @param string $description Описание настройки
     * @return bool
     */
    public function set($key, $value, $type = 'string', $description = null)
    {
        try {
            // Определяем тип автоматически, если не указан
            if ($type === 'string') {
                if (is_int($value)) {
                    $type = 'int';
                } elseif (is_bool($value)) {
                    $type = 'bool';
                } elseif (is_array($value) || is_object($value)) {
                    $type = 'json';
                }
            }
            
            // Преобразуем значение в строку для хранения
            $stringValue = $value;
            if ($type === 'bool') {
                $stringValue = $value ? '1' : '0';
            } elseif ($type === 'json') {
                $stringValue = json_encode($value, JSON_UNESCAPED_UNICODE);
            } else {
                $stringValue = (string)$value;
            }
            
            // Используем подготовленные запросы Bitrix
            $helper = $this->connection->getSqlHelper();
            
            // Проверяем, существует ли настройка
            $checkSql = "SELECT ID FROM artmax_calendar_module_settings WHERE SETTING_KEY = '" . $helper->forSql($key) . "' LIMIT 1";
            $checkResult = $this->connection->query($checkSql);
            $exists = $checkResult->fetch();
            
            if ($exists) {
                // Обновляем существующую настройку
                $updateSql = "
                    UPDATE artmax_calendar_module_settings 
                    SET SETTING_VALUE = '" . $helper->forSql($stringValue) . "',
                        SETTING_TYPE = '" . $helper->forSql($type) . "',
                        DESCRIPTION = " . ($description ? "'" . $helper->forSql($description) . "'" : "NULL") . ",
                        UPDATED_AT = CURRENT_TIMESTAMP
                    WHERE SETTING_KEY = '" . $helper->forSql($key) . "'
                ";
                $this->connection->query($updateSql);
            } else {
                // Создаем новую настройку
                $insertSql = "
                    INSERT INTO artmax_calendar_module_settings (SETTING_KEY, SETTING_VALUE, SETTING_TYPE, DESCRIPTION)
                    VALUES (
                        '" . $helper->forSql($key) . "',
                        '" . $helper->forSql($stringValue) . "',
                        '" . $helper->forSql($type) . "',
                        " . ($description ? "'" . $helper->forSql($description) . "'" : "NULL") . "
                    )
                ";
                $this->connection->query($insertSql);
            }
            
            return true;
        } catch (\Exception $e) {
            error_log('Ошибка сохранения настройки ' . $key . ': ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Удалить настройку
     * 
     * @param string $key Ключ настройки
     * @return bool
     */
    public function delete($key)
    {
        try {
            $helper = $this->connection->getSqlHelper();
            $sql = "DELETE FROM artmax_calendar_module_settings WHERE SETTING_KEY = '" . $helper->forSql($key) . "'";
            $this->connection->query($sql);
            return true;
        } catch (\Exception $e) {
            error_log('Ошибка удаления настройки ' . $key . ': ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Получить все настройки
     * 
     * @return array
     */
    public function getAll()
    {
        try {
            $sql = "SELECT SETTING_KEY, SETTING_VALUE, SETTING_TYPE, DESCRIPTION FROM artmax_calendar_module_settings ORDER BY SETTING_KEY";
            $result = $this->connection->query($sql);
            
            $settings = [];
            while ($row = $result->fetch()) {
                $key = $row['SETTING_KEY'];
                $value = $row['SETTING_VALUE'];
                $type = $row['SETTING_TYPE'];
                
                // Преобразуем значение
                switch ($type) {
                    case 'int':
                        $value = (int)$value;
                        break;
                    case 'bool':
                        $value = $value === '1' || $value === 'true';
                        break;
                    case 'json':
                        $value = json_decode($value, true);
                        break;
                }
                
                $settings[$key] = [
                    'value' => $value,
                    'type' => $type,
                    'description' => $row['DESCRIPTION']
                ];
            }
            
            return $settings;
        } catch (\Exception $e) {
            error_log('Ошибка получения всех настроек: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Получить ID группы пользователей для сотрудников филиала
     * 
     * @return int|null
     */
    public function getBranchEmployeesGroupId()
    {
        return $this->get('branch_employees_group_id', null);
    }
    
    /**
     * Установить ID группы пользователей для сотрудников филиала
     * 
     * @param int $groupId ID группы Bitrix
     * @return bool
     */
    public function setBranchEmployeesGroupId($groupId)
    {
        return $this->set(
            'branch_employees_group_id', 
            (int)$groupId, 
            'int', 
            'ID группы Bitrix, пользователи которой будут считаться сотрудниками филиала'
        );
    }
}
