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
            CREATED_AT datetime DEFAULT CURRENT_TIMESTAMP,
            UPDATED_AT datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (ID)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
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
        $connection->query($sqlEventJournal);
        
        // Создаем первый филиал по умолчанию
        $sqlDefaultBranch = "
        INSERT INTO artmax_calendar_branches (NAME, ADDRESS, PHONE, EMAIL, TIMEZONE_NAME) 
        VALUES ('Филиал - 1', '', '', '', 'Europe/Moscow')
        ";
        $connection->query($sqlDefaultBranch);
        
        // Получаем ID созданного филиала (более надежный способ)
        $result = $connection->query("SELECT ID FROM artmax_calendar_branches WHERE NAME = 'Филиал - 1' ORDER BY ID DESC LIMIT 1");
        $row = $result->fetch();
        $defaultBranchId = $row ? (int)$row['ID'] : null;
        
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
                    'DESCRIPTION' => 'Ошибка добавления филиала в enum: ' . $e->getMessage()
                ]);
            }
        }
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

    public function UnInstallDB()
    {
        // Удаление таблиц базы данных
        $connection = \Bitrix\Main\Application::getConnection();
        $connection->query("DROP TABLE IF EXISTS artmax_calendar_event_journal");
        $connection->query("DROP TABLE IF EXISTS artmax_calendar_branch_employees");
        $connection->query("DROP TABLE IF EXISTS artmax_calendar_events");
        $connection->query("DROP TABLE IF EXISTS artmax_calendar_branches");
        
        // Удаляем пользовательское поле бронирования
        $this->deleteDealBookingField();
        
        // Удаляем пользовательские поля
        $this->deleteDealConfirmationField();
        $this->deleteDealVisitField();
        $this->deleteDealServiceField();
        $this->deleteDealSourceField();
        $this->deleteDealAmountField();
        $this->deleteDealBranchField();
        
        // Удаляем настройки модуля
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
                'DESCRIPTION' => 'Неверные параметры для добавления филиала в enum: branchId=' . $branchId . ', branchName=' . $branchName
            ]);
            return;
        }

        if (!\Bitrix\Main\Loader::includeModule('crm')) {
            \CEventLog::Add([
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_BRANCH_ENUM_CRM_NOT_LOADED',
                'MODULE_ID' => 'artmax.calendar',
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
                'DESCRIPTION' => 'Филиал "' . $branchName . '" (ID: ' . $branchId . ') успешно добавлен в enum поля ' . $fieldCode
            ]);
        } else {
            \CEventLog::Add([
                'SEVERITY' => 'ERROR',
                'AUDIT_TYPE_ID' => 'ARTMAX_CALENDAR_BRANCH_ENUM_ADD_FAILED',
                'MODULE_ID' => 'artmax.calendar',
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

        // Копируем актуальные файлы компонента calendar из рабочей папки
        $calendarFrom = $_SERVER['DOCUMENT_ROOT'] . '/local/components/artmax/calendar/';
        $calendarTo = $_SERVER['DOCUMENT_ROOT'] . '/local/components/artmax/calendar/';
        if (is_dir($calendarFrom)) {
            // Создаем папку, если её нет
            if (!is_dir($calendarTo)) {
                mkdir($calendarTo, 0775, true);
            }
            
            // Копируем все файлы из папки calendar
            $this->copyDirectoryContents($calendarFrom, $calendarTo);
            file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Актуальные файлы календаря скопированы из $calendarFrom в $calendarTo\n", FILE_APPEND);
        } else {
            file_put_contents($_SERVER["DOCUMENT_ROOT"]."/copy_error.log", "Папка календаря не найдена: $calendarFrom\n", FILE_APPEND);
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

    /**
     * Рекурсивно копирует все содержимое директории
     */
    private function copyDirectoryContents($source, $destination)
    {
        if (!is_dir($source)) {
            return false;
        }

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
            $target = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            
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


} 