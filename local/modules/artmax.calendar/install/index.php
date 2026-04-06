<?php
use Bitrix\Main\ModuleManager;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

class artmax_calendar extends CModule
{
    public $MODULE_ID = 'artmax.calendar';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME;
    public $MODULE_DESCRIPTION;
    public $MODULE_GROUP_RIGHTS = 'Y';
    public $MODULE_RIGHTS = 'Y';
    public $PARTNER_NAME = 'АртМакс';
    public $PARTNER_URI  = '#';

    public function __construct()
    {
        // Загружаем языковые файлы
        Loc::loadMessages(__FILE__);
        
        // Получаем сообщения с fallback значениями
        $this->MODULE_NAME = Loc::getMessage('ARTMAX_CALENDAR_MODULE_NAME') ?: 'Онлайн запись | ArtMax';
        $this->MODULE_DESCRIPTION = Loc::getMessage('ARTMAX_CALENDAR_MODULE_DESCRIPTION') ?: 'Модуль для реализации онлайн-записи пациентов медицинских клиник';
        $this->MODULE_VERSION = '1.0.0';
        $this->MODULE_VERSION_DATE = '2024-01-01 00:00:00';
    }

    public function DoInstall()
    {
        global $APPLICATION;
        
        try {
            // Проверяем, установлен ли уже модуль
            $isModuleInstalled = ModuleManager::isModuleInstalled($this->MODULE_ID);
            
            // Если модуль еще не установлен, выполняем установку
            if (!$isModuleInstalled) {
                // Сначала выполняем установку модуля (БД, события, файлы)
                $dbResult = $this->InstallDB();
                if ($dbResult === false) {
                    if (isset($GLOBALS["errors"]) && is_array($GLOBALS["errors"])) {
                        $errorMsg = implode("\n", $GLOBALS["errors"]);
                    } else {
                        $errorMsg = 'Ошибка установки базы данных';
                    }
                    $APPLICATION->ThrowException($errorMsg);
                    return false;
                }
                
                $this->InstallEvents();
                $this->InstallFiles();
                
                // Регистрируем модуль в системе ПОСЛЕ всех установочных операций
                ModuleManager::registerModule($this->MODULE_ID);
            }
            
            // Очищаем кэш после установки
            if (function_exists('BXClearCache')) {
                BXClearCache(true);
            }
            
            // Редирект на страницу настроек после установки
            $settingsUrl = '/bitrix/admin/artmax.calendar_artmax_calendar_settings.php?lang=' . LANGUAGE_ID;
            
            // Используем JavaScript редирект для надежности
            echo '<script>window.location.href="' . htmlspecialchars($settingsUrl) . '";</script>';
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($settingsUrl) . '"></noscript>';
            
            // Также делаем PHP редирект на случай, если JavaScript отключен
            LocalRedirect($settingsUrl);
            
        } catch (\Exception $e) {
            $APPLICATION->ThrowException('Ошибка при установке модуля: ' . $e->getMessage());
            return false;
        } catch (\Error $e) {
            $APPLICATION->ThrowException('Критическая ошибка при установке модуля: ' . $e->getMessage());
            return false;
        }
    }

