<?php
namespace Artmax\Calendar;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Exception;

class Branch
{
    /**
     * Получает список всех филиалов
     */
    public function getBranches()
    {
        $connection = Application::getConnection();
        $result = $connection->query("SELECT * FROM artmax_calendar_branches ORDER BY ID");
        
        $branches = [];
        while ($row = $result->fetch()) {
            $branches[] = $row;
        }
        
        return $branches;
    }

    /**
     * Получает филиал по ID
     */
    public function getBranch($id)
    {
        $connection = Application::getConnection();
        $result = $connection->query("SELECT * FROM artmax_calendar_branches WHERE ID = " . (int)$id);
        
        return $result->fetch();
    }

    /**
     * Добавляет новый филиал
     */
    public function addBranch($name, $address = '', $phone = '', $email = '')
    {
        // В реальном проекте здесь должна быть вставка в базу данных
        $connection = Application::getConnection();
        
        try {
            $result = $connection->query("
                INSERT INTO artmax_calendar_branches (NAME, ADDRESS, PHONE, EMAIL, CREATED_AT) 
                VALUES ('" . $connection->getSqlHelper()->forSql($name) . "', 
                        '" . $connection->getSqlHelper()->forSql($address) . "', 
                        '" . $connection->getSqlHelper()->forSql($phone) . "', 
                        '" . $connection->getSqlHelper()->forSql($email) . "', 
                        NOW())
            ");
            
            return $connection->getInsertedId();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Обновляет филиал
     */
    public function updateBranch($id, $name, $address = '', $phone = '', $email = '')
    {
        $connection = Application::getConnection();
        
        try {
            $connection->query("
                UPDATE artmax_calendar_branches 
                SET NAME = '" . $connection->getSqlHelper()->forSql($name) . "',
                    ADDRESS = '" . $connection->getSqlHelper()->forSql($address) . "',
                    PHONE = '" . $connection->getSqlHelper()->forSql($phone) . "',
                    EMAIL = '" . $connection->getSqlHelper()->forSql($email) . "',
                    UPDATED_AT = NOW()
                WHERE ID = " . (int)$id
            );
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Удаляет филиал
     */
    public function deleteBranch($id)
    {
        $connection = Application::getConnection();
        
        try {
            $connection->query("DELETE FROM artmax_calendar_branches WHERE ID = " . (int)$id);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
} 