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
        $this->InstallFiles();
        $this->InstallEvents();
    }

    public function DoUninstall()
    {

        $this->UnInstallEvents();
        $this->UnInstallFiles();
        $this->UnInstallDB();
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }

    public function InstallDB()
    {
        // Создание таблиц базы данных
        $connection = \Bitrix\Main\Application::getConnection();
        
        // Таблица событий
        $sqlEvents = "
        CREATE TABLE IF NOT EXISTS artmax_calendar_events (
            ID int(11) NOT NULL AUTO_INCREMENT,
            TITLE varchar(255) NOT NULL,
            DESCRIPTION text,
            DATE_FROM datetime NOT NULL,
            DATE_TO datetime NOT NULL,
            USER_ID int(11) NOT NULL,
            BRANCH_ID int(11) NOT NULL DEFAULT 1,
            EVENT_COLOR varchar(7) DEFAULT '#3498db',
            CONTACT_ENTITY_ID int(11) DEFAULT NULL COMMENT 'ID контакта из CRM',
            DEAL_ENTITY_ID int(11) DEFAULT NULL COMMENT 'ID сделки из CRM',
            NOTE text DEFAULT NULL COMMENT 'Заметка к событию',
            CONFIRMATION_STATUS enum('pending','confirmed','not_confirmed') DEFAULT 'pending' COMMENT 'Статус подтверждения события',
            CREATED_AT datetime DEFAULT CURRENT_TIMESTAMP,
            UPDATED_AT datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (ID),
            KEY USER_ID (USER_ID),
            KEY DATE_FROM (DATE_FROM),
            KEY BRANCH_ID (BRANCH_ID),
            KEY CONTACT_ENTITY_ID (CONTACT_ENTITY_ID),
            KEY DEAL_ENTITY_ID (DEAL_ENTITY_ID),
            KEY CONFIRMATION_STATUS (CONFIRMATION_STATUS)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ";
        
        // Таблица филиалов
        $sqlBranches = "
        CREATE TABLE IF NOT EXISTS artmax_calendar_branches (
            ID int(11) NOT NULL AUTO_INCREMENT,
            NAME varchar(255) NOT NULL,
            ADDRESS text,
            PHONE varchar(50),
            EMAIL varchar(255),
            CREATED_AT datetime DEFAULT CURRENT_TIMESTAMP,
            UPDATED_AT datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (ID)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ";

        // Таблица настроек филиалов
        $sqlBranchesSettings = "
        CREATE TABLE IF NOT EXISTS artmax_calendar_timezone_settings (
            ID INT AUTO_INCREMENT PRIMARY KEY,
            BRANCH_ID INT NOT NULL,
            TIMEZONE_NAME VARCHAR(50) NOT NULL,
            TIMEZONE_OFFSET INT NOT NULL,
            DST_ENABLED TINYINT(1) DEFAULT 1,
            DST_START_MONTH TINYINT DEFAULT 3,
            DST_START_DAY TINYINT DEFAULT 31,
            DST_START_HOUR TINYINT DEFAULT 2,
            DST_END_MONTH TINYINT DEFAULT 10,
            DST_END_DAY TINYINT DEFAULT 27,
            DST_END_HOUR TINYINT DEFAULT 3,
            IS_ACTIVE TINYINT(1) DEFAULT 1,
            CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UPDATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_branch_timezone (BRANCH_ID),
            FOREIGN KEY (BRANCH_ID) REFERENCES artmax_calendar_branches(ID) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $sqlModifier = "
        INSERT IGNORE INTO artmax_calendar_timezone_settings (BRANCH_ID, TIMEZONE_NAME, TIMEZONE_OFFSET, DST_ENABLED) VALUES
        (1, 'Europe/Moscow', 3, 1),
        (2, 'Europe/Moscow', 3, 1),
        (3, 'Asia/Yekaterinburg', 5, 1),
        (4, 'Asia/Novosibirsk', 7, 1),
        (5, 'Asia/Vladivostok', 10, 1);
        ";
        
        $connection->query($sqlEvents);
        $connection->query($sqlBranches);
        $connection->query($sqlBranchesSettings);
        $connection->query($sqlModifier);
    }

    public function UnInstallDB()
    {
        // Удаление таблиц базы данных
        $connection = \Bitrix\Main\Application::getConnection();
        $connection->query("DROP TABLE IF EXISTS artmax_calendar_events");
        $connection->query("DROP TABLE IF EXISTS artmax_calendar_branches");
        $connection->query("DROP TABLE IF EXISTS artmax_calendar_timezone_settings");
        
        // Удаляем настройки модуля
        \Bitrix\Main\Config\Option::delete('artmax.calendar', ['name' => 'menu_item_id']);
        \Bitrix\Main\Config\Option::delete('artmax.calendar', ['name' => 'custom_section_id']);
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

        // Регистрируем события модуля
        if (CModule::IncludeModule('artmax.calendar')) {
            \Artmax\Calendar\EventHandlers::onModuleInstall();
            \Artmax\Calendar\EventHandlers::createCustomSection();
        }
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

        // Отменяем регистрацию событий и удаляем настраиваемый раздел
        if (CModule::IncludeModule('artmax.calendar')) {
            try {
                \Artmax\Calendar\EventHandlers::onModuleUninstall();
            } catch (\Exception $e) {
                // Логируем ошибку, но продолжаем удаление
                \CEventLog::Add([
                    'SEVERITY' => 'ERROR',
                    'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_UNINSTALL_EVENTS_ERROR',
                    'MODULE_ID' => 'artmax.calendar',
                    'DESCRIPTION' => 'Ошибка отмены регистрации событий: ' . $e->getMessage()
                ]);
            }
            
            try {
                \Artmax\Calendar\EventHandlers::removeCustomSection();
            } catch (\Exception $e) {
                // Логируем ошибку, но продолжаем удаление
                \CEventLog::Add([
                    'SEVERITY' => 'ERROR',
                    'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_UNINSTALL_SECTION_ERROR',
                    'MODULE_ID' => 'artmax.calendar',
                    'DESCRIPTION' => 'Ошибка удаления настраиваемого раздела: ' . $e->getMessage()
                ]);
            }
        }
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

        // Копируем публичную страницу календаря в корень сайта
        $calendarPageFrom = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $this->MODULE_ID . '/install/admin/artmax_calendar_view.php';
        $calendarPageTo = $_SERVER['DOCUMENT_ROOT'] . '/artmax-calendar.php';
        if (file_exists($calendarPageFrom)) {
            CopyDirFiles($calendarPageFrom, $calendarPageTo, true, true);
            file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Страница календаря скопирована из $calendarPageFrom в $calendarPageTo\n", FILE_APPEND);
        } else {
            file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Файл страницы календаря не найден: $calendarPageFrom\n", FILE_APPEND);
        }

        // Копируем .htaccess для ЧПУ
        $htaccessFrom = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $this->MODULE_ID . '/install/.htaccess';
        $htaccessTo = $_SERVER['DOCUMENT_ROOT'] . '/.htaccess';
        if (file_exists($htaccessFrom)) {
            // Добавляем правила в существующий .htaccess или создаем новый
            $htaccessContent = file_get_contents($htaccessFrom);
            if (file_exists($htaccessTo)) {
                $existingContent = file_get_contents($htaccessTo);
                if (strpos($existingContent, 'artmax-calendar') === false) {
                    file_put_contents($htaccessTo, $existingContent . "\n\n# ArtMax Calendar Rules\n" . $htaccessContent, LOCK_EX);
                    file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Правила календаря добавлены в .htaccess\n", FILE_APPEND);
                }
            } else {
                file_put_contents($htaccessTo, $htaccessContent, LOCK_EX);
                file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Создан новый .htaccess с правилами календаря\n", FILE_APPEND);
            }
        } else {
            file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Файл .htaccess не найден: $htaccessFrom\n", FILE_APPEND);
        }

        // Копируем .settings.php для провайдера
        $settingsFrom = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $this->MODULE_ID . '/.settings.php';
        if (file_exists($settingsFrom)) {
            // Файл уже находится в правильном месте, просто проверяем
            file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Файл .settings.php найден: $settingsFrom\n", FILE_APPEND);
        } else {
            file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Файл .settings.php не найден: $settingsFrom\n", FILE_APPEND);
        }

        // Копируем файл меню
        $menuFrom = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $this->MODULE_ID . '/install/menu/artmax_calendar_menu.php';
        $menuTo = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/artmax_calendar_menu.php';
        if (file_exists($menuFrom)) {
            CopyDirFiles($menuFrom, $menuTo, true, true);
            file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Файл меню скопирован из $menuFrom в $menuTo\n", FILE_APPEND);
        } else {
            file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Файл меню не найден: $menuFrom\n", FILE_APPEND);
        }

        // Копируем файл регистрации провайдера
        $registerFrom = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $this->MODULE_ID . '/install/admin/artmax_calendar_register.php';
        $registerTo = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/artmax_calendar_register.php';
        if (file_exists($registerFrom)) {
            CopyDirFiles($registerFrom, $registerTo, true, true);
            file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Файл регистрации скопирован из $registerFrom в $registerTo\n", FILE_APPEND);
        } else {
            file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Файл регистрации не найден: $registerFrom\n", FILE_APPEND);
        }

        // Копируем файл проверки классов
        $classesFrom = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $this->MODULE_ID . '/install/admin/artmax_calendar_classes.php';
        $classesTo = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/artmax_calendar_classes.php';
        if (file_exists($classesFrom)) {
            CopyDirFiles($classesFrom, $classesTo, true, true);
            file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Файл проверки классов скопирован из $classesFrom в $classesTo\n", FILE_APPEND);
        } else {
            file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Файл проверки классов не найден: $classesFrom\n", FILE_APPEND);
        }

        // Копируем AJAX endpoint в корень сайта
        $ajaxFrom = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $this->MODULE_ID . '/install/ajax.php';
        $ajaxTo = $_SERVER['DOCUMENT_ROOT'] . '/local/components/artmax/calendar/ajax.php';
        if (file_exists($ajaxFrom)) {
            CopyDirFiles($ajaxFrom, $ajaxTo, true, true);
            file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "AJAX endpoint скопирован из $ajaxFrom в $ajaxTo\n", FILE_APPEND);
        } else {
            file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "AJAX endpoint не найден: $ajaxFrom\n", FILE_APPEND);
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
        
        // Удаляем публичную страницу календаря
        $calendarPage = $_SERVER['DOCUMENT_ROOT'] . '/artmax-calendar.php';
        if (file_exists($calendarPage)) {
            unlink($calendarPage);
            file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Страница календаря удалена: $calendarPage\n", FILE_APPEND);
        }

        // Удаляем AJAX endpoint
        $ajaxFile = $_SERVER['DOCUMENT_ROOT'] . '/local/components/artmax/calendar/ajax.php';
        if (file_exists($ajaxFile)) {
            unlink($ajaxFile);
            file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "AJAX endpoint удален: $ajaxFile\n", FILE_APPEND);
        }

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