    public function DoUninstall()
    {
        global $APPLICATION;
        $request = \Bitrix\Main\Context::getCurrent()->getRequest();
        $step = (int)$request->get('step');
        
        if ($step < 2) {
            // Первый шаг - показываем форму выбора
            $APPLICATION->IncludeAdminFile(
                Loc::getMessage('ARTMAX_CALENDAR_UNINSTALL_TITLE'),
                $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $this->MODULE_ID . '/install/unstep.php'
            );
            return;
        }
        
        // Второй шаг - выполняем удаление с параметрами из формы
        $deleteGroups = $request->get('delete_groups') == 'Y';
        $deleteTables = $request->get('delete_tables') == 'Y';
        $deleteCrmFields = $request->get('delete_crm_fields') == 'Y';
        $deleteSettings = $request->get('delete_settings') == 'Y';
        $deleteFiles = $request->get('delete_files') == 'Y';
        
        $options = [
            'delete_groups' => $deleteGroups,
            'delete_tables' => $deleteTables,
            'delete_crm_fields' => $deleteCrmFields,
            'delete_settings' => $deleteSettings,
            'delete_files' => $deleteFiles
        ];

        $this->UnInstallEvents();
        
        if ($options['delete_files']) {
            $this->UnInstallFiles();
        }
        
        if ($options['delete_tables'] || $options['delete_groups'] || $options['delete_crm_fields'] || $options['delete_settings']) {
            $this->UnInstallDB($options);
        }
        
        ModuleManager::unRegisterModule($this->MODULE_ID);
        
        // Показываем результат удаления
        $APPLICATION->IncludeAdminFile(
            Loc::getMessage('ARTMAX_CALENDAR_UNINSTALL_TITLE'),
            $_SERVER['DOCUMENT_ROOT'] . '/local/modules/' . $this->MODULE_ID . '/install/unstep.php'
        );
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
            ORIGINAL_DATE_FROM datetime DEFAULT NULL COMMENT 'Оригинальная дата начала (заполняется только при создании)',
            ORIGINAL_DATE_TO datetime DEFAULT NULL COMMENT 'Оригинальная дата окончания (заполняется только при создании)',
            TIME_IS_CHANGED tinyint(1) DEFAULT 0 COMMENT 'Флаг изменения времени записи',
            USER_ID int(11) NOT NULL,
            BRANCH_ID int(11) NOT NULL DEFAULT 1,
            EVENT_COLOR varchar(7) DEFAULT '#3498db',
            CONTACT_ENTITY_ID int(11) DEFAULT NULL COMMENT 'ID контакта из CRM',
            DEAL_ENTITY_ID int(11) DEFAULT NULL COMMENT 'ID сделки из CRM',
            ACTIVITY_ID int(11) DEFAULT NULL COMMENT 'ID активности (бронирования) в CRM',
            NOTE text DEFAULT NULL COMMENT 'Заметка к событию',
            EMPLOYEE_ID int(11) DEFAULT NULL COMMENT 'ID ответственного сотрудника',
            CONFIRMATION_STATUS enum('pending','confirmed','not_confirmed') DEFAULT 'pending' COMMENT 'Статус подтверждения события',
            STATUS enum('active','moved','cancelled') DEFAULT 'active' COMMENT 'Статус события',
            VISIT_STATUS enum('not_specified','client_came','client_did_not_come') DEFAULT 'not_specified' COMMENT 'Статус визита клиента',
            CREATED_AT datetime DEFAULT CURRENT_TIMESTAMP,
            UPDATED_AT datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (ID),
            KEY USER_ID (USER_ID),
            KEY DATE_FROM (DATE_FROM),
            KEY BRANCH_ID (BRANCH_ID),
            KEY CONTACT_ENTITY_ID (CONTACT_ENTITY_ID),
            KEY DEAL_ENTITY_ID (DEAL_ENTITY_ID),
            KEY ACTIVITY_ID (ACTIVITY_ID),
            KEY EMPLOYEE_ID (EMPLOYEE_ID),
            KEY CONFIRMATION_STATUS (CONFIRMATION_STATUS),
            KEY STATUS (STATUS),
            KEY TIME_IS_CHANGED (TIME_IS_CHANGED),
            KEY VISIT_STATUS (VISIT_STATUS)
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
            TIMEZONE_NAME varchar(50) DEFAULT 'Europe/Moscow',
            IS_ACTIVE tinyint(1) DEFAULT 1,
            CREATED_AT datetime DEFAULT CURRENT_TIMESTAMP,
            UPDATED_AT datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (ID)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ";

        // Таблица настроек модуля
        $sqlModuleSettings = "
        CREATE TABLE IF NOT EXISTS artmax_calendar_module_settings (
            ID INT AUTO_INCREMENT PRIMARY KEY,
            SETTING_KEY VARCHAR(100) NOT NULL UNIQUE COMMENT 'Ключ настройки',
            SETTING_VALUE TEXT DEFAULT NULL COMMENT 'Значение настройки (JSON или строка)',
            SETTING_TYPE ENUM('string', 'int', 'json', 'bool') DEFAULT 'string' COMMENT 'Тип значения',
            DESCRIPTION TEXT DEFAULT NULL COMMENT 'Описание настройки',
            CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UPDATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_key (SETTING_KEY)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        // Таблица связи филиалов и сотрудников
        $sqlBranchesSettings = "
        CREATE TABLE IF NOT EXISTS artmax_calendar_branch_employees (
            ID INT AUTO_INCREMENT PRIMARY KEY,
            BRANCH_ID INT NOT NULL,
            EMPLOYEE_ID INT NOT NULL,
            CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_branch_employee (BRANCH_ID, EMPLOYEE_ID),
            FOREIGN KEY (BRANCH_ID) REFERENCES artmax_calendar_branches(ID) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        // Таблица журнала событий
        $sqlEventJournal = "
        CREATE TABLE IF NOT EXISTS artmax_calendar_event_journal (
            ID INT(11) NOT NULL AUTO_INCREMENT,
            EVENT_ID INT(11) NOT NULL COMMENT 'ID события',
            ACTION VARCHAR(100) NOT NULL COMMENT 'Действие (created, updated, deleted, moved, etc.)',
            ACTION_DATE DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата и время действия',
            ACTION_VALUE TEXT DEFAULT NULL COMMENT 'Значение действия (что записалось, что отвязалось и т.п.)',
            INITIATOR VARCHAR(255) DEFAULT NULL COMMENT 'Название класса и функции инициатора',
            USER_ID INT(11) DEFAULT NULL COMMENT 'ID пользователя, выполнившего действие',
            PRIMARY KEY (ID),
            KEY EVENT_ID (EVENT_ID),
            KEY ACTION_DATE (ACTION_DATE),
            KEY USER_ID (USER_ID),
            KEY ACTION (ACTION),
            FOREIGN KEY (EVENT_ID) REFERENCES artmax_calendar_events(ID) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        $connection->query($sqlEvents);
        $connection->query($sqlBranches);
        $connection->query($sqlBranchesSettings);
        $connection->query($sqlModuleSettings);
        $connection->query($sqlEventJournal);
        
        // Создаем таблицы для системы прав доступа
        $this->createPermissionsTables();
        
        // Создаем группы пользователей
        try {
            $this->createUserGroups();
        } catch (\Exception $e) {
            \CEventLog::Add([
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_INSTALL_GROUPS_ERROR',
                'MODULE_ID' => 'artmax.calendar',
                'OBJECT_ID' => 0,
                'DESCRIPTION' => 'Критическая ошибка создания групп при установке: ' . $e->getMessage()
            ]);
            global $APPLICATION;
            $APPLICATION->ThrowException('Ошибка создания групп пользователей: ' . $e->getMessage());
            throw $e;
        }
        
        // Создаем базовые права
        $this->createDefaultPermissions();
        
        // Назначаем права группам по умолчанию
        $this->assignDefaultPermissions();
        
        // Проверяем и добавляем колонку IS_ACTIVE, если её нет (миграция для существующих установок)
        try {
            $checkSql = "SHOW COLUMNS FROM artmax_calendar_branches LIKE 'IS_ACTIVE'";
            $checkResult = $connection->query($checkSql);
            if ($checkResult->getSelectedRowsCount() == 0) {
                // Колонка отсутствует, добавляем её
                $alterSql = "ALTER TABLE artmax_calendar_branches ADD COLUMN IS_ACTIVE tinyint(1) DEFAULT 1";
                $connection->query($alterSql);
            }
        } catch (\Exception $e) {
            // Игнорируем ошибку, возможно таблица ещё не создана
        }
        
        // Создаем первый филиал по умолчанию (только если его еще нет)
        // Проверяем, существует ли уже филиал с таким названием
        $checkBranchSql = "SELECT ID FROM artmax_calendar_branches WHERE NAME = 'Филиал - 1' LIMIT 1";
        $checkResult = $connection->query($checkBranchSql);
        $existingBranch = $checkResult->fetch();
        $defaultBranchId = null;
        
        if (!$existingBranch) {
            // Филиала еще нет, создаем его
            $sqlDefaultBranch = "
            INSERT INTO artmax_calendar_branches (NAME, ADDRESS, PHONE, EMAIL, TIMEZONE_NAME, IS_ACTIVE) 
            VALUES ('Филиал - 1', '', '', '', 'Europe/Moscow', 1)
            ";
            $connection->query($sqlDefaultBranch);
            
            // Получаем ID созданного филиала (более надежный способ)
            $result = $connection->query("SELECT ID FROM artmax_calendar_branches WHERE NAME = 'Филиал - 1' ORDER BY ID DESC LIMIT 1");
            $row = $result->fetch();
            $defaultBranchId = $row ? (int)$row['ID'] : null;
        } else {
            // Филиал уже существует, используем его ID
            $defaultBranchId = (int)$existingBranch['ID'];
        }
        
        // Создаем пользовательское поле "Бронирование" для сделки
        $this->createDealBookingField();
        
        // Создаем пользовательские поля "Подтверждение" и "Визит" для сделки
        $this->createDealConfirmationField();
        $this->createDealVisitField();
        $this->createDealServiceField();
        $this->createDealSourceField();
        $this->createDealAmountField();
        $this->createDealBranchField();
        
        // Добавляем филиал по умолчанию в список пользовательского поля "Филиал"
        if ($defaultBranchId) {
            try {
                $this->addBranchToDealFieldEnum($defaultBranchId, 'Филиал - 1');
            } catch (\Exception $e) {
                // Логируем ошибку, но не прерываем установку
                \CEventLog::Add([
                    'SEVERITY' => 'ERROR',
                    'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_INSTALL_BRANCH_ENUM_ERROR',
                    'MODULE_ID' => 'artmax.calendar',
                    'OBJECT_ID' => $defaultBranchId ?: 0,
                    'DESCRIPTION' => 'Ошибка добавления филиала в enum: ' . $e->getMessage()
                ]);
            }
        }
        
        return true;
    }
    
    /**
     * Создание пользовательского поля "Бронирование" для сделки
     */
    private function createDealBookingField()
    {
        if (!\Bitrix\Main\Loader::includeModule('crm')) {
            return;
        }
        
        $fieldCode = 'UF_CRM_CALENDAR_BOOKING';
        
        // Проверяем, существует ли уже поле
        $existingField = \CUserTypeEntity::GetList(
            [],
            [
                'ENTITY_ID' => 'CRM_DEAL',
                'FIELD_NAME' => $fieldCode
            ]
        )->Fetch();
        
        if ($existingField) {
            // Сохраняем ID существующего поля в настройках модуля
            \Bitrix\Main\Config\Option::set('artmax.calendar', 'deal_booking_field', $fieldCode);
            return;
        }
        
        // Создаем новое поле типа "Бронирование"
        $userTypeEntity = new \CUserTypeEntity();
        $fieldId = $userTypeEntity->Add([
            'ENTITY_ID' => 'CRM_DEAL',
            'FIELD_NAME' => $fieldCode,
            'USER_TYPE_ID' => 'resourcebooking', // Тип "Бронирование"
            'SORT' => 500,
            'MULTIPLE' => 'Y', // Множественное
            'MANDATORY' => 'N',
            'SHOW_FILTER' => 'N',
            'SHOW_IN_LIST' => 'Y',
            'EDIT_IN_LIST' => 'Y',
            'IS_SEARCHABLE' => 'N',
            'SETTINGS' => [
                'CALENDAR_IBLOCK_ID' => 0 // ID инфоблока календаря (если нужен)
            ],
            'EDIT_FORM_LABEL' => [
                'ru' => 'Бронирование из календаря клиники',
                'en' => 'Calendar Clinic Booking'
            ],
            'LIST_COLUMN_LABEL' => [
                'ru' => 'Бронирование',
                'en' => 'Booking'
            ],
            'LIST_FILTER_LABEL' => [
                'ru' => 'Бронирование',
                'en' => 'Booking'
            ]
        ]);
        
        if ($fieldId) {
            // Сохраняем код поля в настройках модуля
            \Bitrix\Main\Config\Option::set('artmax.calendar', 'deal_booking_field', $fieldCode);
        }
    }

    public function UnInstallDB($options = null)
    {
        // Если параметры не переданы, используем значения по умолчанию (полное удаление)
        if ($options === null) {
            $options = [
                'delete_groups' => true,
                'delete_tables' => true,
                'delete_crm_fields' => true,
                'delete_settings' => true
            ];
        }
        
        // Удаление групп пользователей
        if ($options['delete_groups']) {
            $this->deleteUserGroups();
        }
        
        // Удаление таблиц базы данных
        if ($options['delete_tables']) {
            $connection = \Bitrix\Main\Application::getConnection();
            $connection->query("DROP TABLE IF EXISTS artmax_calendar_group_links");
            $connection->query("DROP TABLE IF EXISTS artmax_calendar_access_rights");
            $connection->query("DROP TABLE IF EXISTS artmax_calendar_permissions");
            $connection->query("DROP TABLE IF EXISTS artmax_calendar_user_groups");
            $connection->query("DROP TABLE IF EXISTS artmax_calendar_event_journal");
            $connection->query("DROP TABLE IF EXISTS artmax_calendar_branch_employees");
            $connection->query("DROP TABLE IF EXISTS artmax_calendar_events");
            $connection->query("DROP TABLE IF EXISTS artmax_calendar_branches");
            $connection->query("DROP TABLE IF EXISTS artmax_calendar_module_settings");
        }
        
        // Удаляем пользовательские поля CRM
        if ($options['delete_crm_fields']) {
            $this->deleteDealBookingField();
            $this->deleteDealConfirmationField();
            $this->deleteDealVisitField();
            $this->deleteDealServiceField();
            $this->deleteDealSourceField();
            $this->deleteDealAmountField();
            $this->deleteDealBranchField();
        }
        
        // Удаляем настройки модуля
        if ($options['delete_settings']) {
            \Bitrix\Main\Config\Option::delete('artmax.calendar', ['name' => 'menu_item_id']);
            \Bitrix\Main\Config\Option::delete('artmax.calendar', ['name' => 'custom_section_id']);
            \Bitrix\Main\Config\Option::delete('artmax.calendar', ['name' => 'deal_booking_field']);
            \Bitrix\Main\Config\Option::delete('artmax.calendar', ['name' => 'deal_confirmation_field']);
            \Bitrix\Main\Config\Option::delete('artmax.calendar', ['name' => 'deal_visit_field']);
            \Bitrix\Main\Config\Option::delete('artmax.calendar', ['name' => 'deal_service_field']);
            \Bitrix\Main\Config\Option::delete('artmax.calendar', ['name' => 'deal_source_field']);
            \Bitrix\Main\Config\Option::delete('artmax.calendar', ['name' => 'deal_amount_field']);
            \Bitrix\Main\Config\Option::delete('artmax.calendar', ['name' => 'deal_branch_field']);
        }
    }
    
    /**
     * Удаление групп пользователей, созданных модулем
     */
    private function deleteUserGroups()
    {
        try {
            $deletedCount = 0;
            $processedIds = []; // Чтобы не удалять одну группу дважды
            
            // Получаем все группы и фильтруем их
            $rsGroups = \CGroup::GetList(
                $by = 'ID',
                $order = 'ASC'
            );
            
            while ($group = $rsGroups->Fetch()) {
                if ($group && isset($group['ID'])) {
                    $groupId = (int)$group['ID'];
                    
                    // Пропускаем системные группы (ID 1 и 2)
                    if ($groupId <= 2) {
                        continue;
                    }
                    
                    // Пропускаем уже обработанные группы
                    if (in_array($groupId, $processedIds)) {
                        continue;
                    }
                    
                    $groupName = $group['NAME'] ?? '';
                    $stringId = $group['STRING_ID'] ?? '';
                    
                    // Проверяем, что группа создана модулем по названию или STRING_ID
                    $isModuleGroup = false;
                    
                    if (strpos($groupName, 'Артмакс.Календарь') === 0) {
                        $isModuleGroup = true;
                    } elseif (!empty($stringId) && strpos($stringId, 'artmax_calendar_') === 0) {
                        $isModuleGroup = true;
                    }
                    
                    if ($isModuleGroup) {
                        $groupObj = new \CGroup();
                        if ($groupObj->Delete($groupId)) {
                            $deletedCount++;
                            $processedIds[] = $groupId;
                            \CEventLog::Add([
                                'SEVERITY' => 'INFO',
                                'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_GROUP_DELETED',
                                'MODULE_ID' => 'artmax.calendar',
                                'OBJECT_ID' => $groupId,
                                'DESCRIPTION' => 'Удалена группа: ' . $groupName . ' (ID: ' . $groupId . ', STRING_ID: ' . $stringId . ')'
                            ]);
                        } else {
                            \CEventLog::Add([
                                'SEVERITY' => 'ERROR',
                                'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_GROUP_DELETE_ERROR',
                                'MODULE_ID' => 'artmax.calendar',
                                'OBJECT_ID' => $groupId,
                                'DESCRIPTION' => 'Ошибка удаления группы: ' . $groupName . ' (ID: ' . $groupId . '). ' . ($groupObj->LAST_ERROR ?: 'Неизвестная ошибка')
                            ]);
                        }
                    }
                }
            }
            
            if ($deletedCount > 0) {
            \CEventLog::Add([
                'SEVERITY' => 'INFO',
                'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_GROUPS_DELETED',
                'MODULE_ID' => 'artmax.calendar',
                'OBJECT_ID' => 0,
                'DESCRIPTION' => 'Удалено групп пользователей: ' . $deletedCount
            ]);
            }
        } catch (\Exception $e) {
            \CEventLog::Add([
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_DELETE_GROUPS_ERROR',
                'MODULE_ID' => 'artmax.calendar',
                'OBJECT_ID' => 0,
                'DESCRIPTION' => 'Ошибка удаления групп пользователей: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Создание пользовательского поля "Подтверждение" для сделки
     */
    private function createDealConfirmationField()
    {
        if (!\Bitrix\Main\Loader::includeModule('crm')) {
            return;
        }
        
        $fieldCode = 'UF_CRM_CALENDAR_CONFIRM';
        
        // Проверяем, существует ли уже поле
        $existingField = \CUserTypeEntity::GetList(
            [],
            [
                'ENTITY_ID' => 'CRM_DEAL',
                'FIELD_NAME' => $fieldCode
            ]
        )->Fetch();
        
        if ($existingField) {
            \Bitrix\Main\Config\Option::set('artmax.calendar', 'deal_confirmation_field', $fieldCode);
            return;
        }
        
        // Создаем новое поле типа "Список"
        $userTypeEntity = new \CUserTypeEntity();
        $fieldId = $userTypeEntity->Add([
            'ENTITY_ID' => 'CRM_DEAL',
            'FIELD_NAME' => $fieldCode,
            'USER_TYPE_ID' => 'enumeration', // Тип "Список"
            'SORT' => 510,
            'MULTIPLE' => 'N',
            'MANDATORY' => 'N',
            'SHOW_FILTER' => 'Y',
            'SHOW_IN_LIST' => 'Y',
            'EDIT_IN_LIST' => 'Y',
            'IS_SEARCHABLE' => 'N',
            'EDIT_FORM_LABEL' => [
                'ru' => 'Подтверждение записи',
                'en' => 'Appointment Confirmation'
            ],
            'LIST_COLUMN_LABEL' => [
                'ru' => 'Подтверждение',
                'en' => 'Confirmation'
            ],
            'LIST_FILTER_LABEL' => [
                'ru' => 'Подтверждение',
                'en' => 'Confirmation'
            ]
        ]);
        
        if ($fieldId) {
            // Добавляем значения списка
            $enumFieldId = $fieldId;
            $enumValues = [
                'n1' => [
                    'VALUE' => 'Ожидается подтверждение',
                    'DEF' => 'Y',
                    'SORT' => 100,
                    'XML_ID' => 'pending'
                ],
                'n2' => [
                    'VALUE' => 'Подтверждено',
                    'DEF' => 'N',
                    'SORT' => 200,
                    'XML_ID' => 'confirmed'
                ],
                'n3' => [
                    'VALUE' => 'Не подтверждено',
                    'DEF' => 'N',
                    'SORT' => 300,
                    'XML_ID' => 'not_confirmed'
                ]
            ];
            
            $obEnum = new \CUserFieldEnum();
            $obEnum->SetEnumValues($enumFieldId, $enumValues);
            
            // Сохраняем код поля в настройках модуля
            \Bitrix\Main\Config\Option::set('artmax.calendar', 'deal_confirmation_field', $fieldCode);
        }
    }

    /**
     * Создание пользовательского поля "Услуга" (список)
     */
    private function createDealServiceField(): void
    {
        $this->createEnumerationField([
            'fieldCode' => 'UF_CRM_CALENDAR_SERVICE',
            'optionKey' => 'deal_service_field',
            'sort' => 530,
            'labels' => [
                'ru' => 'Услуга',
                'en' => 'Service',
            ],
            'values' => [
                'n1' => [
                    'VALUE' => 'Не выбрано',
                    'DEF' => 'Y',
                    'SORT' => 100,
                    'XML_ID' => 'not_selected_service',
                ],
            ],
        ]);
    }

    /**
     * Создание пользовательского поля "Источник" (список)
     */
    private function createDealSourceField(): void
    {
        $this->createEnumerationField([
            'fieldCode' => 'UF_CRM_CALENDAR_SOURCE',
            'optionKey' => 'deal_source_field',
            'sort' => 540,
            'labels' => [
                'ru' => 'Источник',
                'en' => 'Source',
            ],
            'values' => [
                'n1' => [
                    'VALUE' => 'Не указан',
                    'DEF' => 'Y',
                    'SORT' => 100,
                    'XML_ID' => 'not_selected_source',
                ],
            ],
        ]);
    }

    /**
     * Создание пользовательского поля "Сумма" (деньги)
     */
    private function createDealAmountField(): void
    {
        if (!\Bitrix\Main\Loader::includeModule('crm')) {
            return;
        }

        $fieldCode = 'UF_CRM_CALENDAR_AMOUNT';
        $existingField = \CUserTypeEntity::GetList([], [
            'ENTITY_ID' => 'CRM_DEAL',
            'FIELD_NAME' => $fieldCode,
        ])->Fetch();

        if ($existingField) {
            \Bitrix\Main\Config\Option::set('artmax.calendar', 'deal_amount_field', $fieldCode);
            return;
        }

        $userTypeEntity = new \CUserTypeEntity();
        $settings = [
            'DEFAULT_VALUE' => '',
        ];

        $fieldId = $userTypeEntity->Add([
            'ENTITY_ID' => 'CRM_DEAL',
            'FIELD_NAME' => $fieldCode,
            'USER_TYPE_ID' => 'money',
            'SORT' => 550,
            'MULTIPLE' => 'N',
            'MANDATORY' => 'N',
            'SHOW_FILTER' => 'N',
            'SHOW_IN_LIST' => 'Y',
            'EDIT_IN_LIST' => 'Y',
            'IS_SEARCHABLE' => 'N',
            'SETTINGS' => $settings,
            'EDIT_FORM_LABEL' => [
                'ru' => 'Сумма услуги',
                'en' => 'Service Amount',
            ],
            'LIST_COLUMN_LABEL' => [
                'ru' => 'Сумма',
                'en' => 'Amount',
            ],
            'LIST_FILTER_LABEL' => [
                'ru' => 'Сумма',
                'en' => 'Amount',
            ],
        ]);

        if ($fieldId) {
            \Bitrix\Main\Config\Option::set('artmax.calendar', 'deal_amount_field', $fieldCode);
        }
    }

    /**
     * Создание пользовательского поля "Филиал" (список)
     */
    private function createDealBranchField(): void
    {
        $this->createEnumerationField([
            'fieldCode' => 'UF_CRM_CALENDAR_BRANCH',
            'optionKey' => 'deal_branch_field',
            'sort' => 560,
            'labels' => [
                'ru' => 'Филиал',
                'en' => 'Branch',
            ],
            'values' => [
                'n1' => [
                    'VALUE' => 'По умолчанию',
                    'DEF' => 'Y',
                    'SORT' => 100,
                    'XML_ID' => 'default_branch',
                ],
            ],
        ]);
    }

    /**
     * Хелпер создания пользовательского поля типа "список"
     *
     * @param array{fieldCode:string, optionKey:string, sort:int, labels:array, values:array} $params
     */
    private function createEnumerationField(array $params): void
    {
        if (!\Bitrix\Main\Loader::includeModule('crm')) {
            return;
        }

        $fieldCode = $params['fieldCode'];
        $existingField = \CUserTypeEntity::GetList([], [
            'ENTITY_ID' => 'CRM_DEAL',
            'FIELD_NAME' => $fieldCode,
        ])->Fetch();

        if ($existingField) {
            \Bitrix\Main\Config\Option::set('artmax.calendar', $params['optionKey'], $fieldCode);
            return;
        }

        $userTypeEntity = new \CUserTypeEntity();
        $fieldId = $userTypeEntity->Add([
            'ENTITY_ID' => 'CRM_DEAL',
            'FIELD_NAME' => $fieldCode,
            'USER_TYPE_ID' => 'enumeration',
            'SORT' => $params['sort'],
            'MULTIPLE' => 'N',
            'MANDATORY' => 'N',
            'SHOW_FILTER' => 'Y',
            'SHOW_IN_LIST' => 'Y',
            'EDIT_IN_LIST' => 'Y',
            'IS_SEARCHABLE' => 'N',
            'EDIT_FORM_LABEL' => $params['labels'],
            'LIST_COLUMN_LABEL' => $params['labels'],
            'LIST_FILTER_LABEL' => $params['labels'],
        ]);

        if ($fieldId) {
            if (!empty($params['values'])) {
                $obEnum = new \CUserFieldEnum();
                $obEnum->SetEnumValues($fieldId, $params['values']);
            }

            \Bitrix\Main\Config\Option::set('artmax.calendar', $params['optionKey'], $fieldCode);
        }
    }
    
    /**
     * Создание пользовательского поля "Визит" для сделки
     */
    private function createDealVisitField()
    {
        if (!\Bitrix\Main\Loader::includeModule('crm')) {
            return;
        }
        
        $fieldCode = 'UF_CRM_CALENDAR_VISIT';
        
        // Проверяем, существует ли уже поле
        $existingField = \CUserTypeEntity::GetList(
            [],
            [
                'ENTITY_ID' => 'CRM_DEAL',
                'FIELD_NAME' => $fieldCode
            ]
        )->Fetch();
        
        if ($existingField) {
            \Bitrix\Main\Config\Option::set('artmax.calendar', 'deal_visit_field', $fieldCode);
            return;
        }
        
        // Создаем новое поле типа "Список"
        $userTypeEntity = new \CUserTypeEntity();
        $fieldId = $userTypeEntity->Add([
            'ENTITY_ID' => 'CRM_DEAL',
            'FIELD_NAME' => $fieldCode,
            'USER_TYPE_ID' => 'enumeration', // Тип "Список"
            'SORT' => 520,
            'MULTIPLE' => 'N',
            'MANDATORY' => 'N',
            'SHOW_FILTER' => 'Y',
            'SHOW_IN_LIST' => 'Y',
            'EDIT_IN_LIST' => 'Y',
            'IS_SEARCHABLE' => 'N',
            'EDIT_FORM_LABEL' => [
                'ru' => 'Статус визита',
                'en' => 'Visit Status'
            ],
            'LIST_COLUMN_LABEL' => [
                'ru' => 'Визит',
                'en' => 'Visit'
            ],
            'LIST_FILTER_LABEL' => [
                'ru' => 'Визит',
                'en' => 'Visit'
            ]
        ]);
        
        if ($fieldId) {
            // Добавляем значения списка
            $enumFieldId = $fieldId;
            $enumValues = [
                'n1' => [
                    'VALUE' => 'Не указано',
                    'DEF' => 'Y',
                    'SORT' => 100,
                    'XML_ID' => 'not_specified'
                ],
                'n2' => [
                    'VALUE' => 'Клиент пришел',
                    'DEF' => 'N',
                    'SORT' => 200,
                    'XML_ID' => 'client_came'
                ],
                'n3' => [
                    'VALUE' => 'Клиент не пришел',
                    'DEF' => 'N',
                    'SORT' => 300,
                    'XML_ID' => 'client_did_not_come'
                ]
            ];
            
            $obEnum = new \CUserFieldEnum();
            $obEnum->SetEnumValues($enumFieldId, $enumValues);
            
            // Сохраняем код поля в настройках модуля
            \Bitrix\Main\Config\Option::set('artmax.calendar', 'deal_visit_field', $fieldCode);
        }
    }
    
    /**
     * Удаление пользовательского поля "Бронирование" для сделки
     */
    private function deleteDealBookingField()
    {
        if (!\Bitrix\Main\Loader::includeModule('crm')) {
            return;
        }
        
        $fieldCode = \Bitrix\Main\Config\Option::get('artmax.calendar', 'deal_booking_field', 'UF_CRM_CALENDAR_BOOKING');
        
        // Ищем поле
        $existingField = \CUserTypeEntity::GetList(
            [],
            [
                'ENTITY_ID' => 'CRM_DEAL',
                'FIELD_NAME' => $fieldCode
            ]
        )->Fetch();
        
        if ($existingField) {
            $userTypeEntity = new \CUserTypeEntity();
            $userTypeEntity->Delete($existingField['ID']);
        }
    }
    
    /**
     * Удаление пользовательского поля "Подтверждение" для сделки
     */
    private function deleteDealConfirmationField()
    {
        if (!\Bitrix\Main\Loader::includeModule('crm')) {
            return;
        }
        
        $fieldCode = \Bitrix\Main\Config\Option::get('artmax.calendar', 'deal_confirmation_field', 'UF_CRM_CALENDAR_CONFIRM');
        
        // Ищем поле
        $existingField = \CUserTypeEntity::GetList(
            [],
            [
                'ENTITY_ID' => 'CRM_DEAL',
                'FIELD_NAME' => $fieldCode
            ]
        )->Fetch();
        
        if ($existingField) {
            $userTypeEntity = new \CUserTypeEntity();
            $userTypeEntity->Delete($existingField['ID']);
        }
    }
    
    /**
     * Удаление пользовательского поля "Визит" для сделки
     */
    private function deleteDealVisitField()
    {
        if (!\Bitrix\Main\Loader::includeModule('crm')) {
            return;
        }
        
        $fieldCode = \Bitrix\Main\Config\Option::get('artmax.calendar', 'deal_visit_field', 'UF_CRM_CALENDAR_VISIT');
        
        // Ищем поле
        $existingField = \CUserTypeEntity::GetList(
            [],
            [
                'ENTITY_ID' => 'CRM_DEAL',
                'FIELD_NAME' => $fieldCode
            ]
        )->Fetch();
        
        if ($existingField) {
            $userTypeEntity = new \CUserTypeEntity();
            $userTypeEntity->Delete($existingField['ID']);
        }
    }

    private function deleteDealServiceField(): void
    {
        $this->deleteDealEnumField('deal_service_field', 'UF_CRM_CALENDAR_SERVICE');
    }

    private function deleteDealSourceField(): void
    {
        $this->deleteDealEnumField('deal_source_field', 'UF_CRM_CALENDAR_SOURCE');
    }

    private function deleteDealAmountField(): void
    {
        if (!\Bitrix\Main\Loader::includeModule('crm')) {
            return;
        }

        $fieldCode = \Bitrix\Main\Config\Option::get('artmax.calendar', 'deal_amount_field', 'UF_CRM_CALENDAR_AMOUNT');
        $existingField = \CUserTypeEntity::GetList([], [
            'ENTITY_ID' => 'CRM_DEAL',
            'FIELD_NAME' => $fieldCode,
        ])->Fetch();

        if ($existingField) {
            $userTypeEntity = new \CUserTypeEntity();
            $userTypeEntity->Delete($existingField['ID']);
        }
    }

    private function deleteDealBranchField(): void
    {
        $this->deleteDealEnumField('deal_branch_field', 'UF_CRM_CALENDAR_BRANCH');
    }

    private function deleteDealEnumField(string $optionKey, string $defaultCode): void
    {
        if (!\Bitrix\Main\Loader::includeModule('crm')) {
            return;
        }

        $fieldCode = \Bitrix\Main\Config\Option::get('artmax.calendar', $optionKey, $defaultCode);
        $existingField = \CUserTypeEntity::GetList([], [
            'ENTITY_ID' => 'CRM_DEAL',
            'FIELD_NAME' => $fieldCode,
        ])->Fetch();

        if ($existingField) {
            $userTypeEntity = new \CUserTypeEntity();
            $userTypeEntity->Delete($existingField['ID']);
        }
    }

    /**
     * Добавление филиала в список пользовательского поля "Филиал" для сделок
     */
    private function addBranchToDealFieldEnum(int $branchId, string $branchName): void
    {
        if ($branchId <= 0 || $branchName === '') {
            \CEventLog::Add([
                'SEVERITY' => 'WARNING',
                'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_BRANCH_ENUM_INVALID_PARAMS',
                'MODULE_ID' => 'artmax.calendar',
                'OBJECT_ID' => $branchId,
                'DESCRIPTION' => 'Неверные параметры для добавления филиала в enum: branchId=' . $branchId . ', branchName=' . $branchName
            ]);
            return;
        }

        if (!\Bitrix\Main\Loader::includeModule('crm')) {
            \CEventLog::Add([
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_BRANCH_ENUM_CRM_NOT_LOADED',
                'MODULE_ID' => 'artmax.calendar',
                'OBJECT_ID' => $branchId,
                'DESCRIPTION' => 'Модуль CRM не загружен при добавлении филиала в enum'
            ]);
            return;
        }

        $fieldCode = \Bitrix\Main\Config\Option::get('artmax.calendar', 'deal_branch_field', 'UF_CRM_CALENDAR_BRANCH');
        if (!$fieldCode) {
            \CEventLog::Add([
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_BRANCH_ENUM_NO_FIELD_CODE',
                'MODULE_ID' => 'artmax.calendar',
                'OBJECT_ID' => $branchId,
                'DESCRIPTION' => 'Не найден код поля для добавления филиала в enum'
            ]);
            return;
        }

        $field = \CUserTypeEntity::GetList([], [
            'ENTITY_ID' => 'CRM_DEAL',
            'FIELD_NAME' => $fieldCode,
        ])->Fetch();

        if (!$field) {
            \CEventLog::Add([
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_BRANCH_ENUM_FIELD_NOT_FOUND',
                'MODULE_ID' => 'artmax.calendar',
                'OBJECT_ID' => $branchId,
                'DESCRIPTION' => 'Поле ' . $fieldCode . ' не найдено для добавления филиала в enum'
            ]);
            return;
        }

        $xmlId = 'branch_' . $branchId;
        
        $enum = new \CUserFieldEnum();
        $existingValues = [];
        $alreadyExists = false;

        $rsEnum = $enum->GetList(['SORT' => 'ASC'], ['USER_FIELD_ID' => $field['ID']]);
        while ($item = $rsEnum->Fetch()) {
            $existingValues[$item['ID']] = [
                'VALUE' => $item['VALUE'],
                'DEF' => $item['DEF'],
                'SORT' => $item['SORT'],
                'XML_ID' => $item['XML_ID'],
            ];

            if ($item['XML_ID'] === $xmlId || (int)$item['XML_ID'] === $branchId) {
                $alreadyExists = true;
            }
        }

        if ($alreadyExists) {
            \CEventLog::Add([
                'SEVERITY' => 'INFO',
                'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_BRANCH_ENUM_ALREADY_EXISTS',
                'MODULE_ID' => 'artmax.calendar',
                'OBJECT_ID' => $branchId,
                'DESCRIPTION' => 'Филиал "' . $branchName . '" (ID: ' . $branchId . ') уже существует в enum поля ' . $fieldCode
            ]);
            return;
        }

        // Используем ту же логику, что и в рабочем методе
        $existingValues['n' . $branchId] = [
            'VALUE' => $branchName,
            'DEF' => 'N',
            'SORT' => 100 + count($existingValues) * 10,
            'XML_ID' => $xmlId,
        ];

        $result = $enum->SetEnumValues($field['ID'], $existingValues);
        
        if ($result) {
            \CEventLog::Add([
                'SEVERITY' => 'INFO',
                'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_BRANCH_ENUM_ADDED',
                'MODULE_ID' => 'artmax.calendar',
                'OBJECT_ID' => $branchId,
                'DESCRIPTION' => 'Филиал "' . $branchName . '" (ID: ' . $branchId . ') успешно добавлен в enum поля ' . $fieldCode
            ]);
        } else {
            \CEventLog::Add([
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_BRANCH_ENUM_ADD_FAILED',
                'MODULE_ID' => 'artmax.calendar',
                'OBJECT_ID' => $branchId,
                'DESCRIPTION' => 'Ошибка добавления филиала "' . $branchName . '" (ID: ' . $branchId . ') в enum поля ' . $fieldCode
            ]);
        }
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
        
        return true;
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
                    'OBJECT_ID' => 0,
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
                    'OBJECT_ID' => 0,
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
            $_SERVER["DOCUMENT_ROOT"]."/bitrix/admin/",
            $this->MODULE_ID . '_'
        );

        // Явно проверяем и создаем файл настроек, если он не был создан
        $settingsSourceFile = $_SERVER["DOCUMENT_ROOT"]."/local/modules/".$this->MODULE_ID."/install/admin/artmax_calendar_settings.php";
        $settingsLinkFile = $_SERVER["DOCUMENT_ROOT"]."/bitrix/admin/".$this->MODULE_ID."_artmax_calendar_settings.php";
        
        if (file_exists($settingsSourceFile) && !file_exists($settingsLinkFile)) {
            $linkContent = '<?php require($_SERVER["DOCUMENT_ROOT"] . "/local/modules/' . $this->MODULE_ID . '/install/admin/artmax_calendar_settings.php"); ?>';
            $result = file_put_contents($settingsLinkFile, $linkContent);
            if ($result !== false) {
                file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Явно создан линк настроек: $settingsLinkFile\n", FILE_APPEND);
            } else {
                file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Ошибка явного создания линка настроек: $settingsLinkFile\n", FILE_APPEND);
            }
        }

        // Копируем компоненты из install/components в local/components
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


        // AJAX endpoint теперь копируется вместе с остальными файлами компонента
        
        return true;
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

        // Удаляем файлы, скопированные напрямую в /bitrix/admin/
        $adminFilesToDelete = [
            'artmax_calendar_menu.php',
            'artmax_calendar_register.php',
            'artmax_calendar_classes.php'
        ];
        
        foreach ($adminFilesToDelete as $fileName) {
            $filePath = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin/' . $fileName;
            if (file_exists($filePath)) {
                unlink($filePath);
                file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Файл $fileName удален: $filePath\n", FILE_APPEND);
            }
        }

    }

    private function createAdminLinks($fromDir, $toDir, $prefix = '', $exclude = ['.', '..', 'menu.php'])
    {
        if (is_dir($fromDir)) {
            // Используем более надежный способ чтения директории
            $files = scandir($fromDir);
            if ($files === false) {
                file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Не удалось прочитать содержимое папки $fromDir\n", FILE_APPEND);
                return;
            }

            foreach ($files as $item) {
                if (in_array($item, $exclude)) {
                    continue;
                }

                // Пропускаем директории
                $itemPath = $fromDir . '/' . $item;
                if (is_dir($itemPath)) {
                    continue;
                }

                // Пропускаем не PHP файлы
                if (pathinfo($item, PATHINFO_EXTENSION) !== 'php') {
                    continue;
                }

                $linkFile = $toDir . $prefix . $item;
                $linkContent = '<?php require($_SERVER["DOCUMENT_ROOT"] . "/local/modules/' . $this->MODULE_ID . '/install/admin/' . $item . '"); ?>';
                
                // Создаем директорию, если её нет
                $linkDir = dirname($linkFile);
                if (!is_dir($linkDir)) {
                    if (!mkdir($linkDir, 0755, true)) {
                        file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Не удалось создать директорию $linkDir\n", FILE_APPEND);
                        continue;
                    }
                }

                $result = file_put_contents($linkFile, $linkContent);
                if ($result === false) {
                    file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Не удалось создать $linkFile (ошибка: " . error_get_last()['message'] . ")\n", FILE_APPEND);
                } else {
                    file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Создан линк: $linkFile (размер: $result байт)\n", FILE_APPEND);
                }
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

    /**
     * Рекурсивно копирует все содержимое директории
     */
    private function copyDirectoryContents($source, $destination)
    {
        if (!is_dir($source)) {
            return false;
        }

        // Нормализуем пути: убираем лишние слэши
        $source = rtrim($source, '/\\') . DIRECTORY_SEPARATOR;
        $destination = rtrim($destination, '/\\') . DIRECTORY_SEPARATOR;

        if (!is_dir($destination)) {
            if (!mkdir($destination, 0775, true)) {
                file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Не удалось создать папку $destination\n", FILE_APPEND);
                return false;
            }
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            // Используем правильное объединение путей
            $subPath = $iterator->getSubPathName();
            $target = $destination . str_replace('/', DIRECTORY_SEPARATOR, $subPath);
            
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    if (!mkdir($target, 0775, true)) {
                        file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Не удалось создать папку $target\n", FILE_APPEND);
                    }
                }
            } else {
                if (!copy($item, $target)) {
                    file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Не удалось скопировать файл {$item->getPathname()} в $target\n", FILE_APPEND);
                } else {
                    file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Скопирован файл: {$item->getPathname()} -> $target\n", FILE_APPEND);
                }
            }
        }

        return true;
    }
    
    /**
     * Возвращает HTML для страницы настроек модуля
     * Это метод вызывается Bitrix для отображения ссылки на настройки в разделе "Настройки модулей"
     * Метод должен возвращать массив с ключом 'HTML' или строку
     */
    public function GetModuleRight()
    {
        // Проверяем, что модуль установлен
        if (!\Bitrix\Main\ModuleManager::isModuleInstalled($this->MODULE_ID)) {
            return '';
        }
        
        // Возвращаем ссылку на настройки модуля
        // Bitrix автоматически проверит права доступа при переходе по ссылке
        $settingsUrl = '/bitrix/admin/artmax.calendar_artmax_calendar_settings.php?lang=' . LANGUAGE_ID;
        return '<a href="' . $settingsUrl . '">Настройки модуля календаря</a>';
    }

    /**
     * Создание таблиц для системы прав доступа
     */
    private function createPermissionsTables()
    {
        $connection = \Bitrix\Main\Application::getConnection();
        
        // Таблица информации о группах и пользователях
        $sqlUserGroups = "
        CREATE TABLE IF NOT EXISTS artmax_calendar_user_groups (
            ID INT AUTO_INCREMENT PRIMARY KEY,
            GROUP_ID INT NOT NULL COMMENT 'ID группы в Bitrix (b_group.ID)',
            GROUP_NAME VARCHAR(255) NOT NULL COMMENT 'Название группы',
            USER_ID INT DEFAULT NULL COMMENT 'ID пользователя, если право назначено пользователю',
            CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UPDATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_group_id (GROUP_ID),
            INDEX idx_user_id (USER_ID),
            INDEX idx_group_user (GROUP_ID, USER_ID)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        // Таблица перечня доступных прав
        $sqlPermissions = "
        CREATE TABLE IF NOT EXISTS artmax_calendar_permissions (
            ID INT AUTO_INCREMENT PRIMARY KEY,
            CODE VARCHAR(100) NOT NULL UNIQUE COMMENT 'Код права (например: create_event, edit_event)',
            NAME VARCHAR(255) NOT NULL COMMENT 'Название права на русском',
            DESCRIPTION TEXT DEFAULT NULL COMMENT 'Описание права',
            CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_code (CODE)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        // Таблица прав доступа
        $sqlAccessRights = "
        CREATE TABLE IF NOT EXISTS artmax_calendar_access_rights (
            ID INT AUTO_INCREMENT PRIMARY KEY,
            PERMISSION_ID INT NOT NULL COMMENT 'ID права из artmax_calendar_permissions',
            ENTITY_TYPE ENUM('user', 'group') NOT NULL COMMENT 'Тип сущности: user или group',
            ENTITY_ID INT NOT NULL COMMENT 'ID пользователя или группы',
            CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_permission_entity (PERMISSION_ID, ENTITY_TYPE, ENTITY_ID),
            INDEX idx_entity_type (ENTITY_TYPE),
            INDEX idx_entity_id (ENTITY_ID),
            INDEX idx_permission (PERMISSION_ID),
            FOREIGN KEY (PERMISSION_ID) REFERENCES artmax_calendar_permissions(ID) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        // Таблица связей групп календаря с группами Bitrix (для наследования пользователей)
        $sqlGroupLinks = "
        CREATE TABLE IF NOT EXISTS artmax_calendar_group_links (
            ID INT AUTO_INCREMENT PRIMARY KEY,
            CALENDAR_GROUP_ID INT NOT NULL COMMENT 'ID группы календаря (из artmax_calendar_user_groups.GROUP_ID)',
            BITRIX_GROUP_ID INT NOT NULL COMMENT 'ID группы Bitrix (из b_group.ID)',
            CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_calendar_bitrix (CALENDAR_GROUP_ID, BITRIX_GROUP_ID),
            INDEX idx_calendar_group (CALENDAR_GROUP_ID),
            INDEX idx_bitrix_group (BITRIX_GROUP_ID)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        try {
            $connection->query($sqlUserGroups);
            $connection->query($sqlPermissions);
            $connection->query($sqlAccessRights);
            $connection->query($sqlGroupLinks);
        } catch (\Exception $e) {
            \CEventLog::Add([
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_CREATE_PERMISSIONS_TABLES_ERROR',
                'MODULE_ID' => 'artmax.calendar',
                'OBJECT_ID' => 0,
                'DESCRIPTION' => 'Ошибка создания таблиц прав доступа: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Создание групп пользователей в Bitrix
     */
    private function createUserGroups()
    {
        $groups = [
            [
                'NAME' => 'Артмакс.Календарь | Администраторы',
                'DESCRIPTION' => 'Группа администраторов календаря клиники. Полный доступ ко всем функциям.',
                'C_SORT' => 100
            ]
        ];
        
        $connection = \Bitrix\Main\Application::getConnection();
        
        foreach ($groups as $groupData) {
            $groupId = null;
            
            // Проверяем, существует ли уже группа с таким названием
            // Используем более надежный способ поиска - перебираем все группы
            $rsGroups = \CGroup::GetList($by = 'ID', $order = 'ASC');
            $groupFound = false;
            
            if ($rsGroups) {
                while ($group = $rsGroups->Fetch()) {
                    if (isset($group['NAME']) && $group['NAME'] === $groupData['NAME']) {
                        $groupId = (int)$group['ID'];
                        $groupFound = true;
                        break;
                    }
                }
            }
            
            // Также проверяем по STRING_ID, если группа еще не найдена
            if (!$groupFound) {
                $stringId = 'artmax_calendar_' . mb_strtolower(str_replace([' ', '|', '-'], ['_', '_', '_'], $groupData['NAME']));
                $stringId = preg_replace('/[^a-z0-9_]/', '', $stringId);
                
                $rsGroups = \CGroup::GetList($by = 'ID', $order = 'ASC');
                if ($rsGroups) {
                    while ($group = $rsGroups->Fetch()) {
                        if (isset($group['STRING_ID']) && $group['STRING_ID'] === $stringId) {
                            $groupId = (int)$group['ID'];
                            $groupFound = true;
                            break;
                        }
                    }
                }
            }
            
            if (!$groupFound) {
                // Создаем новую группу
                $groupObj = new \CGroup();
                
                // Формируем STRING_ID для группы (должен быть уникальным и без спецсимволов)
                $stringId = 'artmax_calendar_' . mb_strtolower(str_replace([' ', '|', '-'], ['_', '_', '_'], $groupData['NAME']));
                $stringId = preg_replace('/[^a-z0-9_]/', '', $stringId);
                
                $fields = [
                    'ACTIVE' => 'Y',
                    'C_SORT' => $groupData['C_SORT'],
                    'NAME' => $groupData['NAME'],
                    'DESCRIPTION' => $groupData['DESCRIPTION'],
                    'STRING_ID' => $stringId
                ];
                
                $groupId = $groupObj->Add($fields);
                
                if (!$groupId || !is_numeric($groupId) || (int)$groupId <= 0) {
                    $errorMessage = $groupObj->LAST_ERROR ?: 'Неизвестная ошибка';
                    \CEventLog::Add([
                        'SEVERITY' => 'ERROR',
                        'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_CREATE_GROUP_ERROR',
                        'MODULE_ID' => 'artmax.calendar',
                        'OBJECT_ID' => 0,
                        'DESCRIPTION' => 'Ошибка создания группы: ' . $groupData['NAME'] . '. ' . $errorMessage
                    ]);
                    error_log('Ошибка создания группы "' . $groupData['NAME'] . '": ' . $errorMessage);
                    // Выбрасываем исключение, чтобы установка не продолжилась без группы
                    throw new \Exception('Не удалось создать группу "' . $groupData['NAME'] . '": ' . $errorMessage);
                }
                
                $groupId = (int)$groupId;
                
                // Проверяем, что группа действительно создана в базе данных
                $checkGroup = \CGroup::GetByID($groupId);
                if (!$checkGroup || !$checkGroup->Fetch()) {
                    $errorMessage = 'Группа не найдена в базе данных после создания (ID: ' . $groupId . ')';
                    \CEventLog::Add([
                        'SEVERITY' => 'ERROR',
                        'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_GROUP_NOT_FOUND_AFTER_CREATE',
                        'MODULE_ID' => 'artmax.calendar',
                        'OBJECT_ID' => $groupId,
                        'DESCRIPTION' => 'Ошибка проверки созданной группы: ' . $groupData['NAME'] . '. ' . $errorMessage
                    ]);
                    throw new \Exception('Не удалось проверить созданную группу "' . $groupData['NAME'] . '": ' . $errorMessage);
                }
                
                // Логируем успешное создание группы
                \CEventLog::Add([
                    'SEVERITY' => 'INFO',
                    'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_GROUP_CREATED',
                    'MODULE_ID' => 'artmax.calendar',
                    'OBJECT_ID' => $groupId,
                    'DESCRIPTION' => 'Создана группа: ' . $groupData['NAME'] . ' (ID: ' . $groupId . ', STRING_ID: ' . $stringId . ')'
                ]);
            } else {
                // Логируем, что группа уже существует
                \CEventLog::Add([
                    'SEVERITY' => 'INFO',
                    'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_GROUP_EXISTS',
                    'MODULE_ID' => 'artmax.calendar',
                    'OBJECT_ID' => $groupId,
                    'DESCRIPTION' => 'Группа уже существует: ' . $groupData['NAME'] . ' (ID: ' . $groupId . ')'
                ]);
            }
            
            // Сохраняем информацию о группе в таблицу
            if ($groupId) {
                try {
                    $sqlCheck = "SELECT ID FROM artmax_calendar_user_groups WHERE GROUP_ID = " . (int)$groupId . " AND USER_ID IS NULL";
                    $result = $connection->query($sqlCheck);
                    
                    if ($result->getSelectedRowsCount() == 0) {
                        $sqlInsert = "
                        INSERT INTO artmax_calendar_user_groups (GROUP_ID, GROUP_NAME, USER_ID) 
                        VALUES (" . (int)$groupId . ", '" . $connection->getSqlHelper()->forSql($groupData['NAME']) . "', NULL)
                        ";
                        $connection->query($sqlInsert);
                    } else {
                        // Обновляем название группы, если оно изменилось
                        $sqlUpdate = "
                        UPDATE artmax_calendar_user_groups 
                        SET GROUP_NAME = '" . $connection->getSqlHelper()->forSql($groupData['NAME']) . "'
                        WHERE GROUP_ID = " . (int)$groupId . " AND USER_ID IS NULL
                        ";
                        $connection->query($sqlUpdate);
                    }
                } catch (\Exception $e) {
                    \CEventLog::Add([
                        'SEVERITY' => 'ERROR',
                        'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_SAVE_GROUP_INFO_ERROR',
                        'MODULE_ID' => 'artmax.calendar',
                        'OBJECT_ID' => $groupId ?: 0,
                        'DESCRIPTION' => 'Ошибка сохранения информации о группе: ' . $groupData['NAME'] . '. ' . $e->getMessage()
                    ]);
                }
            }
        }
    }

    /**
     * Создание базовых прав доступа
     */
    private function createDefaultPermissions()
    {
        $permissions = [
            // Просмотр
            ['CODE' => 'calendar.view', 'NAME' => 'Просмотр записей', 'DESCRIPTION' => 'Право на просмотр своих записей в календаре'],
            ['CODE' => 'calendar.view_all', 'NAME' => 'Просмотр всех записей', 'DESCRIPTION' => 'Право на просмотр записей всех врачей'],
            // Создание
            ['CODE' => 'calendar.create', 'NAME' => 'Создание записи', 'DESCRIPTION' => 'Право на создание новых записей в календаре'],
            // Редактирование
            ['CODE' => 'calendar.edit_own', 'NAME' => 'Редактирование своих записей', 'DESCRIPTION' => 'Право на редактирование только своих записей'],
            ['CODE' => 'calendar.edit_all', 'NAME' => 'Редактирование всех записей', 'DESCRIPTION' => 'Право на редактирование записей всех врачей'],
            // Редактирование названия
            ['CODE' => 'calendar.edit_title_own', 'NAME' => 'Редактирование названия записи (своих)', 'DESCRIPTION' => 'Право на редактирование названия только своих записей'],
            ['CODE' => 'calendar.edit_title_all', 'NAME' => 'Редактирование названия записи (всех)', 'DESCRIPTION' => 'Право на редактирование названия у любых записей'],
            // Перемещение
            ['CODE' => 'calendar.move', 'NAME' => 'Перемещение записи', 'DESCRIPTION' => 'Право на перемещение записей в календаре'],
            // Подтверждение и отмена
            ['CODE' => 'calendar.confirm', 'NAME' => 'Подтверждение записи', 'DESCRIPTION' => 'Право на подтверждение записей'],
            ['CODE' => 'calendar.cancel', 'NAME' => 'Отмена записи', 'DESCRIPTION' => 'Право на отмену записи и возврат в расписание. Владелец может отменять свои записи без этого права'],
            // Смена врача
            ['CODE' => 'calendar.change_employee', 'NAME' => 'Смена ответственного врача', 'DESCRIPTION' => 'Право на смену ответственного врача в записи'],
            // Удаление
            ['CODE' => 'calendar.delete_own', 'NAME' => 'Удаление своих записей', 'DESCRIPTION' => 'Право на удаление только своих записей'],
            ['CODE' => 'calendar.delete_all', 'NAME' => 'Удаление всех записей', 'DESCRIPTION' => 'Право на удаление записей всех врачей'],
            // Управление
            ['CODE' => 'calendar.manage_groups', 'NAME' => 'Управление группами и правами', 'DESCRIPTION' => 'Право на создание групп пользователей и управление правами доступа'],
            ['CODE' => 'calendar.manage_schedule', 'NAME' => 'Управление расписанием', 'DESCRIPTION' => 'Право на управление расписанием врачей'],
            ['CODE' => 'calendar.manage_branches', 'NAME' => 'Управление филиалами', 'DESCRIPTION' => 'Право на управление филиалами клиники'],
            // Заметки к записям
            ['CODE' => 'calendar.edit_own_notes', 'NAME' => 'Редактирование заметок своих записей', 'DESCRIPTION' => 'Право на редактирование заметок в своих записях календаря'],
            ['CODE' => 'calendar.edit_others_notes', 'NAME' => 'Редактирование заметок чужих записей', 'DESCRIPTION' => 'Право на редактирование заметок в чужих записях календаря'],
            ['CODE' => 'calendar.manage_contact', 'NAME' => 'Создание и привязка контакта к записи', 'DESCRIPTION' => 'Право на создание нового контакта в CRM и привязку/изменение контакта в событии календаря'],
            ['CODE' => 'calendar.manage_deal', 'NAME' => 'Создание и привязка сделки к записи', 'DESCRIPTION' => 'Право на создание сделки в CRM, привязку сделки к событию календаря и просмотр деталей сделки'],
            ['CODE' => 'calendar.set_visit_status', 'NAME' => 'Установка статуса визита', 'DESCRIPTION' => 'Право на установку статуса визита в записи (Клиент пришел / Клиент не пришел / Не указано)']
        ];
        
        $connection = \Bitrix\Main\Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();
        
        foreach ($permissions as $permission) {
            try {
                // Проверяем, существует ли уже право
                $sqlCheck = "SELECT ID FROM artmax_calendar_permissions WHERE CODE = '" . $sqlHelper->forSql($permission['CODE']) . "'";
                $result = $connection->query($sqlCheck);
                
                if ($result->getSelectedRowsCount() == 0) {
                    $sqlInsert = "
                    INSERT INTO artmax_calendar_permissions (CODE, NAME, DESCRIPTION) 
                    VALUES (
                        '" . $sqlHelper->forSql($permission['CODE']) . "',
                        '" . $sqlHelper->forSql($permission['NAME']) . "',
                        '" . $sqlHelper->forSql($permission['DESCRIPTION']) . "'
                    )
                    ";
                    $connection->query($sqlInsert);
                }
            } catch (\Exception $e) {
                \CEventLog::Add([
                    'SEVERITY' => 'ERROR',
                    'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_CREATE_PERMISSION_ERROR',
                    'MODULE_ID' => 'artmax.calendar',
                    'OBJECT_ID' => 0,
                    'DESCRIPTION' => 'Ошибка создания права: ' . $permission['CODE'] . '. ' . $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Назначение прав группам по умолчанию
     */
    private function assignDefaultPermissions()
    {
        $connection = \Bitrix\Main\Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();
        
        // Получаем ID группы администраторов
        $adminGroupId = $this->getGroupIdByName('Артмакс.Календарь | Администраторы');
        
        if (!$adminGroupId) {
            \CEventLog::Add([
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_GROUPS_NOT_FOUND',
                'MODULE_ID' => 'artmax.calendar',
                'OBJECT_ID' => 0,
                'DESCRIPTION' => 'Не удалось найти группу администраторов для назначения прав'
            ]);
            return;
        }
        
        // Администраторы - все права календаря
        $adminPermissions = [
            'calendar.view', 'calendar.view_all', 'calendar.create',
            'calendar.edit_own', 'calendar.edit_all',
            'calendar.edit_title_own', 'calendar.edit_title_all',
            'calendar.edit_own_notes', 'calendar.edit_others_notes',
            'calendar.move', 'calendar.confirm', 'calendar.cancel', 'calendar.change_employee',
            'calendar.delete_own', 'calendar.delete_all',
            'calendar.manage_groups', 'calendar.manage_schedule', 'calendar.manage_branches', 'calendar.manage_contact', 'calendar.manage_deal', 'calendar.set_visit_status'
        ];
        
        // Назначаем права администраторам
        $this->assignPermissionsToGroup($adminGroupId, $adminPermissions);
    }

    /**
     * Получение ID группы по названию
     */
    private function getGroupIdByName($groupName)
    {
        $group = \CGroup::GetList(
            $by = 'ID',
            $order = 'ASC',
            ['NAME' => $groupName]
        )->Fetch();
        
        return $group ? (int)$group['ID'] : null;
    }

    /**
     * Назначение прав группе
     */
    private function assignPermissionsToGroup($groupId, $permissionCodes)
    {
        $connection = \Bitrix\Main\Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();
        
        foreach ($permissionCodes as $permissionCode) {
            try {
                // Получаем ID права
                $sqlPermission = "SELECT ID FROM artmax_calendar_permissions WHERE CODE = '" . $sqlHelper->forSql($permissionCode) . "'";
                $result = $connection->query($sqlPermission);
                $permission = $result->fetch();
                
                if (!$permission) {
                    continue;
                }
                
                $permissionId = (int)$permission['ID'];
                
                // Проверяем, не назначено ли уже это право
                $sqlCheck = "
                SELECT ID FROM artmax_calendar_access_rights 
                WHERE PERMISSION_ID = " . $permissionId . " 
                AND ENTITY_TYPE = 'group' 
                AND ENTITY_ID = " . (int)$groupId . "
                ";
                $checkResult = $connection->query($sqlCheck);
                
                if ($checkResult->getSelectedRowsCount() == 0) {
                    // Назначаем право
                    $sqlInsert = "
                    INSERT INTO artmax_calendar_access_rights (PERMISSION_ID, ENTITY_TYPE, ENTITY_ID) 
                    VALUES (" . $permissionId . ", 'group', " . (int)$groupId . ")
                    ";
                    $connection->query($sqlInsert);
                }
            } catch (\Exception $e) {
                \CEventLog::Add([
                    'SEVERITY' => 'ERROR',
                    'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_ASSIGN_PERMISSION_ERROR',
                    'MODULE_ID' => 'artmax.calendar',
                    'OBJECT_ID' => $groupId,
                    'DESCRIPTION' => 'Ошибка назначения права ' . $permissionCode . ' группе ' . $groupId . ': ' . $e->getMessage()
                ]);
            }
        }
    }


} 