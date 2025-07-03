<?php
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class artmax_calendar extends CModule
{
    public $MODULE_ID = 'artmax.calendar';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $MODULE_GROUP_RIGHTS = 'Y';
    public $PARTNER_NAME = 'АртМакс';
    public $PARTNER_URI  = '#';

    public function __construct()
    {
        $this->MODULE_NAME = Loc::getMessage('ARTMAX_CALENDAR_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('ARTMAX_CALENDAR_MODULE_DESCRIPTION');
        $this->MODULE_VERSION = '1.0.0';
        $this->MODULE_VERSION_DATE = '2024-01-01 00:00:00';
    }

    public function DoInstall()
    {
        ModuleManager::registerModule($this->MODULE_ID);
        $this->InstallDB();
        $this->InstallEvents();
        $this->InstallFiles();
    }

    public function DoUninstall()
    {
        $this->UnInstallFiles();
        $this->UnInstallEvents();
        $this->UnInstallDB();
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }

    public function InstallDB()
    {
        // Создание таблиц базы данных
        $connection = \Bitrix\Main\Application::getConnection();
        
        $sql = "
        CREATE TABLE IF NOT EXISTS artmax_calendar_events (
            ID int(11) NOT NULL AUTO_INCREMENT,
            TITLE varchar(255) NOT NULL,
            DESCRIPTION text,
            DATE_FROM datetime NOT NULL,
            DATE_TO datetime NOT NULL,
            USER_ID int(11) NOT NULL,
            CREATED_AT datetime DEFAULT CURRENT_TIMESTAMP,
            UPDATED_AT datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (ID),
            KEY USER_ID (USER_ID),
            KEY DATE_FROM (DATE_FROM)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ";
        
        $connection->query($sql);
    }

    public function UnInstallDB()
    {
        // Удаление таблиц базы данных
        $connection = \Bitrix\Main\Application::getConnection();
        $connection->query("DROP TABLE IF EXISTS artmax_calendar_events");
    }

    public function InstallEvents()
    {
        // Регистрация обработчиков событий
        $eventManager = \Bitrix\Main\EventManager::getInstance();
        $eventManager->registerEventHandler(
            'main',
            'OnPageStart',
            $this->MODULE_ID,
            'Artmax\\Calendar\\EventHandlers',
            'onPageStart'
        );
    }

    public function UnInstallEvents()
    {
        // Удаление обработчиков событий
        $eventManager = \Bitrix\Main\EventManager::getInstance();
        $eventManager->unRegisterEventHandler(
            'main',
            'OnPageStart',
            $this->MODULE_ID,
            'Artmax\\Calendar\\EventHandlers',
            'onPageStart'
        );
    }

    public function InstallFiles()
    {
        // Копирование файлов модуля
        CopyDirFiles(
            __DIR__ . '/admin/',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/',
            true,
            true
        );
        
        CopyDirFiles(
            __DIR__ . '/components/',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/components/',
            true,
            true
        );
        
        CopyDirFiles(
            __DIR__ . '/js/',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/js/',
            true,
            true
        );
        
        CopyDirFiles(
            __DIR__ . '/css/',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/css/',
            true,
            true
        );
    }

    public function UnInstallFiles()
    {
        // Удаление файлов модуля
        DeleteDirFiles(
            __DIR__ . '/admin/',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/'
        );
        
        DeleteDirFiles(
            __DIR__ . '/components/',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/components/'
        );
        
        DeleteDirFiles(
            __DIR__ . '/js/',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/js/'
        );
        
        DeleteDirFiles(
            __DIR__ . '/css/',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/css/'
        );
    }
} 