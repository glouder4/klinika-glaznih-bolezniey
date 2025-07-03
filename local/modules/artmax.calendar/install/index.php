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
        $eventManager = \Bitrix\Main\EventManager::getInstance();
        $eventManager->registerEventHandler(
            'main',
            'OnPageStart',
            $this->MODULE_ID,
            'Artmax\\Calendar\\EventHandlers',
            'onPageStart'
        );
        $eventManager->registerEventHandler(
            'main',
            'OnEpilog',
            $this->MODULE_ID,
            'Artmax\\Calendar\\EventHandlers',
            'onEpilog'
        );
    }

    public function UnInstallEvents()
    {
        $eventManager = \Bitrix\Main\EventManager::getInstance();
        $eventManager->unRegisterEventHandler(
            'main',
            'OnPageStart',
            $this->MODULE_ID,
            'Artmax\\Calendar\\EventHandlers',
            'onPageStart'
        );
        $eventManager->unRegisterEventHandler(
            'main',
            'OnEpilog',
            $this->MODULE_ID,
            'Artmax\\Calendar\\EventHandlers',
            'onEpilog'
        );
    }

    public function InstallFiles()
    {
        // Создаём папки, если их нет
        $this->createDirectories();
        
        $this->createAdminLinks(
            $_SERVER["DOCUMENT_ROOT"]."/local/modules/".$this->MODULE_ID."/install/admin/",
            $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/".$this->MODULE_ID."/admin/",
            $this->MODULE_ID . '_'
        );

        // Копируем компоненты
        $componentsFrom = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $this->MODULE_ID . '/install/components/';
        $componentsTo = $_SERVER['DOCUMENT_ROOT'] . '/local/components/';
        if (is_dir($componentsFrom)) {
            CopyDirFiles($componentsFrom, $componentsTo, true, true);
            file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Компоненты скопированы из $componentsFrom в $componentsTo\n", FILE_APPEND);
        } else {
            file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Папка компонентов не найдена: $componentsFrom\n", FILE_APPEND);
        }

        // Копируем JS
        $jsFrom = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $this->MODULE_ID . '/install/js/';
        $jsTo = $_SERVER['DOCUMENT_ROOT'] . '/local/js/';
        if (is_dir($jsFrom)) {
            CopyDirFiles($jsFrom, $jsTo, true, true);
            file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "JS скопированы из $jsFrom в $jsTo\n", FILE_APPEND);
        } else {
            file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Папка JS не найдена: $jsFrom\n", FILE_APPEND);
        }
        
        // Копируем CSS
        $cssFrom = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $this->MODULE_ID . '/install/css/';
        $cssTo = $_SERVER['DOCUMENT_ROOT'] . '/local/css/';
        if (is_dir($cssFrom)) {
            CopyDirFiles($cssFrom, $cssTo, true, true);
            file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "CSS скопированы из $cssFrom в $cssTo\n", FILE_APPEND);
        } else {
            file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Папка CSS не найдена: $cssFrom\n", FILE_APPEND);
        }
        // Копирование js-расширения для меню профиля пользователя
        $extensionFrom = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $this->MODULE_ID . '/install/js/artmax-calendar/add_menu_item/';
        $targetDir = $_SERVER['DOCUMENT_ROOT'] . '/local/js/artmax-calendar/add_menu_item/';
        if (is_dir($extensionFrom)) {
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0775, true);
            }
            CopyDirFiles($extensionFrom, $targetDir, true, true);
            file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "JS-расширение скопировано из $extensionFrom в $targetDir\n", FILE_APPEND);
        } else {
            file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Папка JS-расширения не найдена: $extensionFrom\n", FILE_APPEND);
        }
    }

    public function UnInstallFiles()
    {
        $this->removeAdminLinks(
            $_SERVER["DOCUMENT_ROOT"]."/local/modules/".$this->MODULE_ID."/install/admin/",
            $_SERVER["DOCUMENT_ROOT"]."/bitrix/admin/",
            $this->MODULE_ID . '_'
        );

        // Удаляем компоненты полностью
        DeleteDirFilesEx('/local/components/artmax/');

        // Удаляем кастомное расширение
        DeleteDirFilesEx('/local/js/artmax-calendar/add_menu_item/');
        // Удаляем js полностью
        DeleteDirFilesEx('/local/js/artmax-calendar/');
        // Удаляем css полностью
        DeleteDirFilesEx('/local/css/artmax.calendar/');

    }

    private function createAdminLinks($fromDir, $toDir, $prefix = '', $exclude = ['.', '..', 'menu.php'])
    {
        if (is_dir($fromDir)) {
            if ($dir = opendir($fromDir)) {
                while (false !== $item = readdir($dir)) {
                    if (in_array($item, $exclude)) continue;
                    $linkFile = $toDir . $prefix . $item;
                    $linkContent = '<?php require($_SERVER["DOCUMENT_ROOT"] . "/local/modules/' . $this->MODULE_ID . '/install/admin/' . $item . '"); ?>';
                    $result = file_put_contents($linkFile, $linkContent);
                    if ($result === false) {
                        file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Не удалось создать $linkFile\n", FILE_APPEND);
                    } else {
                        file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Создан линк: $linkFile\n", FILE_APPEND);
                    }
                }
                closedir($dir);
            } else {
                file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Не удалось открыть папку $fromDir\n", FILE_APPEND);
            }
        } else {
            file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Папка $fromDir не найдена\n", FILE_APPEND);
        }
    }

    private function removeAdminLinks($fromDir, $toDir, $prefix = '', $exclude = ['.', '..', 'menu.php'])
    {
        if (is_dir($fromDir)) {
            if ($dir = opendir($fromDir)) {
                while (false !== $item = readdir($dir)) {
                    if (in_array($item, $exclude)) continue;
                    $linkFile = $toDir . $prefix . $item;
                    if (file_exists($linkFile)) {
                        if (!unlink($linkFile)) {
                            file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Не удалось удалить $linkFile\n", FILE_APPEND);
                        }
                    }
                }
                closedir($dir);
            } else {
                file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Не удалось открыть папку $fromDir\n", FILE_APPEND);
            }
        } else {
            file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Папка $fromDir не найдена\n", FILE_APPEND);
        }
    }

    private function createDirectories()
    {
        $directories = [
            $_SERVER['DOCUMENT_ROOT'] . '/local/components/',
            $_SERVER['DOCUMENT_ROOT'] . '/local/js/',
            $_SERVER['DOCUMENT_ROOT'] . '/local/css/',
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0775, true)) {
                    file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Не удалось создать папку $dir\n", FILE_APPEND);
                }
            }
        }
    }
} 