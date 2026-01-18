<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();


use Bitrix\Main\Localization\Loc;
use Bitrix\UI\Toolbar\Facade\Toolbar;
use Bitrix\UI\Toolbar\ButtonLocation;

Loc::loadMessages(__FILE__);

class ArtmaxCalendarComponent extends CBitrixComponent{

    public function executeComponent()
    {
        // Подключаем необходимые скрипты и стили
        $this->includeAssets();

        
        // Проверяем, является ли это AJAX запросом
        if ($this->isAjaxRequest()) {
            $this->handleAjaxRequest();
            // Принудительно завершаем выполнение для AJAX запросов
            die();
        } else {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "\n=== NOT AJAX - NORMAL PAGE LOAD ===\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "Time: " . date('Y-m-d H:i:s') . "\n", 
                FILE_APPEND | LOCK_EX);
        }

        // Проверяем существование модуля
        if (!CModule::IncludeModule('artmax.calendar')) {
            ShowError('Модуль artmax.calendar не установлен');
            return;
        }

        // Получаем параметры
        $branchId = (int)($this->arParams['BRANCH_ID'] ?? 1);
        $eventsCount = (int)($this->arParams['EVENTS_COUNT'] ?? 20);
        $showForm = $this->arParams['SHOW_FORM'] === 'Y';
        
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "Branch ID: " . $branchId . "\n", 
            FILE_APPEND | LOCK_EX);

        try {
            // Получаем информацию о филиале
            $branchObj = new \Artmax\Calendar\Branch();
            $branch = $branchObj->getBranch($branchId);

            if (!$branch) {
                ShowError('Филиал не найден');
                return;
            }

            // Получаем события для филиала с ограничениями по дате
            $calendarObj = new \Artmax\Calendar\Calendar();
            
            // Получаем текущий месяц и год из URL параметра или текущую дату
            $currentDate = isset($_GET['date']) ? new DateTime($_GET['date']) : new DateTime();
            $currentYear = $currentDate->format('Y');
            $currentMonth = $currentDate->format('n');
            
            // Отладочная информация о выбранной дате
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                "PHP COMPONENT: URL date param = " . ($_GET['date'] ?? 'not set') . "\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                "PHP COMPONENT: Using date = " . $currentDate->format('Y-m-d') . "\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                "PHP COMPONENT: currentYear = $currentYear, currentMonth = $currentMonth\n", 
                FILE_APPEND | LOCK_EX);
            
            // Формируем диапазон дат для календарной сетки (включая дни предыдущего и следующего месяца)
            $firstDay = new DateTime("$currentYear-$currentMonth-01");
            $firstDayOfWeek = $firstDay->format('N'); // 1 = понедельник, 7 = воскресенье
            
            // Начинаем с понедельника предыдущей недели
            $startDate = clone $firstDay;
            $startDate->modify('-' . ($firstDayOfWeek - 1) . ' days');
            
            // Заканчиваем через 6 недель (42 дня)
            $endDate = clone $startDate;
            $endDate->modify('+41 days');
            
            $dateFrom = $startDate->format('Y-m-d');
            $dateTo = $endDate->format('Y-m-d');
            
            // Определяем employeeId для фильтрации
            // Пользователи с правом calendar.view_others видят все записи, остальные - только свои
            $employeeId = null;
            global $USER;
            $hasViewOthersPermission = false;
            
            if ($USER && $USER->IsAuthorized()) {
                // Проверяем право на просмотр чужих записей
                if ($USER->IsAdmin()) {
                    $hasViewOthersPermission = true;
                } else {
                    try {
                        $permissionsObj = new \Artmax\Calendar\Permissions();
                        $hasViewOthersPermission = $permissionsObj->hasPermission($USER->GetID(), 'calendar.view_others');
                    } catch (\Exception $e) {
                        // Если ошибка проверки прав, используем безопасный вариант - только свои записи
                        $hasViewOthersPermission = false;
                    }
                }
                
                // Если есть право на просмотр чужих, проверяем параметр employee_id из GET (для переключателя)
                if ($hasViewOthersPermission) {
                    if (isset($_GET['employee_id'])) {
                        $employeeIdParam = $_GET['employee_id'];
                        // Если параметр равен '0' или 'all', показываем все записи
                        if ($employeeIdParam === '0' || $employeeIdParam === 'all' || $employeeIdParam === '') {
                            $employeeId = null;
                        } else {
                            $employeeId = (int)$employeeIdParam;
                        }
                    } else {
                        // Если параметра нет: для админов "Все записи", для врачей с правом view_others "Мои записи"
                        if ($USER->IsAdmin()) {
                            $employeeId = null; // Админы видят все записи по умолчанию
                        } else {
                            $employeeId = $USER->GetID(); // Врачи с правом view_others видят свои записи по умолчанию
                        }
                    }
                } else {
                    // Без права на просмотр чужих - показываем только свои записи
                    $employeeId = $USER->GetID();
                }
            }
            
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "\n=== STATIC LOAD USER CHECK ===\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "Current user ID: " . ($USER ? $USER->GetID() : 'none') . "\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "IsAuthorized: " . ($USER && $USER->IsAuthorized() ? 'yes' : 'no') . "\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "IsAdmin: " . ($USER && $USER->IsAdmin() ? 'yes' : 'no') . "\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "Filter employeeId: " . ($employeeId ?? 'null') . "\n", 
                FILE_APPEND | LOCK_EX);
            
            $events = $calendarObj->getEventsByBranch($branchId, $dateFrom, $dateTo, null, null, $employeeId);
            
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "Events loaded: " . count($events) . "\n", 
                FILE_APPEND | LOCK_EX);
            
            // Загружаем данные контактов для каждого события
            foreach ($events as &$event) {
                if (!empty($event['CONTACT_ENTITY_ID'])) {
                    $contactData = $this->getContactFromCRM($event['CONTACT_ENTITY_ID']);
                    if ($contactData) {
                        $event['CONTACT_NAME'] = $contactData['name'] ?? '';
                        $event['CONTACT_PHONE'] = $contactData['phone'] ?? '';
                    }
                }
            }
            unset($event); // Разрываем ссылку после foreach
            
            // Отладочная информация
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                "=== STATIC LOAD START ===\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                "STATIC LOAD: dateFrom=$dateFrom, dateTo=$dateTo, events count=" . count($events) . "\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                "STATIC LOAD: currentYear=$currentYear, currentMonth=$currentMonth\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                "STATIC LOAD: startDate=" . $startDate->format('Y-m-d') . ", endDate=" . $endDate->format('Y-m-d') . "\n", 
                FILE_APPEND | LOCK_EX);

            // Группируем события по датам для отображения в календаре
            $eventsByDate = [];
            foreach ($events as $event) {
                $convertedDate = $this->convertRussianDateToStandard($event['DATE_FROM']);
                $dateKey = date('Y-m-d', strtotime($convertedDate));
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                    "STATIC LOAD: event ID={$event['ID']}, original DATE_FROM={$event['DATE_FROM']}, converted={$convertedDate}, dateKey={$dateKey}\n", 
                    FILE_APPEND | LOCK_EX);
                if (!isset($eventsByDate[$dateKey])) {
                    $eventsByDate[$dateKey] = [];
                }
                $eventsByDate[$dateKey][] = $event;
            }
            
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                "STATIC LOAD: eventsByDate keys=" . implode(', ', array_keys($eventsByDate)) . "\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                "=== STATIC LOAD END ===\n", 
                FILE_APPEND | LOCK_EX);

            // Получаем список всех филиалов для навигации
            $allBranches = $branchObj->getBranches();
        } catch (Exception $e) {
            ShowError('Ошибка при инициализации календаря: ' . $e->getMessage());
            return;
        }

        // Формируем данные для шаблона
        global $USER;
        
        // Проверяем право на просмотр чужих записей для шаблона
        $hasViewOthersPermission = false;
        $hasCreatePermission = false;
        $hasManageGroupsPermission = false;
        $hasManageBranchesPermission = false;
        $hasManageSchedulePermission = false;
        $hasDeletePermission = false;
        $hasChangeEmployeePermission = false;
        $hasEditTitlePermission = false;
        $hasEditOthersNotesPermission = false;
        $hasEditPermission = false;
        $hasMovePermission = false;
        $hasConfirmPermission = false;
        if ($USER && $USER->IsAuthorized()) {
            if ($USER->IsAdmin()) {
                $hasViewOthersPermission = true;
                $hasCreatePermission = true;
                $hasManageGroupsPermission = true;
                $hasManageBranchesPermission = true;
                $hasManageSchedulePermission = true;
                $hasDeletePermission = true;
                $hasChangeEmployeePermission = true;
                $hasEditTitlePermission = true;
                $hasEditOthersNotesPermission = true;
                $hasEditPermission = true;
                $hasMovePermission = true;
                $hasConfirmPermission = true;
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                    "HAS_VIEW_OTHERS_PERMISSION: User is admin, setting to true\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                    "HAS_CREATE_PERMISSION: User is admin, setting to true\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                    "HAS_MANAGE_GROUPS_PERMISSION: User is admin, setting to true\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                    "HAS_MANAGE_BRANCHES_PERMISSION: User is admin, setting to true\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                    "HAS_MANAGE_SCHEDULE_PERMISSION: User is admin, setting to true\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                    "HAS_DELETE_PERMISSION: User is admin, setting to true\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                    "HAS_CHANGE_EMPLOYEE_PERMISSION: User is admin, setting to true\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                    "HAS_EDIT_TITLE_PERMISSION: User is admin, setting to true\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                    "HAS_EDIT_OTHERS_NOTES_PERMISSION: User is admin, setting to true\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                    "HAS_EDIT_PERMISSION: User is admin, setting to true\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                    "HAS_MOVE_PERMISSION: User is admin, setting to true\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                    "HAS_CONFIRM_PERMISSION: User is admin, setting to true\n", 
                    FILE_APPEND | LOCK_EX);
            } else {
                try {
                    $permissionsObj = new \Artmax\Calendar\Permissions();
                    $userId = $USER->GetID();
                    $hasViewOthersPermission = $permissionsObj->hasPermission($userId, 'calendar.view_others');
                    $hasCreatePermission = $permissionsObj->hasPermission($userId, 'calendar.create');
                    $hasManageGroupsPermission = $permissionsObj->hasPermission($userId, 'calendar.manage_groups');
                    $hasManageBranchesPermission = $permissionsObj->hasPermission($userId, 'calendar.manage_branches');
                    $hasManageSchedulePermission = $permissionsObj->hasPermission($userId, 'calendar.manage_schedule');
                    $hasDeletePermission = $permissionsObj->hasPermission($userId, 'calendar.delete');
                    $hasChangeEmployeePermission = $permissionsObj->hasPermission($userId, 'calendar.change_employee');
                    $hasEditTitlePermission = $permissionsObj->hasPermission($userId, 'calendar.edit_title');
                    $hasEditOthersNotesPermission = $permissionsObj->hasPermission($userId, 'calendar.edit_others_notes');
                    $hasEditPermission = $permissionsObj->hasPermission($userId, 'calendar.edit');
                    $hasMovePermission = $permissionsObj->hasPermission($userId, 'calendar.move');
                    $hasConfirmPermission = $permissionsObj->hasPermission($userId, 'calendar.confirm');
                    
                    // Отладочная информация
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                        "HAS_VIEW_OTHERS_PERMISSION: User ID = " . $userId . ", hasPermission result = " . ($hasViewOthersPermission ? 'true' : 'false') . "\n", 
                        FILE_APPEND | LOCK_EX);
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                        "HAS_CREATE_PERMISSION: User ID = " . $userId . ", hasPermission result = " . ($hasCreatePermission ? 'true' : 'false') . "\n", 
                        FILE_APPEND | LOCK_EX);
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                        "HAS_MANAGE_GROUPS_PERMISSION: User ID = " . $userId . ", hasPermission result = " . ($hasManageGroupsPermission ? 'true' : 'false') . "\n", 
                        FILE_APPEND | LOCK_EX);
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                        "HAS_MANAGE_BRANCHES_PERMISSION: User ID = " . $userId . ", hasPermission result = " . ($hasManageBranchesPermission ? 'true' : 'false') . "\n", 
                        FILE_APPEND | LOCK_EX);
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                        "HAS_MANAGE_SCHEDULE_PERMISSION: User ID = " . $userId . ", hasPermission result = " . ($hasManageSchedulePermission ? 'true' : 'false') . "\n", 
                        FILE_APPEND | LOCK_EX);
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                        "HAS_DELETE_PERMISSION: User ID = " . $userId . ", hasPermission result = " . ($hasDeletePermission ? 'true' : 'false') . "\n", 
                        FILE_APPEND | LOCK_EX);
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                        "HAS_CHANGE_EMPLOYEE_PERMISSION: User ID = " . $userId . ", hasPermission result = " . ($hasChangeEmployeePermission ? 'true' : 'false') . "\n", 
                        FILE_APPEND | LOCK_EX);
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                        "HAS_EDIT_TITLE_PERMISSION: User ID = " . $userId . ", hasPermission result = " . ($hasEditTitlePermission ? 'true' : 'false') . "\n", 
                        FILE_APPEND | LOCK_EX);
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                        "HAS_EDIT_OTHERS_NOTES_PERMISSION: User ID = " . $userId . ", hasPermission result = " . ($hasEditOthersNotesPermission ? 'true' : 'false') . "\n", 
                        FILE_APPEND | LOCK_EX);
                    
                    // Дополнительная отладка: проверяем группы пользователя
                    $userGroups = \CUser::GetUserGroup($userId);
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                        "HAS_VIEW_OTHERS_PERMISSION: User groups = " . implode(', ', $userGroups) . "\n", 
                        FILE_APPEND | LOCK_EX);
                } catch (\Exception $e) {
                    $hasViewOthersPermission = false;
                    $hasCreatePermission = false;
                    $hasManageGroupsPermission = false;
                    $hasManageBranchesPermission = false;
                    $hasManageSchedulePermission = false;
                    $hasDeletePermission = false;
                    $hasChangeEmployeePermission = false;
                    $hasEditTitlePermission = false;
                    $hasEditOthersNotesPermission = false;
                    $hasEditPermission = false;
                    $hasMovePermission = false;
                    $hasConfirmPermission = false;
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                        "HAS_VIEW_OTHERS_PERMISSION: Exception = " . $e->getMessage() . "\n", 
                        FILE_APPEND | LOCK_EX);
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                        "HAS_CREATE_PERMISSION: Exception = " . $e->getMessage() . "\n", 
                        FILE_APPEND | LOCK_EX);
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                        "HAS_MANAGE_GROUPS_PERMISSION: Exception = " . $e->getMessage() . "\n", 
                        FILE_APPEND | LOCK_EX);
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                        "HAS_MANAGE_BRANCHES_PERMISSION: Exception = " . $e->getMessage() . "\n", 
                        FILE_APPEND | LOCK_EX);
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                        "HAS_MANAGE_SCHEDULE_PERMISSION: Exception = " . $e->getMessage() . "\n", 
                        FILE_APPEND | LOCK_EX);
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                        "HAS_DELETE_PERMISSION: Exception = " . $e->getMessage() . "\n", 
                        FILE_APPEND | LOCK_EX);
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                        "HAS_CHANGE_EMPLOYEE_PERMISSION: Exception = " . $e->getMessage() . "\n", 
                        FILE_APPEND | LOCK_EX);
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                        "HAS_EDIT_PERMISSION: Exception = " . $e->getMessage() . "\n", 
                        FILE_APPEND | LOCK_EX);
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                        "HAS_MOVE_PERMISSION: Exception = " . $e->getMessage() . "\n", 
                        FILE_APPEND | LOCK_EX);
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                        "HAS_CONFIRM_PERMISSION: Exception = " . $e->getMessage() . "\n", 
                        FILE_APPEND | LOCK_EX);
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                        "HAS_EDIT_TITLE_PERMISSION: Exception = " . $e->getMessage() . "\n", 
                        FILE_APPEND | LOCK_EX);
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                        "HAS_EDIT_OTHERS_NOTES_PERMISSION: Exception = " . $e->getMessage() . "\n", 
                        FILE_APPEND | LOCK_EX);
                }
            }
        } else {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                "HAS_VIEW_OTHERS_PERMISSION: User not authorized or not set\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                "HAS_CREATE_PERMISSION: User not authorized or not set\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                "HAS_MANAGE_GROUPS_PERMISSION: User not authorized or not set\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                "HAS_MANAGE_BRANCHES_PERMISSION: User not authorized or not set\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                "HAS_MANAGE_SCHEDULE_PERMISSION: User not authorized or not set\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                "HAS_DELETE_PERMISSION: User not authorized or not set\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                "HAS_CHANGE_EMPLOYEE_PERMISSION: User not authorized or not set\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                "HAS_EDIT_TITLE_PERMISSION: User not authorized or not set\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                "HAS_EDIT_OTHERS_NOTES_PERMISSION: User not authorized or not set\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                "HAS_EDIT_PERMISSION: User not authorized or not set\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                "HAS_MOVE_PERMISSION: User not authorized or not set\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                "HAS_CONFIRM_PERMISSION: User not authorized or not set\n", 
                FILE_APPEND | LOCK_EX);
        }
        
        $this->arResult = [
            'BRANCH' => $branch,
            'EVENTS' => $events,
            'EVENTS_BY_DATE' => $eventsByDate,
            'ALL_BRANCHES' => $allBranches,
            'SHOW_FORM' => $showForm,
            'CURRENT_USER_ID' => $USER ? $USER->GetID() : 0,
            'IS_ADMIN' => $USER && $USER->IsAdmin(),
            'CAN_ADD_EVENTS' => $USER ? $USER->IsAuthorized() : false,
            'HAS_VIEW_OTHERS_PERMISSION' => $hasViewOthersPermission,
            'HAS_CREATE_PERMISSION' => $hasCreatePermission,
            'HAS_MANAGE_GROUPS_PERMISSION' => $hasManageGroupsPermission,
            'HAS_MANAGE_BRANCHES_PERMISSION' => $hasManageBranchesPermission,
            'HAS_MANAGE_SCHEDULE_PERMISSION' => $hasManageSchedulePermission,
            'HAS_DELETE_PERMISSION' => $hasDeletePermission,
            'HAS_CHANGE_EMPLOYEE_PERMISSION' => $hasChangeEmployeePermission,
            'HAS_EDIT_TITLE_PERMISSION' => $hasEditTitlePermission,
            'HAS_EDIT_OTHERS_NOTES_PERMISSION' => $hasEditOthersNotesPermission,
            'HAS_EDIT_PERMISSION' => $hasEditPermission,
            'HAS_MOVE_PERMISSION' => $hasMovePermission,
            'HAS_CONFIRM_PERMISSION' => $hasConfirmPermission,
        ];

        // Добавляем панельные кнопки (кнопка "Настройки" показывается всем с правами, "Создать" - только админам)
        if ($USER && $USER->IsAuthorized()) {
            $this->addPanelButtons();
            
            // Управляем звездочкой "Добавить в избранное" (только для админов)
            if ($USER->IsAdmin()) {
                $this->manageFavoriteStar();
                
                // Также добавляем данные для отображения кнопок в шаблоне
                $this->arResult['SHOW_BRANCH_BUTTONS'] = true;
                $this->arResult['BRANCH_BUTTONS'] = [
                    'create_menu' => [
                        'text' => 'Создать',
                        'title' => 'Создать новый элемент',
                        'icon' => '➕',
                        'menu' => [
                            [
                                'text' => 'Создать расписание',
                                'title' => 'Создать новое расписание',
                                'icon' => '📅',
                                'onclick' => 'openScheduleModal()'
                            ],
                            [
                                'text' => 'Создать филиал',
                                'title' => 'Создать новый филиал клиники',
                                'icon' => '🏢',
                                'onclick' => 'openAddBranchModal()'
                            ]
                        ]
                    ],
                    'branch_settings' => [
                        'text' => '',
                        'title' => 'Настроить параметры текущего филиала',
                        'icon' => '⚙️',
                        'onclick' => 'openBranchModal()'
                    ]
                ];
            } else {
                $this->arResult['SHOW_BRANCH_BUTTONS'] = false;
            }
        }
        
        // Добавляем навигацию по месяцам для всех пользователей
        $this->addMonthNavigation();

        // Подключаем шаблон
        $this->includeComponentTemplate();
    }

    /**
     * Добавляет кнопки в тулбар Bitrix24 используя современный API
     * Использует Bitrix\UI\Toolbar\Facade\Toolbar согласно документации
     */
    private function addPanelButtons()
    {
        // Проверяем, доступен ли современный API тулбара
        if (class_exists('\Bitrix\UI\Toolbar\Facade\Toolbar')) {
            $this->addModernToolbarButtons();
        } else {
            // Fallback на старый API для совместимости
            $this->addLegacyPanelButtons();
        }
    }
    
    /**
     * Добавляет кнопки через современный API тулбара Bitrix24
     */
    private function addModernToolbarButtons()
    {
        global $USER;
        
        // Кнопка "Создать" с выпадающим меню - показываем всем с соответствующими правами
        $createMenuItems = [];
        
        // Проверяем право на управление расписанием
        $hasManageSchedulePermission = $this->arResult['HAS_MANAGE_SCHEDULE_PERMISSION'] ?? false;
        if ($hasManageSchedulePermission) {
            $createMenuItems[] = [
                'text' => 'Создать расписание',
                'onclick' => new \Bitrix\UI\Buttons\JsHandler('openScheduleModal')
            ];
        }
        
        // Проверяем право на управление филиалами
        $hasManageBranchesPermission = $this->arResult['HAS_MANAGE_BRANCHES_PERMISSION'] ?? false;
        if ($hasManageBranchesPermission) {
            $createMenuItems[] = [
                'text' => 'Создать филиал',
                'onclick' => new \Bitrix\UI\Buttons\JsHandler('openAddBranchModal')
            ];
        }
        
        // Показываем кнопку "Создать" только если есть хотя бы один пункт меню
        if (!empty($createMenuItems)) {
            Toolbar::addButton([
                'text' => 'Создать',
                'title' => 'Создать новый элемент',
                'color' => \Bitrix\UI\Buttons\Color::SUCCESS,
                'dataset' => [
                    'toolbar-collapsed-icon' => \Bitrix\UI\Buttons\Icon::ADD
                ],
                'menu' => [
                    'items' => $createMenuItems
                ]
            ], ButtonLocation::AFTER_TITLE);
        }

        // Кнопка "Настройки" с выпадающим меню - показываем всем с соответствующими правами
        $menuItems = [];
        
        // Проверяем право на управление филиалами
        $hasManageBranchesPermission = $this->arResult['HAS_MANAGE_BRANCHES_PERMISSION'] ?? false;
        if ($hasManageBranchesPermission) {
            $menuItems[] = [
                'text' => 'Настройки филиала',
                'title' => 'Настроить параметры текущего филиала',
                'onclick' => new \Bitrix\UI\Buttons\JsHandler('openBranchModal')
            ];
        }
        
        // Проверяем право на управление группами
        $hasManageGroupsPermission = $this->arResult['HAS_MANAGE_GROUPS_PERMISSION'] ?? false;
        if ($hasManageGroupsPermission) {
            // Добавляем JavaScript функцию для редиректа, если её еще нет
            global $APPLICATION;
            $langId = defined('LANGUAGE_ID') ? LANGUAGE_ID : (defined('LANG') ? LANG : 'ru');
            $jsFunction = '
                <script>
                if (typeof window.redirectToPermissionsPage === "undefined") {
                    window.redirectToPermissionsPage = function() {
                        window.location.href = "/bitrix/admin/artmax.calendar_artmax_calendar_permissions.php?lang=' . $langId . '";
                    };
                }
                </script>
            ';
            $APPLICATION->AddHeadString($jsFunction);
            
            $menuItems[] = [
                'text' => 'Группы и права',
                'title' => 'Управление группами пользователей и правами доступа',
                'onclick' => new \Bitrix\UI\Buttons\JsHandler('redirectToPermissionsPage')
            ];
        }
        
        // Показываем кнопку "Настройки" только если есть хотя бы один пункт меню
        if (!empty($menuItems)) {
            Toolbar::addButton([
                'text' => '',
                'title' => 'Настройки',
                'icon' => \Bitrix\UI\Buttons\Icon::SETTING,
                'dataset' => [
                    'toolbar-collapsed-icon' => \Bitrix\UI\Buttons\Icon::SETTING
                ],
                'menu' => [
                    'items' => $menuItems
                ],
                'classList' => ['calendar-settings-btn']
            ], ButtonLocation::AFTER_TITLE);
        }
    }

    /**
     * Добавляет навигацию по месяцам (для всех пользователей)
     */
    private function addMonthNavigation()
    {
        // Блок навигации по месяцам в pagetitle-below через отложенные функции
        global $APPLICATION;
        
        // Получаем текущую дату
        $currentDate = $this->arParams['DATE'] ?? date('Y-m-d');
        $currentMonth = date('n', strtotime($currentDate));
        $currentYear = date('Y', strtotime($currentDate));
        
        // Массив названий месяцев
        $monthNames = [
            1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель',
            5 => 'Май', 6 => 'Июнь', 7 => 'Июль', 8 => 'Август',
            9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь'
        ];
        
        $currentMonthName = $monthNames[$currentMonth];
        
        // Генерируем опции для select
        $monthOptions = '';
        foreach ($monthNames as $num => $name) {
            $selected = ($num == $currentMonth) ? 'selected' : '';
            $monthOptions .= "<option value=\"{$num}\" {$selected}>{$name}</option>";
        }
        
        $APPLICATION->AddViewContent('below_pagetitle', '
            <div class="calendar-month-navigation" style="
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 15px;
                margin: 10px 0;
                padding: 0 20px;
            ">
                <div class="nav-left" style="display: flex; align-items: center; gap: 8px;">
                <button type="button" class="ui-btn ui-btn-icon-angle-down ui-btn-empty nav-btn prev-month" 
                        onclick="previousMonth(); return false;">
                </button>
                
                <div class="current-month" style="position: relative;">
                    <select id="monthSelect" 
                            onchange="changeMonth(this.value)"  
                            style="
                                background: transparent;
                                border: none;
                                color: white;
                                font-size: 12px;
                                font-weight: 500;
                                text-align: center;
                                cursor: pointer;
                                outline: none;
                                border-bottom: 1px dotted rgba(255, 255, 255, 0.6);
                                padding: 2px 20px 2px 20px;
                                appearance: none;
                                background-image: url(\'data:image/svg+xml;charset=US-ASCII,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 4 5"><path fill="white" d="M2 0L0 2h4zm0 5L0 3h4z"/></svg>\');
                                background-repeat: no-repeat;
                                background-position: right 6px center;
                                background-size: 10px;
                                min-width: 100px;
                            ">
                        ' . $monthOptions . '
                    </select>
                </div>
                
                <button type="button" class="ui-btn ui-btn-icon-angle-down ui-btn-empty nav-btn next-month"  
                        onclick="nextMonth(); return false;">
                </button>
                
                <button class="ui-btn ui-btn-empty nav-btn today-btn" 
                        onclick="goToToday()"
                        style="
                            background: rgba(255, 255, 255, 0.1);
                            border: 1px solid rgba(255, 255, 255, 0.2);
                            border-radius: 3px;
                            padding: 2px 8px;
                            color: white;
                            cursor: pointer;
                            font-size: 10px;
                            transition: all 0.3s ease;
                            backdrop-filter: blur(10px);
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            min-width: 50px;
                            height: 20px;
                        "
                        onmouseover="this.style.background=\'rgba(255, 255, 255, 0.2)\'"
                        onmouseout="this.style.background=\'rgba(255, 255, 255, 0.1)\'">
                    Сегодня
                </button>
                </div>
                
                <div class="nav-right" style="display: flex; align-items: center;">' . 
                (($this->arResult['HAS_DELETE_PERMISSION'] ?? false) ? '
                    <button class="ui-btn ui-btn-empty nav-btn clear-all-btn" 
                            onclick="clearAllEvents()"
                            title="Удалить все события"
                            style="
                                background: rgba(220, 53, 69, 0.1);
                                border: 1px solid rgba(220, 53, 69, 0.2);
                                border-radius: 3px;
                                padding: 4px 8px;
                                color: white;
                                cursor: pointer;
                                font-size: 10px;
                                transition: all 0.3s ease;
                                backdrop-filter: blur(10px);
                                display: inline-flex;
                                align-items: center;
                                justify-content: center;
                                min-width: 60px;
                                height: 20px;
                            "
                            onmouseover="this.style.background=\'rgba(220, 53, 69, 0.2)\'"
                            onmouseout="this.style.background=\'rgba(220, 53, 69, 0.1)\'">
                        🗑️ Удалить все
                    </button>' : '') . '
                </div>
            </div>
        ');
    }
    
    /**
     * Fallback метод для старых версий Bitrix24
     */
    private function addLegacyPanelButtons()
    {
        global $APPLICATION;
        
        // Кнопка "Создать" с выпадающим меню
        $APPLICATION->AddPanelButton([
            "TEXT" => "Создать",
            "TITLE" => "Создать новый элемент",
            "ICON" => "bx-icon-plus",
            "SORT" => 10,
            "HINT" => "Создать новый элемент",
            "DATA_TOOLBAR_COLLAPSED_ICON" => "bx-icon-plus",
            "MENU" => [
                [
                    "TEXT" => "Создать расписание",
                    "TITLE" => "Создать новое расписание",
                    "LINK" => "javascript:openScheduleModal();"
                ],
                [
                    "TEXT" => "Создать филиал",
                    "TITLE" => "Создать новый филиал клиники",
                    "LINK" => "javascript:openAddBranchModal();"
                ]
            ]
        ]);

        // Кнопка "Настройки" с выпадающим меню (только иконка)
        global $USER;
        $menuItems = [
            [
                "TEXT" => "Настройки филиала",
                "TITLE" => "Настроить параметры текущего филиала",
                "LINK" => "javascript:openBranchModal();"
            ]
        ];
        
        // Проверяем право на управление группами и добавляем пункт "Группы и права"
        $hasManageGroupsPermission = false;
        try {
            if (class_exists('\Artmax\Calendar\Permissions')) {
                $permissionsObj = new \Artmax\Calendar\Permissions();
                $hasManageGroupsPermission = $permissionsObj->hasPermission($USER->GetID(), 'calendar.manage_groups') || $USER->IsAdmin();
            }
        } catch (\Exception $e) {
            // Игнорируем ошибки
        }
        
        if ($hasManageGroupsPermission) {
            $jsFunction = '
                <script>
                if (typeof window.redirectToPermissionsPage === "undefined") {
                    window.redirectToPermissionsPage = function() {
                        window.location.href = "/bitrix/admin/artmax.calendar_artmax_calendar_permissions.php?lang=' . LANGUAGE_ID . '";
                    };
                }
                </script>
            ';
            $APPLICATION->AddHeadString($jsFunction);
            
            $menuItems[] = [
                "TEXT" => "Группы и права",
                "TITLE" => "Управление группами пользователей и правами доступа",
                "LINK" => "javascript:redirectToPermissionsPage();"
            ];
        }
        
        $APPLICATION->AddPanelButton([
            "TEXT" => "",
            "TITLE" => "Настройки",       
            "ICON" => "bx-icon-settings",  
            "SORT" => 20,
            "HINT" => "Настройки календаря",
            "MENU" => $menuItems
        ]);
    }
    
    /**
     * Управляет звездочкой "Добавить в избранное" в тулбаре
     */
    private function manageFavoriteStar()
    {
        // Проверяем, доступен ли современный API тулбара
        if (class_exists('\Bitrix\UI\Toolbar\Facade\Toolbar')) {
            // Показываем звездочку для календаря филиала
            Toolbar::addFavoriteStar();
        }
    }

    /**
     * Проверка, является ли запрос AJAX
     */
    private function isAjaxRequest()
    {
        // Отладочная информация в файл
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'POST_action' => $_POST['action'] ?? 'NOT SET',
            'HTTP_X_REQUESTED_WITH' => $_SERVER['HTTP_X_REQUESTED_WITH'] ?? 'NOT SET',
            'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'NOT SET',
            'POST_data' => $_POST
        ];
        
        $isAjax = isset($_POST['action']) || 
                  (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                   strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        
        return $isAjax;
    }

    /**
     * Обработка AJAX запросов
     */
    private function handleAjaxRequest()
    {
        
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'addEvent':
                
                // Поддерживаем оба варианта: branchId (camelCase) и branch_id (snake_case)
                // Приоритет: branch_id, затем branchId, затем значение по умолчанию
                $branchId = null;
                if (isset($_POST['branch_id']) && $_POST['branch_id'] !== '' && $_POST['branch_id'] !== '0') {
                    $branchId = (int)$_POST['branch_id'];
                } elseif (isset($_POST['branchId']) && $_POST['branchId'] !== '' && $_POST['branchId'] !== '0') {
                    $branchId = (int)$_POST['branchId'];
                } else {
                    $branchId = 1;
                }
                
                $result = $this->addEventAction(
                    $_POST['title'] ?? '',
                    $_POST['description'] ?? '',
                    $_POST['dateFrom'] ?? '',
                    $_POST['dateTo'] ?? '',
                    $branchId,
                    $_POST['eventColor'] ?? '#3498db',
                    isset($_POST['employee_id']) ? (int)$_POST['employee_id'] : null
                );
                break;
                
            case 'addSchedule':
                // Правильно обрабатываем массив weekdays
                $weekdays = [];
                if (isset($_POST['weekdays']) && is_array($_POST['weekdays'])) {
                    $weekdays = array_map('intval', $_POST['weekdays']);
                }
                
                $result = $this->addScheduleAction(
                    $_POST['title'] ?? '',
                    $_POST['date'] ?? '',
                    $_POST['time'] ?? '',
                    $_POST['employee_id'] ?? null,
                    $_POST['repeat'] === 'on' || $_POST['repeat'] === 'true',
                    $_POST['frequency'] ?? null,
                    $weekdays,
                    $_POST['repeatEnd'] ?? 'never',
                    !empty($_POST['repeatCount']) ? (int)$_POST['repeatCount'] : null,
                    !empty($_POST['repeatEndDate']) ? $_POST['repeatEndDate'] : null,
                    $_POST['eventColor'] ?? '#3498db'
                );
                break;
                
            case 'deleteEvent':
                $result = $this->deleteEventAction(
                    (int)($_POST['eventId'] ?? 0)
                );
                break;
                
            case 'getEvent':
                $result = $this->getEventAction(
                    (int)($_POST['eventId'] ?? 0)
                );
                break;
                
            case 'updateEvent':
                $result = $this->updateEventAction(
                    (int)($_POST['eventId'] ?? 0),
                    $_POST['title'] ?? '',
                    $_POST['description'] ?? '',
                    $_POST['dateFrom'] ?? '',
                    $_POST['dateTo'] ?? '',
                    $_POST['eventColor'] ?? '#3498db',
                    (int)($_POST['branchId'] ?? 1)
                );
                break;
                
            case 'getEvents':
                $result = $this->getEventsAction(
                    (int)($_POST['branchId'] ?? 1),
                    $_POST['dateFrom'] ?? null,
                    $_POST['dateTo'] ?? null
                );
                break;
                
            case 'searchClients':
                $result = $this->searchClientsAction(
                    $_POST['query'] ?? '',
                    $_POST['type'] ?? 'contact'
                );
                break;
                
            case 'getBranches':
                $result = $this->getBranchesAction();
                break;
                
            case 'getBranchEmployees':
                $result = $this->getBranchEmployeesAction(
                    (int)($_POST['branchId'] ?? 1)
                );
                break;
                
            case 'moveEvent':
                $result = $this->moveEventAction(
                    (int)($_POST['eventId'] ?? 0),
                    (int)($_POST['branchId'] ?? 1),
                    (int)($_POST['employeeId'] ?? 0),
                    $_POST['dateFrom'] ?? '',
                    $_POST['dateTo'] ?? ''
                );
                break;
                
            default:
                $result = ['success' => false, 'error' => 'Неизвестное действие'];
        }

        
        header('Content-Type: application/json');
        
        echo json_encode($result);
        
        exit;
        die();
    }



    /**
     * Добавление события
     */
    public function addEventAction($title, $description, $dateFrom, $dateTo, $branchId, $eventColor = '#3498db', $employeeId = null)
    {
        global $USER;
        if (!$USER || !$USER->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            
            if (!CModule::IncludeModule('artmax.calendar')) {
                return ['success' => false, 'error' => 'Модуль artmax.calendar не установлен'];
            }
            
            $calendarObj = new \Artmax\Calendar\Calendar();
            $userId = $USER->GetID();

            // Проверяем доступность времени для врача в конкретном филиале
            if (!$calendarObj->isTimeAvailableForDoctor($dateFrom, $dateTo, $employeeId, null, $branchId)) {
                return ['success' => false, 'error' => 'Время уже занято для выбранного врача в этом филиале'];
            }
            
            $eventId = $calendarObj->addEvent($title, $description, $dateFrom, $dateTo, $userId, $branchId, $eventColor, $employeeId);

            if ($eventId) {
                return ['success' => true, 'eventId' => $eventId];
            } else {
                return ['success' => false, 'error' => 'Ошибка добавления события'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Удаление события
     */
    public function deleteEventAction($eventId)
    {
        global $USER;
        if (!$USER || !$USER->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            if (!CModule::IncludeModule('artmax.calendar')) {
                return ['success' => false, 'error' => 'Модуль artmax.calendar не установлен'];
            }
            
            $calendarObj = new \Artmax\Calendar\Calendar();
            $event = $calendarObj->getEvent($eventId);

            if (!$event) {
                return ['success' => false, 'error' => 'Событие не найдено'];
            }

            // Проверяем права на удаление (только автор события)
            if ($event['USER_ID'] != $USER->GetID()) {
                return ['success' => false, 'error' => 'Нет прав на удаление'];
            }

            $result = $calendarObj->deleteEvent($eventId);

            if ($result) {
                return ['success' => true];
            } else {
                return ['success' => false, 'error' => 'Ошибка удаления события'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Получение событий
     */
    public function getEventsAction($branchId, $dateFrom = null, $dateTo = null)
    {
        try {
            if (!CModule::IncludeModule('artmax.calendar')) {
                return ['success' => false, 'error' => 'Модуль artmax.calendar не установлен'];
            }
            
            // Определяем employeeId для фильтрации
            // Пользователи с правом calendar.view_others видят все записи, остальные - только свои
            $employeeId = null;
            global $USER;
            $hasViewOthersPermission = false;
            
            if ($USER && $USER->IsAuthorized()) {
                // Проверяем право на просмотр чужих записей
                if ($USER->IsAdmin()) {
                    $hasViewOthersPermission = true;
                } else {
                    try {
                        $permissionsObj = new \Artmax\Calendar\Permissions();
                        $hasViewOthersPermission = $permissionsObj->hasPermission($USER->GetID(), 'calendar.view_others');
                    } catch (\Exception $e) {
                        // Если ошибка проверки прав, используем безопасный вариант - только свои записи
                        $hasViewOthersPermission = false;
                    }
                }
                
                // Если есть право на просмотр чужих, проверяем параметр employee_id из POST (для переключателя)
                if ($hasViewOthersPermission) {
                    if (isset($_POST['employee_id'])) {
                        $employeeIdParam = $_POST['employee_id'];
                        // Если параметр равен '0' или 'all', показываем все записи
                        if ($employeeIdParam === '0' || $employeeIdParam === 'all' || $employeeIdParam === '') {
                            $employeeId = null;
                        } else {
                            $employeeId = (int)$employeeIdParam;
                        }
                    } else {
                        // Если параметра нет: для админов "Все записи", для врачей с правом view_others "Мои записи"
                        if ($USER->IsAdmin()) {
                            $employeeId = null; // Админы видят все записи по умолчанию
                        } else {
                            $employeeId = $USER->GetID(); // Врачи с правом view_others видят свои записи по умолчанию
                        }
                    }
                } else {
                    // Без права на просмотр чужих - показываем только свои записи
                    $employeeId = $USER->GetID();
                }
            }
            
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                "AJAX getEventsAction: Current user ID=" . ($USER ? $USER->GetID() : 'none') . ", IsAdmin=" . ($USER && $USER->IsAdmin() ? 'yes' : 'no') . ", HasViewOthersPermission=" . ($hasViewOthersPermission ? 'yes' : 'no') . ", Filter employeeId=" . ($employeeId ?? 'null') . "\n", 
                FILE_APPEND | LOCK_EX);
            
            $calendarObj = new \Artmax\Calendar\Calendar();
            $events = $calendarObj->getEventsByBranch($branchId, $dateFrom, $dateTo, null, null, $employeeId);
            
            // Загружаем данные контактов для каждого события
            foreach ($events as &$event) {
                if (!empty($event['CONTACT_ENTITY_ID'])) {
                    $contactData = $this->getContactFromCRM($event['CONTACT_ENTITY_ID']);
                    if ($contactData) {
                        $event['CONTACT_NAME'] = $contactData['name'] ?? '';
                        $event['CONTACT_PHONE'] = $contactData['phone'] ?? '';
                    }
                }
            }
            unset($event); // Разрываем ссылку после foreach

            return ['success' => true, 'events' => $events];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Добавление расписания
     * 
     * @param array $params - Массив параметров:
     *   - title (string) - Название события
     *   - date (string) - Дата события (YYYY-MM-DD)
     *   - time (string) - Время события (HH:MM)
     *   - employee_id (int|null) - ID врача
     *   - branch_id (int) - ID филиала (по умолчанию 1)
     *   - repeat (bool) - Повторяющееся ли событие
     *   - frequency (string|null) - Частота повторения (daily, weekly, monthly)
     *   - weekdays (array) - Дни недели для еженедельного повторения
     *   - repeat_end (string) - Тип окончания повторения (never, count, date)
     *   - repeat_count (int|null) - Количество повторений
     *   - repeat_end_date (string|null) - Дата окончания повторений (YYYY-MM-DD)
     *   - event_color (string) - Цвет события (hex, например #3498db)
     *   - exclude_weekends (bool) - Исключать ли выходные дни
     *   - exclude_holidays (bool) - Исключать ли праздничные дни
     *   - include_end_date (bool) - Включать ли конечную дату в расписание
     * 
     * ПРИМЕР ПРАВИЛЬНОГО ВЫЗОВА:
     * $component->addScheduleAction([
     *     'title' => 'Название',
     *     'date' => '2025-10-14',
     *     'time' => '15:30',
     *     'employee_id' => 1,
     *     'branch_id' => 2,
     *     'repeat' => true,
     *     'frequency' => 'daily',
     *     'weekdays' => [],
     *     'repeat_end' => 'date',
     *     'repeat_count' => null,
     *     'repeat_end_date' => '2025-10-21',
     *     'event_color' => '#f39c12',
     *     'exclude_weekends' => false,
     *     'exclude_holidays' => false,
     *     'include_end_date' => false
     * ]);
     */
    public function addScheduleAction($params)
    {
        global $USER;
        // Извлекаем параметры из массива с значениями по умолчанию
        $title = $params['title'] ?? '';
        $date = $params['date'] ?? '';
        $time = $params['time'] ?? '';
        $duration = $params['duration'] ?? 30; // Длительность в минутах, по умолчанию 30
        $employeeId = $params['employee_id'] ?? null;
        $branchId = $params['branch_id'] ?? 1;
        $repeat = $params['repeat'] ?? false;
        $frequency = $params['frequency'] ?? null;
        $weekdays = $params['weekdays'] ?? [];
        $repeatEnd = $params['repeat_end'] ?? 'never';
        $repeatCount = $params['repeat_count'] ?? null;
        $repeatEndDate = $params['repeat_end_date'] ?? null;
        $eventColor = $params['event_color'] ?? '#3498db';
        $excludeWeekends = $params['exclude_weekends'] ?? false;
        $excludeHolidays = $params['exclude_holidays'] ?? false;
        $includeEndDate = $params['include_end_date'] ?? true;

        // Логируем полученные параметры для отладки
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "addScheduleAction: repeat = " . ($repeat ? 'true' : 'false') . ", frequency = " . ($frequency ?? 'null') . ", weekdays = " . json_encode($weekdays) . ", repeatCount = " . ($repeatCount ?? 'null') . ", repeatEnd = " . ($repeatEnd ?? 'null') . ", includeEndDate = " . ($includeEndDate ? 'true' : 'false') . ", duration = " . $duration . " minutes\n", 
            FILE_APPEND | LOCK_EX);
        
        if (!$USER || !$USER->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            if (!CModule::IncludeModule('artmax.calendar')) {
                return ['success' => false, 'error' => 'Модуль artmax.calendar не установлен'];
            }
            
            $calendarObj = new \Artmax\Calendar\Calendar();
            $userId = $USER->GetID();
            
            // Формируем дату и время
            $dateTime = $date . ' ' . $time;
            $dateFrom = new \DateTime($dateTime);
            $dateTo = clone $dateFrom;
            // Добавляем длительность в минутах (конвертируем в DateInterval)
            $dateTo->add(new \DateInterval('PT' . (int)$duration . 'M'));

            $eventsCreated = 0;
            $createdEvents = [];
            
            // Если событие повторяемое, создаем все события (включая первое)
            if ($repeat && $frequency) {
                // Вызываем createRecurringEvents с массивом параметров
                $recurringResult = $this->createRecurringEvents([
                    'original_event_id' => null,
                    'frequency' => $frequency,
                    'weekdays' => $weekdays,
                    'repeat_end' => $repeatEnd,
                    'repeat_count' => $repeatCount,
                    'repeat_end_date' => $repeatEndDate,
                    'event_color' => $eventColor,
                    'employee_id' => $employeeId,
                    'branch_id' => $branchId,
                    'schedule_start_date' => $dateFrom->format('Y-m-d H:i:s'),
                    'title' => $title,
                    'date_from' => $dateFrom->format('Y-m-d H:i:s'),
                    'date_to' => $dateTo->format('Y-m-d H:i:s'),
                    'user_id' => $userId,
                    'exclude_weekends' => $excludeWeekends,
                    'exclude_holidays' => $excludeHolidays,
                    'include_end_date' => $includeEndDate
                ]);
                if ($recurringResult && $recurringResult['count'] > 0) {
                    $eventsCreated = $recurringResult['count'];
                    
                    // Получаем все созданные события
                    foreach ($recurringResult['ids'] as $eventId) {
                        $event = $calendarObj->getEvent($eventId);
                        if ($event) {
                            $event['EVENT_COLOR'] = (is_string($eventColor)) ? $eventColor : '#3498db';
                            // Конвертируем даты в стандартный формат
                            $event['DATE_FROM'] = $this->convertRussianDateToStandard($event['DATE_FROM']);
                            $event['DATE_TO'] = $this->convertRussianDateToStandard($event['DATE_TO']);
                            $createdEvents[] = $event;
                        }
                    }
                }
            } else {
                // Если событие не повторяемое, создаем только одно событие
                if ($calendarObj->isTimeAvailableForDoctor($dateFrom->format('Y-m-d H:i:s'), $dateTo->format('Y-m-d H:i:s'), $employeeId, null, $branchId)) {
                    $eventId = $calendarObj->addEvent($title, '', $dateFrom->format('Y-m-d H:i:s'), $dateTo->format('Y-m-d H:i:s'), $userId, $branchId, $eventColor, $employeeId);
                    if ($eventId) {
                        // Записываем в журнал событие создания из расписания
                        $journal = new \Artmax\Calendar\Journal();
                        $journal->writeEvent(
                            $eventId,
                            'CREATED_BY_SCHEDULE',
                            'Artmax\Calendar\Calendar::addEvent',
                            $userId,
                            'EVENT_ID=' . $eventId
                        );
                        
                        $eventsCreated = 1;
                        $event = $calendarObj->getEvent($eventId);
                        if ($event) {
                            $event['EVENT_COLOR'] = (is_string($eventColor)) ? $eventColor : '#3498db';
                            // Конвертируем даты в стандартный формат
                            $event['DATE_FROM'] = $this->convertRussianDateToStandard($event['DATE_FROM']);
                            $event['DATE_TO'] = $this->convertRussianDateToStandard($event['DATE_TO']);
                            $createdEvents[] = $event;
                        }
                    }
                }
            }

            
            // Возвращаем результат
            if ($eventsCreated > 0) {
                return [
                    'success' => true, 
                    'eventId' => $mainEventId, 
                    'eventsCreated' => $eventsCreated,
                    'events' => $createdEvents
                ];
            } else {
                return [
                    'success' => false, 
                    'error' => 'Все выбранные времена заняты, расписание не создано'
                ];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Ошибка создания расписания: ' . $e->getMessage()];
        }
    }

    /**
     * Создание повторяющихся событий
     * 
     * @param array $params - Массив параметров:
     *   - original_event_id (int|null) - ID оригинального события
     *   - frequency (string) - Частота повторения (daily, weekly, monthly)
     *   - weekdays (array) - Дни недели для еженедельного повторения
     *   - repeat_end (string) - Тип окончания повторения (never, count, date)
     *   - repeat_count (int|null) - Количество повторений
     *   - repeat_end_date (string|null) - Дата окончания повторений (YYYY-MM-DD)
     *   - event_color (string) - Цвет события (hex, например #3498db)
     *   - employee_id (int|null) - ID врача
     *   - schedule_start_date (string|null) - Дата начала расписания (YYYY-MM-DD HH:MM:SS)
     *   - title (string|null) - Название события
     *   - date_from (string|null) - Дата начала события (YYYY-MM-DD HH:MM:SS)
     *   - date_to (string|null) - Дата окончания события (YYYY-MM-DD HH:MM:SS)
     *   - user_id (int|null) - ID пользователя
     *   - exclude_weekends (bool) - Исключать ли выходные дни
     *   - exclude_holidays (bool) - Исключать ли праздничные дни
     *   - include_end_date (bool) - Включать ли конечную дату в расписание
     */
    private function createRecurringEvents($params)
    {
        // Извлекаем параметры из массива с значениями по умолчанию
        $originalEventId = $params['original_event_id'] ?? null;
        $frequency = $params['frequency'] ?? 'daily';
        $weekdays = $params['weekdays'] ?? [];
        $repeatEnd = $params['repeat_end'] ?? 'never';
        $repeatCount = $params['repeat_count'] ?? null;
        $repeatEndDate = $params['repeat_end_date'] ?? null;
        $eventColor = $params['event_color'] ?? '#3498db';
        $employeeId = $params['employee_id'] ?? null;
        $branchId = $params['branch_id'] ?? 1;
        $scheduleStartDate = $params['schedule_start_date'] ?? null;
        $title = $params['title'] ?? '';
        $dateFrom = $params['date_from'] ?? null;
        $dateTo = $params['date_to'] ?? null;
        $userId = $params['user_id'] ?? null;
        $excludeWeekends = $params['exclude_weekends'] ?? false;
        $excludeHolidays = $params['exclude_holidays'] ?? false;
        $includeEndDate = $params['include_end_date'] ?? true;

        try {
            if (!CModule::IncludeModule('artmax.calendar')) {
                return ['count' => 0, 'ids' => []];
            }
            
            $calendarObj = new \Artmax\Calendar\Calendar();
            
            // Если переданы параметры для создания событий напрямую
            if ($title && $dateFrom && $dateTo && $userId) {
                $eventDateFrom = new \DateTime($dateFrom);
                $eventDateTo = new \DateTime($dateTo);
                $duration = $eventDateFrom->diff($eventDateTo);
                $startDate = $scheduleStartDate ? new \DateTime($scheduleStartDate) : $eventDateFrom;
            } else {
                // Старая логика для обратной совместимости
                $originalEvent = $calendarObj->getEvent($originalEventId);
                if (!$originalEvent) {
                    return ['count' => 0, 'ids' => []];
                }
                $eventDateFrom = new \DateTime($originalEvent['DATE_FROM']);
                $eventDateTo = new \DateTime($originalEvent['DATE_TO']);
                $duration = $eventDateFrom->diff($eventDateTo);
                $startDate = $scheduleStartDate ? new \DateTime($scheduleStartDate) : $eventDateFrom;
                $title = $originalEvent['TITLE'];
                $userId = $originalEvent['USER_ID'];
            }

            $eventsCreated = 0;
            $createdEventIds = [];
            
            // Для еженедельного расписания с выбранными днями недели
            if ($frequency === 'weekly' && !empty($weekdays)) {
                // Определяем базовую дату начала
                $startDate = $scheduleStartDate ? new \DateTime($scheduleStartDate) : $eventDateFrom;
                $endDate = null;
                
                if ($repeatEnd === 'date' && $repeatEndDate) {
                    // Если указана конечная дата, рассчитываем количество недель до неё
                    $endDate = new \DateTime($repeatEndDate);
                    
                    // Находим понедельник недели, в которой находится startDate
                    $startDayOfWeek = $startDate->format('N'); // 1 = понедельник, 7 = воскресенье
                    $startMondayOffset = $startDayOfWeek - 1;
                    $startMonday = clone $startDate;
                    $startMonday->sub(new \DateInterval('P' . $startMondayOffset . 'D'));
                    
                    // Находим понедельник недели, в которой находится endDate
                    $endDayOfWeek = $endDate->format('N');
                    $endMondayOffset = $endDayOfWeek - 1;
                    $endMonday = clone $endDate;
                    $endMonday->sub(new \DateInterval('P' . $endMondayOffset . 'D'));
                    
                    // Считаем количество недель между понедельниками
                    // Используем format('%a') для получения абсолютного количества дней
                    $daysDiff = (int)$startMonday->diff($endMonday)->format('%a');
                    $weeksDiff = $daysDiff / 7;
                    
                    // Если конечная дата попадает на выбранный день недели в своей неделе,
                    // то нужно включить эту неделю, даже если includeEndDate=false
                    $endDateDayOfWeek = $endDate->format('N');
                    $includeEndWeek = in_array($endDateDayOfWeek, $weekdays);
                    
                    // Округляем вверх, чтобы не пропустить последнюю неделю
                    $maxWeeks = (int)ceil($weeksDiff) + ($includeEndDate || $includeEndWeek ? 1 : 0);
                    $maxEvents = $maxWeeks * count($weekdays);
                } elseif ($repeatCount && $repeatCount > 0) {
                    // Для еженедельного повторения используем количество недель
                    $maxWeeks = $repeatCount;
                    $maxEvents = $repeatCount * count($weekdays);
                } else {
                    // Для бесконечного повторения
                    $maxWeeks = 100; // Максимум 100 недель для бесконечного повторения
                    $maxEvents = 100 * count($weekdays); // Максимум 100 событий для бесконечного повторения
                }
            } else {
                if ($repeatEnd === 'date' && $repeatEndDate) {
                    // Если указана конечная дата, рассчитываем количество событий по датам
                    $startDate = $scheduleStartDate ? new \DateTime($scheduleStartDate) : $eventDateFrom;
                    $endDate = new \DateTime($repeatEndDate);
                    // Устанавливаем время на 00:00:00 для корректного расчета дней
                    $startDate->setTime(0, 0, 0);
                    $endDate->setTime(0, 0, 0);
                    $daysDiff = $startDate->diff($endDate)->days;
                    
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                        "createRecurringEvents: Расчет maxEvents: startDate = " . $startDate->format('Y-m-d') . ", endDate = " . $endDate->format('Y-m-d') . ", daysDiff = $daysDiff, includeEndDate = " . ($includeEndDate ? 'true' : 'false') . "\n", 
                        FILE_APPEND | LOCK_EX);
                    
                    // Рассчитываем максимальное количество событий в зависимости от частоты
                    switch ($frequency) {
                        case 'daily':
                            // Для ежедневного: количество дней между датами + 1 (включая обе даты)
                            // Если includeEndDate = true, то +1, если false, то daysDiff (не включая конечную)
                            $maxEvents = $includeEndDate ? ($daysDiff + 1) : $daysDiff;
                            break;
                        case 'weekly':
                            $maxEvents = ceil($daysDiff / 7) + ($includeEndDate ? 1 : 0);
                            break;
                        case 'monthly':
                            $maxEvents = $startDate->diff($endDate)->m + ($includeEndDate ? 1 : 0);
                            break;
                        default:
                            $maxEvents = $includeEndDate ? ($daysDiff + 1) : $daysDiff;
                    }
                    
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                        "createRecurringEvents: Рассчитанный maxEvents = $maxEvents для frequency = $frequency\n", 
                        FILE_APPEND | LOCK_EX);
                } elseif ($repeatCount && $repeatCount > 0) {
                    // Если указано количество повторений, используем его
                    $maxEvents = $repeatCount;
                } else {
                    // Для бесконечного повторения
                    $maxEvents = 100; // Максимум 100 событий для бесконечного повторения
                }
            }
            
            $endDate = ($repeatEnd === 'date' && $repeatEndDate) ? new \DateTime($repeatEndDate) : null;
            
            // Логируем параметры для отладки
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "createRecurringEvents: repeatEnd = $repeatEnd, repeatCount = $repeatCount, maxEvents = $maxEvents\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "createRecurringEvents: frequency = $frequency, weekdays = " . json_encode($weekdays) . ", is_array = " . (is_array($weekdays) ? 'true' : 'false') . ", empty = " . (empty($weekdays) ? 'true' : 'false') . "\n", 
                FILE_APPEND | LOCK_EX);

            if ($frequency === 'weekly' && !empty($weekdays)) {
                // Специальная обработка для еженедельного повторения с выбранными днями недели
                // Используем дату начала расписания как базовую дату
                $currentDate = clone $startDate;
                $weekNumber = 0;
                
                // Проверяем, что maxWeeks определена
                if (!isset($maxWeeks)) {
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                        "createRecurringEvents: ERROR - maxWeeks не определена! Используем значение по умолчанию.\n", 
                        FILE_APPEND | LOCK_EX);
                    $maxWeeks = $repeatCount ? $repeatCount : 3;
                    $maxEvents = $maxWeeks * count($weekdays);
                }
                
                // Логируем параметры для отладки
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "createRecurringEvents: maxWeeks = $maxWeeks, maxEvents = $maxEvents, weekdays = " . implode(',', $weekdays) . "\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "createRecurringEvents: startDate = " . $startDate->format('Y-m-d H:i:s') . "\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "createRecurringEvents: eventDateFrom = " . $eventDateFrom->format('Y-m-d H:i:s') . "\n", 
                    FILE_APPEND | LOCK_EX);
                
                // Используем количество недель из параметров
                $weekNumber = 0; // Начинаем с недели 0 (первая неделя - та, на которую приходится дата начала)

                while ($weekNumber < $maxWeeks) {
                    // Логируем текущую неделю
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                        "createRecurringEvents: weekNumber = $weekNumber, eventsCreated = $eventsCreated, currentDate = " . $currentDate->format('Y-m-d') . "\n", 
                        FILE_APPEND | LOCK_EX);
                    
                    // Находим понедельник недели, в которой находится currentDate
                    $dayOfWeek = $currentDate->format('N'); // 1 = понедельник, 7 = воскресенье
                    $mondayOffset = $dayOfWeek - 1;
                    $weekStart = clone $currentDate;
                    $weekStart->sub(new \DateInterval('P' . $mondayOffset . 'D'));
                    
                    // Логируем начало недели
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                        "createRecurringEvents: weekStart = " . $weekStart->format('Y-m-d') . " (Monday of week containing " . $currentDate->format('Y-m-d') . ")\n", 
                        FILE_APPEND | LOCK_EX);
                    
                    // Создаем события для каждого выбранного дня недели в текущей неделе
                    foreach ($weekdays as $weekday) {
                        $eventDate = clone $weekStart;
                        $eventDate->add(new \DateInterval('P' . ($weekday - 1) . 'D'));
                        
                        // Логируем проверяемую дату
                        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                            "createRecurringEvents: Checking weekday $weekday, eventDate = " . $eventDate->format('Y-m-d') . ", startDate = " . $startDate->format('Y-m-d') . "\n", 
                            FILE_APPEND | LOCK_EX);
                        
                        // Проверяем, что дата события не раньше даты начала расписания
                        if ($eventDate >= $startDate) {
                            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                                "createRecurringEvents: Date " . $eventDate->format('Y-m-d') . " >= startDate " . $startDate->format('Y-m-d') . " - proceeding with event creation\n", 
                                FILE_APPEND | LOCK_EX);
                            // Проверяем ограничение по дате
                            if ($endDate) {
                                if ($includeEndDate) {
                                    // Если включаем конечную дату, проверяем что не превышаем её
                                    if ($eventDate->format('Y-m-d') > $endDate->format('Y-m-d')) {
                                        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                                            "createRecurringEvents: Date " . $eventDate->format('Y-m-d') . " exceeds endDate " . $endDate->format('Y-m-d') . " (includeEndDate=true) - breaking\n", 
                                            FILE_APPEND | LOCK_EX);
                                        break 2; // Выходим из обоих циклов
                                    }
                                } else {
                                    // Если не включаем конечную дату, проверяем что строго меньше её
                                    if ($eventDate->format('Y-m-d') >= $endDate->format('Y-m-d')) {
                                        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                                            "createRecurringEvents: Date " . $eventDate->format('Y-m-d') . " >= endDate " . $endDate->format('Y-m-d') . " (includeEndDate=false) - breaking\n", 
                                            FILE_APPEND | LOCK_EX);
                                        break 2; // Выходим из обоих циклов
                                    }
                                }
                                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                                    "createRecurringEvents: Date " . $eventDate->format('Y-m-d') . " passed endDate check (endDate=" . $endDate->format('Y-m-d') . ", includeEndDate=" . ($includeEndDate ? 'true' : 'false') . ")\n", 
                                    FILE_APPEND | LOCK_EX);
                            }
                            
                            // Дополнительная проверка по количеству событий (защита от переполнения)
                            if ($eventsCreated >= $maxEvents) {
                                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                                    "createRecurringEvents: Max events reached ($eventsCreated >= $maxEvents) - breaking\n", 
                                    FILE_APPEND | LOCK_EX);
                                break 2; // Выходим из обоих циклов
                            }
                            
                            $eventDateTo = clone $eventDate;
                            $eventDateTo->add($duration);
                            
                            // Проверяем исключения выходных и праздников
                            if ($excludeWeekends && $this->isWeekend($eventDate)) {
                                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                                    "createRecurringEvents: Date " . $eventDate->format('Y-m-d') . " is weekend - skipping\n", 
                                    FILE_APPEND | LOCK_EX);
                                continue; // Пропускаем выходные
                            }
                            
                            if ($excludeHolidays && $this->isHoliday($eventDate)) {
                                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                                    "createRecurringEvents: Date " . $eventDate->format('Y-m-d') . " is holiday - skipping\n", 
                                    FILE_APPEND | LOCK_EX);
                                continue; // Пропускаем праздники
                            }
                            
                            // Проверяем доступность времени для события
                            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                                "createRecurringEvents: Checking time availability for " . $eventDate->format('Y-m-d H:i:s') . " to " . $eventDateTo->format('Y-m-d H:i:s') . "\n", 
                                FILE_APPEND | LOCK_EX);
                            if ($calendarObj->isTimeAvailableForDoctor($eventDate->format('Y-m-d H:i:s'), $eventDateTo->format('Y-m-d H:i:s'), $employeeId, null, $branchId)) {
                                // Создаем событие
                                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                                    "createRecurringEvents: Creating event for date " . $eventDate->format('Y-m-d H:i:s') . "\n", 
                                    FILE_APPEND | LOCK_EX);
                                $recurringEventId = $calendarObj->addEvent(
                                    $title,
                                    '', // Описание пустое для расписания
                                    $eventDate->format('Y-m-d H:i:s'),
                                    $eventDateTo->format('Y-m-d H:i:s'),
                                    $userId,
                                    $branchId,
                                    $eventColor,
                                    $employeeId
                                );

                                if ($recurringEventId) {
                                    // Записываем в журнал событие создания из расписания
                                    $journal = new \Artmax\Calendar\Journal();
                                    $journal->writeEvent(
                                        $recurringEventId,
                                        'CREATED_BY_SCHEDULE',
                                        'Artmax\Calendar\Calendar::addEvent',
                                        $userId,
                                        'EVENT_ID=' . $recurringEventId
                                    );
                                    
                                    $eventsCreated++;
                                    $createdEventIds[] = $recurringEventId;
                                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                                        "createRecurringEvents: Event created with ID $recurringEventId, total events: $eventsCreated\n", 
                                        FILE_APPEND | LOCK_EX);
                                }
                            } else {
                                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                                    "createRecurringEvents: Time not available for " . $eventDate->format('Y-m-d H:i:s') . "\n", 
                                    FILE_APPEND | LOCK_EX);
                            }
                            // Если время занято, просто пропускаем этот день
                        } else {
                            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                                "createRecurringEvents: Date " . $eventDate->format('Y-m-d') . " < startDate " . $startDate->format('Y-m-d') . " - skipping this date\n", 
                                FILE_APPEND | LOCK_EX);
                        }
                    }
                    
                    // Переходим к следующей неделе
                    $currentDate->add(new \DateInterval('P7D'));
                    $weekNumber++;
                    
                    // Защита от бесконечного цикла
                    if ($weekNumber > 100) {
                        break;
                    }
                }
            } else {
                // Обычная обработка для других типов повторений
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "createRecurringEvents: Начинаем цикл создания событий, maxEvents = $maxEvents, frequency = $frequency\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "createRecurringEvents: startDate = " . $startDate->format('Y-m-d') . ", endDate = " . ($endDate ? $endDate->format('Y-m-d') : 'null') . ", includeEndDate = " . ($includeEndDate ? 'true' : 'false') . "\n", 
                    FILE_APPEND | LOCK_EX);
                $i = 0;
                $eventsCreated = 0;
                // Цикл идет по количеству повторений (maxEvents), а не по количеству созданных событий
                // Это гарантирует, что события создаются на конкретных датах (01.12, 02.12, 03.12...)
                while ($i < $maxEvents) {
                    $newDateFrom = clone $eventDateFrom;
                    $newDateTo = clone $eventDateTo;

                    // Вычисляем следующую дату в зависимости от частоты
                    switch ($frequency) {
                        case 'daily':
                            $newDateFrom->add(new \DateInterval('P' . $i . 'D'));
                            $newDateTo->add(new \DateInterval('P' . $i . 'D'));
                            break;
                        
                        case 'weekly':
                            $newDateFrom->add(new \DateInterval('P' . ($i * 7) . 'D'));
                            $newDateTo->add(new \DateInterval('P' . ($i * 7) . 'D'));
                            break;
                        
                        case 'monthly':
                            $newDateFrom->add(new \DateInterval('P' . $i . 'M'));
                            $newDateTo->add(new \DateInterval('P' . $i . 'M'));
                            break;
                    }

                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                        "createRecurringEvents: Итерация $i, дата события: " . $newDateFrom->format('Y-m-d') . ", eventsCreated = $eventsCreated, maxEvents = $maxEvents\n", 
                        FILE_APPEND | LOCK_EX);

                    // Проверяем ограничение по дате
                    if ($endDate) {
                        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                            "createRecurringEvents: Проверяем ограничение по дате, endDate = " . $endDate->format('Y-m-d') . ", includeEndDate = " . ($includeEndDate ? 'true' : 'false') . "\n", 
                            FILE_APPEND | LOCK_EX);
                        if ($includeEndDate) {
                            // Если включаем конечную дату, проверяем что не превышаем её
                            // Используем сравнение дат без времени для корректной проверки
                            $eventDateOnly = $newDateFrom->format('Y-m-d');
                            $endDateOnly = $endDate->format('Y-m-d');
                            if ($eventDateOnly > $endDateOnly) {
                                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                                    "createRecurringEvents: Дата события $eventDateOnly превышает конечную дату $endDateOnly - прерываем цикл\n", 
                                    FILE_APPEND | LOCK_EX);
                                break;
                            }
                        } else {
                            // Если не включаем конечную дату, проверяем что строго меньше её
                            $eventDateOnly = $newDateFrom->format('Y-m-d');
                            $endDateOnly = $endDate->format('Y-m-d');
                            if ($eventDateOnly >= $endDateOnly) {
                                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                                    "createRecurringEvents: Дата события $eventDateOnly больше или равна конечной дате $endDateOnly - прерываем цикл\n", 
                                    FILE_APPEND | LOCK_EX);
                                break;
                            }
                        }
                    }

                    
                    // Проверяем исключения выходных и праздников
                    if ($excludeWeekends && $this->isWeekend($newDateFrom)) {
                        $i++;
                        continue; // Пропускаем выходные
                    }
                    
                    if ($excludeHolidays && $this->isHoliday($newDateFrom)) {
                        $i++;
                        continue; // Пропускаем праздники
                    }
                    
                    // Проверяем доступность времени для повторяющегося события
                    $isAvailable = $calendarObj->isTimeAvailableForDoctor($newDateFrom->format('Y-m-d H:i:s'), $newDateTo->format('Y-m-d H:i:s'), $employeeId, null, $branchId);
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                        "createRecurringEvents: Итерация $i, дата " . $newDateFrom->format('Y-m-d') . ", время доступно: " . ($isAvailable ? 'да' : 'нет') . "\n", 
                        FILE_APPEND | LOCK_EX);
                    
                    if ($isAvailable) {
                        // Создаем событие
                        $recurringEventId = $calendarObj->addEvent(
                            $title,
                            '', // Описание пустое для расписания
                            $newDateFrom->format('Y-m-d H:i:s'),
                            $newDateTo->format('Y-m-d H:i:s'),
                            $userId,
                            $branchId, // branchId
                            $eventColor, // eventColor
                            $employeeId // employeeId
                        );

                        if ($recurringEventId) {
                            // Записываем в журнал событие создания из расписания
                            $journal = new \Artmax\Calendar\Journal();
                            $journal->writeEvent(
                                $recurringEventId,
                                'CREATED_BY_SCHEDULE',
                                'Artmax\Calendar\Calendar::addEvent',
                                $userId,
                                'EVENT_ID=' . $recurringEventId
                            );
                            
                            $eventsCreated++;
                            $createdEventIds[] = $recurringEventId;
                            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                                "createRecurringEvents: Создано событие ID=$recurringEventId на дату " . $newDateFrom->format('Y-m-d') . ", всего создано: $eventsCreated\n", 
                                FILE_APPEND | LOCK_EX);
                        } else {
                            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                                "createRecurringEvents: ОШИБКА - addEvent вернул false для даты " . $newDateFrom->format('Y-m-d') . "\n", 
                                FILE_APPEND | LOCK_EX);
                        }
                    } else {
                        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                            "createRecurringEvents: Время занято для даты " . $newDateFrom->format('Y-m-d') . " - пропускаем\n", 
                            FILE_APPEND | LOCK_EX);
                    }
                    // Если время занято, просто пропускаем этот день
                    $i++;
                }
            }

            return ['count' => $eventsCreated, 'ids' => $createdEventIds];
        } catch (\Exception $e) {
            return ['count' => 0, 'ids' => []];
        }
    }

    /**
     * Получение события по ID
     */
    public function getEventAction($eventId)
    {
        global $USER;
        if (!$USER || !$USER->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            if (!CModule::IncludeModule('artmax.calendar')) {
                return ['success' => false, 'error' => 'Модуль artmax.calendar не установлен'];
            }
            
            $calendarObj = new \Artmax\Calendar\Calendar();
            $event = $calendarObj->getEvent($eventId);

            if (!$event) {
                return ['success' => false, 'error' => 'Событие не найдено'];
            }

            // Проверяем права на просмотр
            // Админы видят всё, создатель события видит, врач события видит
            // Также проверяем право calendar.view_others
            $hasViewOthersPermission = false;
            if ($USER->IsAdmin()) {
                $hasViewOthersPermission = true;
            } else {
                try {
                    $permissionsObj = new \Artmax\Calendar\Permissions();
                    $hasViewOthersPermission = $permissionsObj->hasPermission($USER->GetID(), 'calendar.view_others');
                } catch (\Exception $e) {
                    $hasViewOthersPermission = false;
                }
            }
            
            if (!$hasViewOthersPermission && 
                $event['USER_ID'] != $USER->GetID() && 
                $event['EMPLOYEE_ID'] != $USER->GetID()) {
                return ['success' => false, 'error' => 'Нет прав на просмотр'];
            }

            // Загружаем данные контакта для события
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "GET_EVENT_ACTION: Загружаем контакт для события ID=$eventId\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "GET_EVENT_ACTION: CONTACT_ENTITY_ID = " . ($event['CONTACT_ENTITY_ID'] ?? 'null') . "\n", 
                FILE_APPEND | LOCK_EX);
            
            if (!empty($event['CONTACT_ENTITY_ID'])) {
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "GET_EVENT_ACTION: Вызываем getContactFromCRM для ID=" . $event['CONTACT_ENTITY_ID'] . "\n", 
                    FILE_APPEND | LOCK_EX);
                    
                $contactData = $this->getContactFromCRM($event['CONTACT_ENTITY_ID']);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "GET_EVENT_ACTION: getContactFromCRM результат: " . print_r($contactData, true) . "\n", 
                    FILE_APPEND | LOCK_EX);
                    
                if ($contactData) {
                    $event['CONTACT_NAME'] = $contactData['name'] ?? '';
                    $event['CONTACT_PHONE'] = $contactData['phone'] ?? '';
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                        "GET_EVENT_ACTION: Установлены CONTACT_NAME='" . $event['CONTACT_NAME'] . "', CONTACT_PHONE='" . $event['CONTACT_PHONE'] . "'\n", 
                        FILE_APPEND | LOCK_EX);
                } else {
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                        "GET_EVENT_ACTION: getContactFromCRM вернул null\n", 
                        FILE_APPEND | LOCK_EX);
                }
            } else {
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "GET_EVENT_ACTION: CONTACT_ENTITY_ID пустой, контакт не загружается\n", 
                    FILE_APPEND | LOCK_EX);
            }

            return ['success' => true, 'event' => $event];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Обновление события
     */
    public function updateEventAction($eventId, $title, $description, $dateFrom, $dateTo, $eventColor, $branchId)
    {
        global $USER;
        if (!$USER || !$USER->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            // Логируем входящие данные
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "UPDATE_EVENT: Входящие данные:\n" .
                "  eventId: $eventId\n" .
                "  title: $title\n" .
                "  dateFrom: $dateFrom\n" .
                "  dateTo: $dateTo\n" .
                "  eventColor: $eventColor\n" .
                "  branchId: $branchId\n", 
                FILE_APPEND | LOCK_EX);
            
            if (!CModule::IncludeModule('artmax.calendar')) {
                return ['success' => false, 'error' => 'Модуль artmax.calendar не установлен'];
            }
            
            $calendarObj = new \Artmax\Calendar\Calendar();
            $userId = $USER->GetID();

            // Получаем существующее событие для проверки прав
            $existingEvent = $calendarObj->getEvent($eventId);
            if (!$existingEvent) {
                return ['success' => false, 'error' => 'Событие не найдено'];
            }

            // Проверяем права на редактирование названия (для всех записей, включая свои)
            $hasEditTitlePermission = false;
            if ($USER->IsAdmin()) {
                $hasEditTitlePermission = true;
            } else {
                try {
                    $permissionsObj = new \Artmax\Calendar\Permissions();
                    $hasEditTitlePermission = $permissionsObj->hasPermission($userId, 'calendar.edit_title');
                } catch (\Exception $e) {
                    $hasEditTitlePermission = false;
                }
            }
            
            if (!$hasEditTitlePermission) {
                return ['success' => false, 'error' => 'Нет прав на редактирование названия записи'];
            }

            // Получаем employeeId из существующего события
            $employeeId = $existingEvent['EMPLOYEE_ID'] ?? null;

            // Проверяем доступность времени для врача в конкретном филиале (исключая текущее событие)
            if ($employeeId && !$calendarObj->isTimeAvailableForDoctor($dateFrom, $dateTo, $employeeId, $eventId, $existingEvent['BRANCH_ID'])) {
                return ['success' => false, 'error' => 'Время уже занято для выбранного врача в этом филиале'];
            }

            // Обновляем событие
            $result = $calendarObj->updateEvent($eventId, $title, $description, $dateFrom, $dateTo, $eventColor, $branchId);

            if ($result) {
                // Если есть привязанная активность CRM, обновляем её
                if (!empty($existingEvent['ACTIVITY_ID'])) {
                    $activityUpdated = $calendarObj->updateCrmActivity(
                        $existingEvent['ACTIVITY_ID'],
                        $title,
                        $dateFrom,
                        $dateTo
                    );
                    
                    if ($activityUpdated) {
                        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                            "UPDATE_EVENT: Обновлена активность CRM ID={$existingEvent['ACTIVITY_ID']}\n", 
                            FILE_APPEND | LOCK_EX);
                    }
                }
                
                // Логируем перед проверкой сделки
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "UPDATE_EVENT: Проверка наличия сделки. DEAL_ENTITY_ID={$existingEvent['DEAL_ENTITY_ID']}\n", 
                    FILE_APPEND | LOCK_EX);
                
                // Если есть привязанная сделка, обновляем бронирование
                if (!empty($existingEvent['DEAL_ENTITY_ID']) && \CModule::IncludeModule('crm')) {
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                        "UPDATE_EVENT: Условие выполнено, начинаем обновление бронирования\n", 
                        FILE_APPEND | LOCK_EX);
                    
                    $responsibleId = $existingEvent['EMPLOYEE_ID'] ?? $userId;
                    
                // Получаем часовой пояс филиала
                $calendar = new \Artmax\Calendar\Calendar();
                $branchTimezone = $calendar->getBranchTimezone($existingEvent['BRANCH_ID']);
                
                // Преобразуем формат даты из Y-m-d H:i:s в d.m.Y H:i:s для бронирования
                $dateFromObj = \DateTime::createFromFormat('Y-m-d H:i:s', $dateFrom, new \DateTimeZone($branchTimezone));
                $dateToObj = \DateTime::createFromFormat('Y-m-d H:i:s', $dateTo, new \DateTimeZone($branchTimezone));
                
                if (!$dateFromObj || !$dateToObj) {
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                        "UPDATE_EVENT: Ошибка парсинга дат. dateFrom=$dateFrom, dateTo=$dateTo\n", 
                        FILE_APPEND | LOCK_EX);
                    // Пробуем другой формат
                    $dateFromObj = \DateTime::createFromFormat('d.m.Y H:i:s', $dateFrom, new \DateTimeZone($branchTimezone));
                    $dateToObj = \DateTime::createFromFormat('d.m.Y H:i:s', $dateTo, new \DateTimeZone($branchTimezone));
                }
                
                // Используем исходное время как есть
                $bookingDateTime = $dateFromObj->format('d.m.Y H:i:s');
                 
                // Вычисляем длительность
                $durationSeconds = $dateToObj->getTimestamp() - $dateFromObj->getTimestamp();
                $bookingValue = "user|{$responsibleId}|{$bookingDateTime}|{$durationSeconds}|{$title}";
                
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "UPDATE_EVENT: Вычисление бронирования:\n" .
                    "  dateFrom (вход): $dateFrom\n" .
                    "  dateTo (вход): $dateTo\n" .
                    "  branchTimezone: $branchTimezone\n" .
                    "  bookingDateTime: $bookingDateTime\n" .
                    "  startDateTime timestamp: {$dateFromObj->getTimestamp()}\n" .
                    "  endDateTime timestamp: {$dateToObj->getTimestamp()}\n" .
                    "  durationSeconds: $durationSeconds\n" .
                    "  EMPLOYEE_ID: {$existingEvent['EMPLOYEE_ID']}\n" .
                    "  responsibleId: $responsibleId\n" .
                    "  title: $title\n" .
                    "  ФИНАЛЬНАЯ СТРОКА: $bookingValue\n", 
                    FILE_APPEND | LOCK_EX);
                    
                    $bookingFieldCode = \Bitrix\Main\Config\Option::get('artmax.calendar', 'deal_booking_field', 'UF_CRM_CALENDAR_BOOKING');
                    
                    $deal = new \CCrmDeal(false);
                    $updateFields = [
                        $bookingFieldCode => [$bookingValue]
                    ];
                    $deal->Update($existingEvent['DEAL_ENTITY_ID'], $updateFields);
                    
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                        "UPDATE_EVENT: Обновлено бронирование в сделке ID={$existingEvent['DEAL_ENTITY_ID']}\n", 
                        FILE_APPEND | LOCK_EX);
                }
                
                return ['success' => true];
            } else {
                return ['success' => false, 'error' => 'Ошибка обновления события'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Получение следующего дня недели для еженедельного повторения
     */
    private function getNextWeekday($startDate, $weekdays, $iteration = 1)
    {
        $currentDate = clone $startDate;
        
        // Для первой итерации ищем следующий день недели
        if ($iteration == 1) {
            for ($i = 1; $i <= 7; $i++) {
                $currentDate->add(new \DateInterval('P1D'));
                $currentWeekday = $currentDate->format('N'); // 1 (понедельник) - 7 (воскресенье)
                
                if (in_array($currentWeekday, $weekdays)) {
                    return $currentDate;
                }
            }
        } else {
            // Для последующих итераций добавляем недели
            $currentDate->add(new \DateInterval('P' . (($iteration - 1) * 7) . 'D'));
            
            // Ищем первый подходящий день недели в этой неделе
            for ($i = 0; $i < 7; $i++) {
                $currentWeekday = $currentDate->format('N');
                if (in_array($currentWeekday, $weekdays)) {
                    return $currentDate;
                }
                $currentDate->add(new \DateInterval('P1D'));
            }
        }
        
        return null;
    }

    /**
     * Поиск клиентов в Bitrix 24 CRM
     * Использует стандартный сервис crm.api.entity.search
     */
    public function searchClientsAction($query, $type = 'contact')
    {
        global $USER;
        if (!$USER || !$USER->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            $clients = [];
            
            if (strlen($query) >= 2) {
                // Подключаем модуль CRM
                if (!CModule::IncludeModule('crm')) {
                    return ['success' => false, 'error' => 'Модуль CRM не установлен'];
                }
                
                // Используем стандартный сервис для поиска
                if ($type === 'deal') {
                    $clients = $this->searchDealsViaStandardService($query);
                } else {
                    $clients = $this->searchContactsViaStandardService($query);
                }
            }
            
            return ['success' => true, 'clients' => $clients];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Поиск контактов через прямой запрос к CCrmContact
     */
    private function searchContactsViaStandardService($query)
    {
        // Сразу используем прямой поиск через CCrmContact
        return $this->searchContactsViaDirectQuery($query);
    }
    
    /**
     * Поиск сделок через прямой запрос к CCrmDeal
     */
    private function searchDealsViaStandardService($query)
    {
        // Сразу используем прямой поиск через CCrmDeal
        return $this->searchDealsViaDirectQuery($query);
    }
    
    /**
     * Fallback поиск сделок через прямой запрос к CCrmDeal
     */
    private function searchDealsViaDirectQuery($query)
    {
        $deals = [];
        
        try {
            // Подготавливаем фильтр
            $arFilter = [];
            $searchParts = preg_split('/[\s]+/', $query, 2, PREG_SPLIT_NO_EMPTY);
            
            if (count($searchParts) < 2) {
                $arFilter['LOGIC'] = 'OR';
                $arFilter['%TITLE'] = $query;
                $arFilter['%COMPANY_TITLE'] = $query;
            } else {
                $arFilter['LOGIC'] = 'OR';
                $arFilter["__INNER_FILTER_TITLE_1"] = ['%TITLE' => $searchParts[0], '%TITLE' => $searchParts[1]];
                $arFilter["__INNER_FILTER_COMPANY_1"] = ['%COMPANY_TITLE' => $searchParts[0], '%COMPANY_TITLE' => $searchParts[1]];
            }
            
            $arSelect = [
                'ID', 'TITLE', 'OPPORTUNITY', 'STAGE_ID', 'COMPANY_TITLE', 'CURRENCY_ID'
            ];
            
            $arOrder = ['TITLE' => 'ASC'];
            
            // Выполняем поиск
            $dbDeals = \CCrmDeal::GetListEx($arOrder, $arFilter, false, ['nTopCount' => 10], $arSelect);
            
            while ($deal = $dbDeals->Fetch()) {
                $deals[] = [
                    'id' => $deal['ID'],
                    'title' => $deal['TITLE'] ?: 'Сделка #' . $deal['ID'],
                    'amount' => $deal['OPPORTUNITY'] ?: '',
                    'stage' => $deal['STAGE_ID'] ?: '',
                    'company' => $deal['COMPANY_TITLE'] ?: '',
                    'currency' => $deal['CURRENCY_ID'] ?: 'RUB'
                ];
            }
            
        } catch (\Exception $e) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                'Ошибка прямого поиска сделок: ' . $e->getMessage() . "\n", 
                FILE_APPEND | LOCK_EX);
        }
        
        return $deals;
    }
    
    /**
     * Fallback поиск контактов через прямой запрос к CCrmContact
     */
    private function searchContactsViaDirectQuery($query)
    {
        $contacts = [];
        
        try {
            // Подготавливаем фильтр
            $arFilter = [];
            $searchParts = preg_split('/[\s]+/', $query, 2, PREG_SPLIT_NO_EMPTY);
            
            if (count($searchParts) < 2) {
                $arFilter['LOGIC'] = 'OR';
                $arFilter['%NAME'] = $query;
                $arFilter['%LAST_NAME'] = $query;
                $arFilter['%SECOND_NAME'] = $query;
                $arFilter['%EMAIL'] = $query;
                $arFilter['%PHONE'] = $query;
                $arFilter['%COMPANY_TITLE'] = $query;
            } else {
                $arFilter['LOGIC'] = 'OR';
                $arFilter["__INNER_FILTER_NAME_1"] = ['%NAME' => $searchParts[0], '%LAST_NAME' => $searchParts[1]];
                $arFilter["__INNER_FILTER_NAME_2"] = ['%LAST_NAME' => $searchParts[0], '%NAME' => $searchParts[1]];
                $arFilter["__INNER_FILTER_NAME_3"] = ['%NAME' => $searchParts[0], '%SECOND_NAME' => $searchParts[1]];
            }
            
            $arSelect = [
                'ID', 'NAME', 'SECOND_NAME', 'LAST_NAME', 
                'EMAIL', 'PHONE', 'COMPANY_TITLE', 'POST', 'ADDRESS'
            ];
            
            $arOrder = ['LAST_NAME' => 'ASC', 'NAME' => 'ASC'];
            
            // Выполняем поиск
            $dbContacts = \CCrmContact::GetListEx($arOrder, $arFilter, false, ['nTopCount' => 10], $arSelect);
            
            while ($contact = $dbContacts->Fetch()) {
                // Формируем полное имя
                $fullName = trim($contact['NAME'] . ' ' . $contact['LAST_NAME'] . ' ' . $contact['SECOND_NAME']);
                if (empty($fullName)) {
                    $fullName = 'Контакт #' . $contact['ID'];
                }
                
                $contacts[] = [
                    'id' => $contact['ID'],
                    'name' => $fullName,
                    'firstName' => $contact['NAME'],
                    'lastName' => $contact['LAST_NAME'],
                    'secondName' => $contact['SECOND_NAME'],
                    'phone' => $contact['PHONE'] ?? '',
                    'email' => $contact['EMAIL'] ?? '',
                    'company' => $contact['COMPANY_TITLE'] ?? '',
                    'post' => $contact['POST'] ?? '',
                    'address' => $contact['ADDRESS'] ?? ''
                ];
            }
            
        } catch (\Exception $e) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                'Ошибка fallback поиска контактов: ' . $e->getMessage() . "\n", 
                FILE_APPEND | LOCK_EX);
        }
        
        return $contacts;
    }
    
    
    /**
     * Сохранение сделки для события
     */
    public function saveEventDealAction($eventId, $dealData)
    {
        global $USER;
        if (!$USER || !$USER->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            // Подключаем модуль
            if (!CModule::IncludeModule('artmax.calendar')) {
                return ['success' => false, 'error' => 'Модуль artmax.calendar не установлен'];
            }

            // Проверяем, что событие существует
            $calendar = new \Artmax\Calendar\Calendar();
            $event = $calendar->getEvent($eventId);
            
            if (!$event) {
                return ['success' => false, 'error' => 'Событие не найдено'];
            }

            // Проверяем права на редактирование события
            if ($event['USER_ID'] != $USER->GetID()) {
                return ['success' => false, 'error' => 'Нет прав на редактирование события'];
            }

            // Декодируем JSON данные сделки
            $dealDataArray = json_decode($dealData, true);
            if (!$dealDataArray || !isset($dealDataArray['id'])) {
                return ['success' => false, 'error' => 'Неверные данные сделки'];
            }
            
            $journal = new \Artmax\Calendar\Journal();
            $userId = $USER->GetID();
            
            $oldDealId = !empty($event['DEAL_ENTITY_ID']) ? (int)$event['DEAL_ENTITY_ID'] : null;
            $newDealId = (int)$dealDataArray['id'];
            
            // Если был привязан другой deal, сначала записываем отвязку старого
            if ($oldDealId && $oldDealId !== $newDealId) {
                $journal->writeEvent(
                    $eventId,
                    'DEAL_DETACHED',
                    'Artmax\Calendar\Calendar::updateEventDeal',
                    $userId,
                    'DEAL_ID=' . $oldDealId
                );
            }
            
            // Обновляем событие, добавляя ID сделки
            $result = $calendar->updateEventDeal($eventId, $dealDataArray['id']);
            
            if ($result) {
                // Записываем в журнал привязку новой сделки
                $journal->writeEvent(
                    $eventId,
                    'DEAL_ATTACHED',
                    'Artmax\Calendar\Calendar::updateEventDeal',
                    $userId,
                    'DEAL_ID=' . $newDealId
                );
                
                // Создаем или обновляем активность в сделке
                $dealId = $dealDataArray['id'];
                
                // Проверяем, есть ли уже активность у события
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "SAVE_DEAL: Проверка активности. ACTIVITY_ID={$event['ACTIVITY_ID']}\n", 
                    FILE_APPEND | LOCK_EX);
                    
                if (!empty($event['ACTIVITY_ID'])) {
                    // Обновляем существующую активность
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                        "SAVE_DEAL: Обновляем существующую активность ID={$event['ACTIVITY_ID']}\n", 
                        FILE_APPEND | LOCK_EX);
                        
                    $activityUpdated = $calendar->updateCrmActivity(
                        $event['ACTIVITY_ID'],
                        $event['TITLE'],
                        $event['DATE_FROM'],
                        $event['DATE_TO']
                    );
                    
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                        "SAVE_DEAL: Обновлена активность ID={$event['ACTIVITY_ID']}\n", 
                        FILE_APPEND | LOCK_EX);
                } else {
                    // Создаем новую активность
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                        "SAVE_DEAL: Создаем новую активность. DealID=$dealId, Title={$event['TITLE']}, DateFrom={$event['DATE_FROM']}, DateTo={$event['DATE_TO']}, EmployeeID={$event['EMPLOYEE_ID']}\n", 
                        FILE_APPEND | LOCK_EX);
                        
                    $activityId = $calendar->createCrmActivity(
                        $dealId,
                        $event['TITLE'],
                        $event['DATE_FROM'],
                        $event['DATE_TO'],
                        $event['EMPLOYEE_ID'] ?? \CCrmSecurityHelper::GetCurrentUserID()
                    );
                    
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                        "SAVE_DEAL: createCrmActivity вернул activityId=$activityId\n", 
                        FILE_APPEND | LOCK_EX);
                    
                    if ($activityId) {
                        // Сохраняем ID активности
                        $calendar->saveEventActivityId($eventId, $activityId);
                        
                        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                            "SAVE_DEAL: Создана активность ID=$activityId для события ID=$eventId\n", 
                            FILE_APPEND | LOCK_EX);
                    } else {
                        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                            "SAVE_DEAL ERROR: Не удалось создать активность!\n", 
                            FILE_APPEND | LOCK_EX);
                    }
                }
                
                // Обновляем бронирование в сделке
                if (!\CModule::IncludeModule('crm')) {
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                        "SAVE_DEAL: CRM модуль не подключен для обновления бронирования\n", 
                        FILE_APPEND | LOCK_EX);
                } else {
                // Формируем строку бронирования
                $responsibleId = $event['EMPLOYEE_ID'] ?? \CCrmSecurityHelper::GetCurrentUserID();
                
                // Получаем часовой пояс филиала
                $calendar = new \Artmax\Calendar\Calendar();
                $branchTimezone = $calendar->getBranchTimezone($event['BRANCH_ID']);
                
                // Используем исходное время как есть
                $bookingDateTime = $event['DATE_FROM'];
                
                // Вычисляем длительность через DateTime объекты с часовым поясом филиала
                $startDateTime = \DateTime::createFromFormat('d.m.Y H:i:s', $event['DATE_FROM'], new \DateTimeZone($branchTimezone));
                $endDateTime = \DateTime::createFromFormat('d.m.Y H:i:s', $event['DATE_TO'], new \DateTimeZone($branchTimezone));
                $durationSeconds = $endDateTime->getTimestamp() - $startDateTime->getTimestamp();
                $serviceName = $event['TITLE'];
                $bookingValue = "user|{$responsibleId}|{$bookingDateTime}|{$durationSeconds}|{$serviceName}";
                
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "SAVE_DEAL: Вычисление бронирования:\n" .
                    "  DATE_FROM: {$event['DATE_FROM']}\n" .
                    "  DATE_TO: {$event['DATE_TO']}\n" .
                    "  branchTimezone: $branchTimezone\n" .
                    "  bookingDateTime: $bookingDateTime\n" .
                    "  startDateTime timestamp: {$startDateTime->getTimestamp()}\n" .
                    "  endDateTime timestamp: {$endDateTime->getTimestamp()}\n" .
                    "  durationSeconds: $durationSeconds\n" .
                    "  EMPLOYEE_ID: {$event['EMPLOYEE_ID']}\n" .
                    "  responsibleId: $responsibleId\n" .
                    "  serviceName: $serviceName\n" .
                    "  ФИНАЛЬНАЯ СТРОКА: $bookingValue\n", 
                    FILE_APPEND | LOCK_EX);
                    
                    // Получаем код поля бронирования из настроек модуля
                    $bookingFieldCode = \Bitrix\Main\Config\Option::get('artmax.calendar', 'deal_booking_field', 'UF_CRM_CALENDAR_BOOKING');
                    
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                        "SAVE_DEAL: Обновляем бронирование в сделке - Field: $bookingFieldCode, Value: $bookingValue\n", 
                        FILE_APPEND | LOCK_EX);
                    
                    // Обновляем сделку
                    $deal = new \CCrmDeal(false);
                    $updateFields = [
                        $bookingFieldCode => [$bookingValue]
                    ];
                    $updateResult = $deal->Update($dealId, $updateFields);
                    
                    if ($updateResult) {
                        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                            "SAVE_DEAL: Бронирование успешно обновлено в сделке ID=$dealId\n", 
                            FILE_APPEND | LOCK_EX);
                    } else {
                        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                            "SAVE_DEAL ERROR: Не удалось обновить бронирование в сделке\n", 
                            FILE_APPEND | LOCK_EX);
                    }
                }
                
                return ['success' => true, 'message' => 'Сделка сохранена и бронирование синхронизировано'];
            } else {
                return ['success' => false, 'error' => 'Ошибка сохранения сделки'];
            }

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Сохранение кастомных полей сделки
     */
    public function saveDealCustomFieldsAction($dealId, array $fields = [])
    {
        global $USER;
        if (!$USER || !$USER->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        $dealId = (int)$dealId;
        if ($dealId <= 0) {
            return ['success' => false, 'error' => 'Некорректный ID сделки'];
        }

        if (!\CModule::IncludeModule('crm')) {
            return ['success' => false, 'error' => 'Модуль CRM не установлен'];
        }

        if (!\CModule::IncludeModule('artmax.calendar')) {
            return ['success' => false, 'error' => 'Модуль artmax.calendar не установлен'];
        }

        // Получаем старые значения полей сделки для сравнения
        $fieldCodes = $this->getDealFieldCodes();
        $selectFields = array_filter(array_values($fieldCodes));
        $select = array_merge(['ID', 'TITLE', 'OPPORTUNITY', 'CURRENCY_ID'], $selectFields);
        $select = array_unique($select);
        
        $deal = \CCrmDeal::GetListEx([], ['=ID' => $dealId], false, false, $select)->Fetch();
        if (!$deal) {
            return ['success' => false, 'error' => 'Сделка не найдена'];
        }

        // Сохраняем старые значения для сравнения
        $oldValues = [
            'TITLE' => $deal['TITLE'] ?? '',
            'SERVICE' => $deal[$fieldCodes['SERVICE']] ?? null,
            'SOURCE' => $deal[$fieldCodes['SOURCE']] ?? null,
            'BRANCH' => $deal[$fieldCodes['BRANCH']] ?? null,
            'AMOUNT' => $deal[$fieldCodes['AMOUNT']] ?? '',
            'OPPORTUNITY' => $deal['OPPORTUNITY'] ?? 0,
            'CURRENCY_ID' => $deal['CURRENCY_ID'] ?? 'RUB',
        ];

        $updateFields = [];

        if (array_key_exists('service', $fields) && $fieldCodes['SERVICE']) {
            $serviceValue = trim((string)$fields['service']);
            $updateFields[$fieldCodes['SERVICE']] = $serviceValue === '' ? null : (int)$serviceValue;
        }

        if (array_key_exists('source', $fields) && $fieldCodes['SOURCE']) {
            $sourceValue = trim((string)$fields['source']);
            $updateFields[$fieldCodes['SOURCE']] = $sourceValue === '' ? null : (int)$sourceValue;
        }

        if (array_key_exists('branch', $fields) && $fieldCodes['BRANCH']) {
            $branchValue = trim((string)$fields['branch']);
            $updateFields[$fieldCodes['BRANCH']] = $branchValue === '' ? null : (int)$branchValue;
        }

        if (array_key_exists('amountValue', $fields) && $fieldCodes['AMOUNT']) {
            $amountValue = trim((string)$fields['amountValue']);
            $currency = isset($fields['amountCurrency']) && $fields['amountCurrency'] !== ''
                ? strtoupper($fields['amountCurrency'])
                : 'RUB';

            if ($amountValue === '') {
                $updateFields[$fieldCodes['AMOUNT']] = '';
                // Очищаем штатные поля суммы
                $updateFields['OPPORTUNITY'] = 0;
            } else {
                $normalizedAmount = str_replace(',', '.', $amountValue);
                $updateFields[$fieldCodes['AMOUNT']] = $normalizedAmount . '|' . $currency;
                
                // Сохраняем сумму в штатные поля сделки
                $updateFields['OPPORTUNITY'] = (float)$normalizedAmount;
                $updateFields['CURRENCY_ID'] = $currency;
            }
        }

        if (array_key_exists('title', $fields)) {
            $title = trim((string)$fields['title']);
            if ($title !== '') {
                $updateFields['TITLE'] = $title;
            }
        }

        if (empty($updateFields)) {
            return ['success' => false, 'error' => 'Нет данных для обновления'];
        }

        $dealEntity = new \CCrmDeal(false);
        $updateResult = $dealEntity->Update($dealId, $updateFields, true, true, [
            'DISABLE_USER_FIELD_CHECK' => false,
            'REGISTER_SONET_EVENT' => false
        ]);

        if (!$updateResult) {
            return ['success' => false, 'error' => $dealEntity->LAST_ERROR ?: 'Не удалось обновить сделку'];
        }

        // Находим событие, связанное со сделкой, для записи в журнал
        $eventId = null;
        $connection = \Bitrix\Main\Application::getConnection();
        $sql = "SELECT ID FROM artmax_calendar_events WHERE DEAL_ENTITY_ID = " . (int)$dealId . " LIMIT 1";
        $result = $connection->query($sql);
        if ($result && ($row = $result->fetch())) {
            $eventId = (int)$row['ID'];
        }

        // Записываем изменения в журнал, если найдено событие
        if ($eventId) {
            $journal = new \Artmax\Calendar\Journal();
            $userId = $USER->GetID();

            // Сравниваем и записываем изменения для каждого поля
            if (isset($updateFields['TITLE']) && $oldValues['TITLE'] !== $updateFields['TITLE']) {
                $journal->writeEvent(
                    $eventId,
                    'DEAL_TITLE_CHANGED',
                    'Artmax\Calendar\Calendar::saveDealCustomFieldsAction',
                    $userId,
                    'TITLE=' . $oldValues['TITLE'] . '->' . $updateFields['TITLE']
                );
            }

            if (isset($updateFields[$fieldCodes['SERVICE']])) {
                $oldService = $oldValues['SERVICE'];
                $newService = $updateFields[$fieldCodes['SERVICE']];
                if ($oldService != $newService) {
                    $journal->writeEvent(
                        $eventId,
                        'DEAL_SERVICE_CHANGED',
                        'Artmax\Calendar\Calendar::saveDealCustomFieldsAction',
                        $userId,
                        'SERVICE=' . ($oldService ?? 'null') . '->' . ($newService ?? 'null')
                    );
                }
            }

            if (isset($updateFields[$fieldCodes['SOURCE']])) {
                $oldSource = $oldValues['SOURCE'];
                $newSource = $updateFields[$fieldCodes['SOURCE']];
                if ($oldSource != $newSource) {
                    $journal->writeEvent(
                        $eventId,
                        'DEAL_SOURCE_CHANGED',
                        'Artmax\Calendar\Calendar::saveDealCustomFieldsAction',
                        $userId,
                        'SOURCE=' . ($oldSource ?? 'null') . '->' . ($newSource ?? 'null')
                    );
                }
            }

            if (isset($updateFields[$fieldCodes['BRANCH']])) {
                $oldBranch = $oldValues['BRANCH'];
                $newBranch = $updateFields[$fieldCodes['BRANCH']];
                if ($oldBranch != $newBranch) {
                    $journal->writeEvent(
                        $eventId,
                        'DEAL_BRANCH_CHANGED',
                        'Artmax\Calendar\Calendar::saveDealCustomFieldsAction',
                        $userId,
                        'BRANCH=' . ($oldBranch ?? 'null') . '->' . ($newBranch ?? 'null')
                    );
                }
            }

            if (isset($updateFields[$fieldCodes['AMOUNT']]) || isset($updateFields['OPPORTUNITY'])) {
                $oldAmount = $oldValues['AMOUNT'];
                $oldOpportunity = $oldValues['OPPORTUNITY'];
                $oldCurrency = $oldValues['CURRENCY_ID'];
                
                $newAmount = $updateFields[$fieldCodes['AMOUNT']] ?? $oldAmount;
                $newOpportunity = $updateFields['OPPORTUNITY'] ?? $oldOpportunity;
                $newCurrency = $updateFields['CURRENCY_ID'] ?? $oldCurrency;
                
                if ($oldAmount != $newAmount || $oldOpportunity != $newOpportunity || $oldCurrency != $newCurrency) {
                    $oldAmountStr = $oldAmount ?: ($oldOpportunity . ' ' . $oldCurrency);
                    $newAmountStr = $newAmount ?: ($newOpportunity . ' ' . $newCurrency);
                    $journal->writeEvent(
                        $eventId,
                        'DEAL_AMOUNT_CHANGED',
                        'Artmax\Calendar\Calendar::saveDealCustomFieldsAction',
                        $userId,
                        'AMOUNT=' . $oldAmountStr . '->' . $newAmountStr
                    );
                }
            }
        }

        $updatedDeal = \CCrmDeal::GetListEx([], ['=ID' => $dealId], false, false, ['ID', 'TITLE'])->Fetch();

        return [
            'success' => true,
            'dealId' => $dealId,
            'dealTitle' => $updatedDeal['TITLE'] ?? ($updateFields['TITLE'] ?? $deal['TITLE']),
            'fields' => [
                'service' => $fields['service'] ?? null,
                'source' => $fields['source'] ?? null,
                'branch' => $fields['branch'] ?? null,
                'amountValue' => $fields['amountValue'] ?? '',
                'amountCurrency' => $fields['amountCurrency'] ?? 'RUB',
            ]
        ];
    }

    /**
     * Возвращает коды пользовательских полей сделки
     */
    private function getDealFieldCodes(): array
    {
        return [
            'SERVICE' => \Bitrix\Main\Config\Option::get('artmax.calendar', 'deal_service_field', 'UF_CRM_CALENDAR_SERVICE'),
            'SOURCE' => \Bitrix\Main\Config\Option::get('artmax.calendar', 'deal_source_field', 'UF_CRM_CALENDAR_SOURCE'),
            'AMOUNT' => \Bitrix\Main\Config\Option::get('artmax.calendar', 'deal_amount_field', 'UF_CRM_CALENDAR_AMOUNT'),
            'BRANCH' => \Bitrix\Main\Config\Option::get('artmax.calendar', 'deal_branch_field', 'UF_CRM_CALENDAR_BRANCH'),
        ];
    }
    
    /**
     * Добавляет филиал в перечисление пользовательского поля сделки
     */
    private function addBranchToDealFieldEnum(int $branchId, string $branchName): void
    {
        if ($branchId <= 0 || $branchName === '') {
            return;
        }

        if (!\CModule::IncludeModule('crm')) {
            return;
        }

        $fieldCode = \Bitrix\Main\Config\Option::get('artmax.calendar', 'deal_branch_field', 'UF_CRM_CALENDAR_BRANCH');
        if (!$fieldCode) {
            return;
        }

        $field = \CUserTypeEntity::GetList(
            [],
            ['ENTITY_ID' => 'CRM_DEAL', 'FIELD_NAME' => $fieldCode]
        )->Fetch();

        if (!$field) {
            return;
        }

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

            if ($item['XML_ID'] === 'branch_' . $branchId || (int)$item['XML_ID'] === $branchId) {
                $alreadyExists = true;
            }
        }

        if ($alreadyExists) {
            return;
        }

        $existingValues['n' . $branchId] = [
            'VALUE' => $branchName,
            'DEF' => 'N',
            'SORT' => 100 + count($existingValues) * 10,
            'XML_ID' => 'branch_' . $branchId,
        ];

        $enum->SetEnumValues($field['ID'], $existingValues);
    }
    
    /**
     * Получение контакта для события
     */
    public function getEventContactsAction($eventId)
    {
        global $USER;
        if (!$USER || !$USER->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            // Подключаем модуль
            if (!CModule::IncludeModule('artmax.calendar')) {
                return ['success' => false, 'error' => 'Модуль artmax.calendar не установлен'];
            }

            // Получаем событие с контактом
            $calendar = new \Artmax\Calendar\Calendar();
            $event = $calendar->getEvent($eventId);
            
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "\n=== getEventContactsAction ===\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "eventId: " . $eventId . "\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "event found: " . ($event ? 'yes' : 'no') . "\n", 
                FILE_APPEND | LOCK_EX);
            
            if (!$event) {
                return ['success' => false, 'error' => 'Событие не найдено'];
            }
            
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "event USER_ID: " . ($event['USER_ID'] ?? 'null') . "\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "event EMPLOYEE_ID: " . ($event['EMPLOYEE_ID'] ?? 'null') . "\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "current USER ID: " . $USER->GetID() . "\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "IsAdmin: " . ($USER->IsAdmin() ? 'yes' : 'no') . "\n", 
                FILE_APPEND | LOCK_EX);

            $contact = null;
            if (!empty($event['CONTACT_ENTITY_ID'])) {
                // Получаем данные контакта из CRM
                $contact = $this->getContactFromCRM($event['CONTACT_ENTITY_ID']);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "CONTACT_ENTITY_ID: " . $event['CONTACT_ENTITY_ID'] . "\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "contact found: " . ($contact ? 'yes' : 'no') . "\n", 
                    FILE_APPEND | LOCK_EX);
            } else {
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "CONTACT_ENTITY_ID: empty\n", 
                    FILE_APPEND | LOCK_EX);
            }

            return ['success' => true, 'contact' => $contact];

        } catch (\Exception $e) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "ERROR: " . $e->getMessage() . "\n", 
                FILE_APPEND | LOCK_EX);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Получение сделки для события
     */
    public function getEventDealsAction($eventId)
    {
        global $USER;
        if (!$USER || !$USER->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            // Подключаем модуль
            if (!CModule::IncludeModule('artmax.calendar')) {
                return ['success' => false, 'error' => 'Модуль artmax.calendar не установлен'];
            }

            // Получаем событие со сделкой
            $calendar = new \Artmax\Calendar\Calendar();
            $event = $calendar->getEvent($eventId);
            
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "\n=== getEventDealsAction ===\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "eventId: " . $eventId . "\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "event found: " . ($event ? 'yes' : 'no') . "\n", 
                FILE_APPEND | LOCK_EX);
            
            if (!$event) {
                return ['success' => false, 'error' => 'Событие не найдено'];
            }
            
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "event USER_ID: " . ($event['USER_ID'] ?? 'null') . "\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "event EMPLOYEE_ID: " . ($event['EMPLOYEE_ID'] ?? 'null') . "\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "current USER ID: " . $USER->GetID() . "\n", 
                FILE_APPEND | LOCK_EX);

            $deal = null;
            if (!empty($event['DEAL_ENTITY_ID'])) {
                // Получаем данные сделки из CRM
                $deal = $this->getDealFromCRM($event['DEAL_ENTITY_ID']);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "DEAL_ENTITY_ID: " . $event['DEAL_ENTITY_ID'] . "\n", 
                    FILE_APPEND | LOCK_EX);
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "deal found: " . ($deal ? 'yes' : 'no') . "\n", 
                    FILE_APPEND | LOCK_EX);
            } else {
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                    "DEAL_ENTITY_ID: empty\n", 
                    FILE_APPEND | LOCK_EX);
            }

            return ['success' => true, 'deal' => $deal];

        } catch (\Exception $e) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "ERROR: " . $e->getMessage() . "\n", 
                FILE_APPEND | LOCK_EX);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Создание сделки для события календаря
     */
    public function createDealForEventAction($eventId, $contactId)
    {
        global $USER;
        if (!$USER || !$USER->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            // Подключаем модули
            if (!CModule::IncludeModule('artmax.calendar') || !CModule::IncludeModule('crm')) {
                return ['success' => false, 'error' => 'Необходимые модули не установлены'];
            }

            // Получаем данные события
            $calendar = new \Artmax\Calendar\Calendar();
            $event = $calendar->getEvent($eventId);
            
            if (!$event) {
                return ['success' => false, 'error' => 'Событие не найдено'];
            }

            // Получаем данные контакта для формирования названия сделки
            $contact = $this->getContactFromCRM($contactId);
            if (!$contact) {
                return ['success' => false, 'error' => 'Контакт не найден'];
            }

            // Формируем название сделки: Имя + Фамилия + Номер телефона
            $dealTitle = trim($contact['name']);
            if (!empty($contact['phone'])) {
                $dealTitle .= ' - ' . $contact['phone'];
            }
            if (empty($dealTitle)) {
                $dealTitle = 'Сделка для события #' . $eventId;
            }

            $responsibleId = $event['EMPLOYEE_ID'] ?? \CCrmSecurityHelper::GetCurrentUserID();
            
            // Получаем часовой пояс филиала
            $calendar = new \Artmax\Calendar\Calendar();
            $branchTimezone = $calendar->getBranchTimezone($event['BRANCH_ID']);
            
            // Используем исходное время как есть
            $bookingDateTime = $event['DATE_FROM'];
            
            // Вычисляем длительность через DateTime объекты с часовым поясом филиала
            $startDateTime = \DateTime::createFromFormat('d.m.Y H:i:s', $event['DATE_FROM'], new \DateTimeZone($branchTimezone));
            $endDateTime = \DateTime::createFromFormat('d.m.Y H:i:s', $event['DATE_TO'], new \DateTimeZone($branchTimezone));
            $durationSeconds = $endDateTime->getTimestamp() - $startDateTime->getTimestamp();
            $serviceName = $event['TITLE'];
            // Формат для Bitrix resourcebooking: user|ID|дата_время_начала|длительность_в_секундах|название
            $bookingValue = "user|{$responsibleId}|{$bookingDateTime}|{$durationSeconds}|{$serviceName}";
            
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "CREATE_DEAL: Вычисление бронирования:\n" .
                "  DATE_FROM: {$event['DATE_FROM']}\n" .
                "  DATE_TO: {$event['DATE_TO']}\n" .
                "  branchTimezone: $branchTimezone\n" .
                "  bookingDateTime: $bookingDateTime\n" .
                "  startDateTime timestamp: {$startDateTime->getTimestamp()}\n" .
                "  endDateTime timestamp: {$endDateTime->getTimestamp()}\n" .
                "  durationSeconds: $durationSeconds\n" .
                "  EMPLOYEE_ID: {$event['EMPLOYEE_ID']}\n" .
                "  responsibleId: $responsibleId\n" .
                "  serviceName: $serviceName\n" .
                "  ФИНАЛЬНАЯ СТРОКА: $bookingValue\n", 
                FILE_APPEND | LOCK_EX);
            
            // Получаем код поля бронирования из настроек модуля
            $bookingFieldCode = \Bitrix\Main\Config\Option::get('artmax.calendar', 'deal_booking_field', 'UF_CRM_CALENDAR_BOOKING');
            
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "CREATE_DEAL: Формируем бронирование - Field: $bookingFieldCode, Value: $bookingValue\n", 
                FILE_APPEND | LOCK_EX);
            
            // Получаем enum ID филиала для установки в сделку
            $branchEnumId = null;
            $branchFieldCode = \Bitrix\Main\Config\Option::get('artmax.calendar', 'deal_branch_field', 'UF_CRM_CALENDAR_BRANCH');
            if ($branchFieldCode && !empty($event['BRANCH_ID'])) {
                $field = \CUserTypeEntity::GetList(
                    [],
                    [
                        'ENTITY_ID' => 'CRM_DEAL',
                        'FIELD_NAME' => $branchFieldCode,
                    ]
                )->Fetch();
                
                if ($field) {
                    $enum = new \CUserFieldEnum();
                    $xmlId = 'branch_' . $event['BRANCH_ID'];
                    $rsEnum = $enum->GetList(
                        [],
                        [
                            'USER_FIELD_ID' => $field['ID'],
                            'XML_ID' => $xmlId
                        ]
                    );
                    $enumItem = $rsEnum->Fetch();
                    
                    if ($enumItem) {
                        $branchEnumId = (int)$enumItem['ID'];
                    } else {
                        // Пробуем найти по числовому XML_ID (для старых записей)
                        $rsEnum = $enum->GetList(
                            [],
                            [
                                'USER_FIELD_ID' => $field['ID'],
                                'XML_ID' => (string)$event['BRANCH_ID']
                            ]
                        );
                        $enumItem = $rsEnum->Fetch();
                        if ($enumItem) {
                            $branchEnumId = (int)$enumItem['ID'];
                        }
                    }
                }
            }
            
            // Создаем сделку
            $deal = new \CCrmDeal(true);
            $dealFields = [
                'TITLE' => $dealTitle,
                'CONTACT_IDS' => [$contactId],
                'ASSIGNED_BY_ID' => \CCrmSecurityHelper::GetCurrentUserID(),
                'STAGE_ID' => 'NEW',
                'OPPORTUNITY' => 0,
                'CURRENCY_ID' => 'RUB',
                'OPENED' => 'Y',
                // Добавляем бронирование
                $bookingFieldCode => [$bookingValue]
            ];
            
            // Добавляем филиал, если найден enum ID
            if ($branchEnumId && $branchFieldCode) {
                $dealFields[$branchFieldCode] = $branchEnumId;
            }

            $dealId = $deal->Add($dealFields);
            
            if ($dealId) {
                $journal = new \Artmax\Calendar\Journal();
                $userId = $USER->GetID();
                
                // Проверяем, был ли уже привязан deal
                $oldDealId = !empty($event['DEAL_ENTITY_ID']) ? (int)$event['DEAL_ENTITY_ID'] : null;
                
                // Если был привязан другой deal, сначала записываем отвязку старого
                if ($oldDealId && $oldDealId !== $dealId) {
                    $journal->writeEvent(
                        $eventId,
                        'DEAL_DETACHED',
                        'Artmax\Calendar\Calendar::updateEventDeal',
                        $userId,
                        'DEAL_ID=' . $oldDealId
                    );
                }
                
                // Привязываем сделку к событию
                $calendar->updateEventDeal($eventId, $dealId);
                
                // Записываем в журнал привязку новой сделки
                $journal->writeEvent(
                    $eventId,
                    'DEAL_ATTACHED',
                    'Artmax\Calendar\Calendar::updateEventDeal',
                    $userId,
                    'DEAL_ID=' . $dealId
                );
                
                // Создаем активность (бронирование) в сделке с датой и временем события
                $activityId = $calendar->createCrmActivity(
                    $dealId, 
                    $event['TITLE'], 
                    $event['DATE_FROM'], 
                    $event['DATE_TO'],
                    $event['EMPLOYEE_ID'] ?? $USER->GetID()
                );
                
                if ($activityId) {
                    // Сохраняем ID активности к событию
                    $calendar->saveEventActivityId($eventId, $activityId);
                    
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                        "CREATE_DEAL: Создана активность ID=$activityId для события ID=$eventId\n", 
                        FILE_APPEND | LOCK_EX);
                }
                
                return [
                    'success' => true, 
                    'dealId' => $dealId,
                    'activityId' => $activityId ?? null,
                    'eventId' => $eventId,
                    'branchId' => $event['BRANCH_ID'] ?? null,
                    'message' => 'Сделка успешно создана и привязана к событию'
                ];
            } else {
                return ['success' => false, 'error' => 'Ошибка создания сделки: ' . $deal->LAST_ERROR];
            }

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Создание нового контакта в CRM
     */
    public function createContactAction($contactData)
    {
        global $USER;
        if (!$USER || !$USER->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            if (!CModule::IncludeModule('crm')) {
                return ['success' => false, 'error' => 'Модуль CRM не установлен'];
            }

            // Создаем новый контакт
            $contactEntity = new \CCrmContact(true);
            
            $userId = \CCrmSecurityHelper::GetCurrentUserID();
            $name = $contactData['name'];
            $lastname = $contactData['lastname'] ?? '';
            $phone = $contactData['phone'] ?? '';
            $email = $contactData['email'] ?? '';
            
            // Формируем поля контакта
            $contactFields = [
                'NAME' => $name,
                'LAST_NAME' => $lastname,
                'OPENED' => 'Y',
                'ASSIGNED_BY_ID' => $userId,
            ];
            
            // Добавляем мультиполя для телефона
            if (!empty($phone)) {
                $contactFields['FM'] = [
                    'PHONE' => [
                        'n0' => [
                            'VALUE' => $phone,
                            'VALUE_TYPE' => 'WORK',
                        ],
                    ],
                ];
            }
            
            // Добавляем мультиполя для email
            if (!empty($email)) {
                if (isset($contactFields['FM'])) {
                    $contactFields['FM']['EMAIL'] = [
                        'n0' => [
                            'VALUE' => $email,
                            'VALUE_TYPE' => 'WORK',
                        ],
                    ];
                } else {
                    $contactFields['FM'] = [
                        'EMAIL' => [
                            'n0' => [
                                'VALUE' => $email,
                                'VALUE_TYPE' => 'WORK',
                            ],
                        ],
                    ];
                }
            }
            
            $contactId = $contactEntity->Add($contactFields);
            
            if ($contactId) {
                // Получаем данные созданного контакта
                $createdContact = $this->getContactFromCRM($contactId);
                
                return [
                    'success' => true, 
                    'contactId' => $contactId,
                    'contact' => $createdContact,
                    'message' => 'Контакт успешно создан'
                ];
            } else {
                return ['success' => false, 'error' => 'Ошибка создания контакта: ' . $contactEntity->LAST_ERROR];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    

    /**
     * Сохранение контакта к событию
     */
    public function saveEventContactAction($eventId, $contactId, $contactData)
    {
        global $USER;
        if (!$USER || !$USER->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            if (!CModule::IncludeModule('artmax.calendar')) {
                return ['success' => false, 'error' => 'Модуль календаря не установлен'];
            }

            $calendar = new \Artmax\Calendar\Calendar();
            $journal = new \Artmax\Calendar\Journal();
            $userId = $USER->GetID();
            
            // Получаем текущее событие, чтобы проверить, был ли ранее привязан контакт
            $event = $calendar->getEvent($eventId);
            if (!$event) {
                return ['success' => false, 'error' => 'Событие не найдено'];
            }
            
            $oldContactId = !empty($event['CONTACT_ENTITY_ID']) ? (int)$event['CONTACT_ENTITY_ID'] : null;
            $newContactId = (int)$contactId;
            
            // Если был привязан другой контакт, сначала записываем отвязку старого
            if ($oldContactId && $oldContactId !== $newContactId) {
                $journal->writeEvent(
                    $eventId,
                    'CONTACT_DETACHED',
                    'Artmax\Calendar\Calendar::updateEventContact',
                    $userId,
                    'CONTACT_ID=' . $oldContactId
                );
            }
            
            // Обновляем событие с ID контакта
            $result = $calendar->updateEventContact($eventId, $contactId);
            
            if ($result) {
                // Записываем в журнал привязку нового контакта
                $journal->writeEvent(
                    $eventId,
                    'CONTACT_ATTACHED',
                    'Artmax\Calendar\Calendar::updateEventContact',
                    $userId,
                    'CONTACT_ID=' . $newContactId
                );
                
                return [
                    'success' => true, 
                    'message' => 'Контакт успешно привязан к событию',
                    'contact' => $contactData
                ];
            } else {
                return ['success' => false, 'error' => 'Ошибка привязки контакта к событию'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Получение данных события
     */
    public function getEventDataAction($eventId)
    {
        global $USER;
        if (!$USER || !$USER->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            if (!CModule::IncludeModule('artmax.calendar')) {
                return ['success' => false, 'error' => 'Модуль календаря не установлен'];
            }

            $calendar = new \Artmax\Calendar\Calendar();
            $event = $calendar->getEvent($eventId);
            
            if ($event) {
                return [
                    'success' => true,
                    'event' => $event
                ];
            } else {
                return ['success' => false, 'error' => 'Событие не найдено'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Сохранение заметки к событию
     */
    public function saveEventNoteAction($eventId, $noteText)
    {
        global $USER;
        if (!$USER || !$USER->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            if (!CModule::IncludeModule('artmax.calendar')) {
                return ['success' => false, 'error' => 'Модуль календаря не установлен'];
            }

            $calendar = new \Artmax\Calendar\Calendar();
            
            // Проверяем, является ли пользователь автором записи
            $event = $calendar->getEvent($eventId);
            if (!$event) {
                return ['success' => false, 'error' => 'Событие не найдено'];
            }
            
            $isOwner = $event['USER_ID'] == $USER->GetID();
            
            // Если это чужая запись, проверяем право на редактирование заметок чужих записей
            if (!$isOwner) {
                $hasEditOthersNotesPermission = false;
                if ($USER->IsAdmin()) {
                    $hasEditOthersNotesPermission = true;
                } else {
                    try {
                        $permissionsObj = new \Artmax\Calendar\Permissions();
                        $hasEditOthersNotesPermission = $permissionsObj->hasPermission($USER->GetID(), 'calendar.edit_others_notes');
                    } catch (\Exception $e) {
                        $hasEditOthersNotesPermission = false;
                    }
                }
                
                if (!$hasEditOthersNotesPermission) {
                    return ['success' => false, 'error' => 'Нет прав на редактирование заметок чужих записей'];
                }
            }
            
            // Обновляем событие с заметкой
            $result = $calendar->updateEventNote($eventId, $noteText);
            
            if ($result) {
                return [
                    'success' => true, 
                    'message' => 'Заметка успешно сохранена'
                ];
            } else {
                return ['success' => false, 'error' => 'Ошибка сохранения заметки'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Получение списка сотрудников из CRM
     * @param int|null $branchId ID филиала (опционально). Если передан, возвращаются только сотрудники этого филиала
     */
    public function getEmployeesAction($branchId = null)
    {
        global $USER;
        if (!$USER || !$USER->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            // Если передан branchId, используем getBranchEmployees
            if ($branchId) {
                if (!CModule::IncludeModule('artmax.calendar')) {
                    return ['success' => false, 'error' => 'Модуль календаря не установлен'];
                }
                
                $calendar = new \Artmax\Calendar\Calendar();
                $employees = $calendar->getBranchEmployees($branchId);
                
                return [
                    'success' => true,
                    'employees' => $employees
                ];
            }

            // Если branchId не передан, возвращаем всех сотрудников компании
            // Фильтруем по заполненности WORK_POSITION и UF_DEPARTMENT
            // Сотрудники компании обычно имеют должность и привязку к отделу
            $userEntity = new \CUser();
            $users = $userEntity->GetList(
                ($by = "ID"),
                ($order = "ASC"),
                [
                    'ACTIVE' => 'Y',
                    '!WORK_POSITION' => false, // Должность заполнена
                    '!UF_DEPARTMENT' => false   // Отдел заполнен
                ],
                [
                    'FIELDS' => ['ID', 'NAME', 'LAST_NAME', 'LOGIN', 'EMAIL', 'WORK_POSITION', 'UF_DEPARTMENT']
                ]
            );

            $employees = [];
            while ($user = $users->Fetch()) {
                // Включаем пользователя, если есть имя, фамилия или логин
                if (!empty($user['NAME']) || !empty($user['LAST_NAME']) || !empty($user['LOGIN'])) {
                    $employees[] = [
                        'ID' => $user['ID'],
                        'NAME' => $user['NAME'] ?: '',
                        'LAST_NAME' => $user['LAST_NAME'] ?: '',
                        'LOGIN' => $user['LOGIN'] ?: '',
                        'EMAIL' => $user['EMAIL'] ?: ''
                    ];
                }
            }

            return [
                'success' => true,
                'employees' => $employees
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Поиск сотрудников по запросу
     */
    public function searchEmployeesAction($query)
    {
        global $USER;
        if (!$USER || !$USER->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            $query = trim($query);
            if (empty($query)) {
                return $this->getEmployeesAction();
            }

            // Получаем список пользователей из Bitrix с поиском только среди сотрудников компании
            // Фильтруем по заполненности WORK_POSITION и UF_DEPARTMENT
            $userEntity = new \CUser();
            $employees = [];
            $foundIds = [];
            
            // Поиск по имени среди сотрудников компании
            $usersByName = $userEntity->GetList(
                ($by = "ID"),
                ($order = "ASC"),
                [
                    'ACTIVE' => 'Y',
                    '!WORK_POSITION' => false, // Должность заполнена
                    '!UF_DEPARTMENT' => false, // Отдел заполнен
                    'NAME' => $query . ' &' // Поиск по имени с оператором &
                ],
                [
                    'FIELDS' => ['ID', 'NAME', 'LAST_NAME', 'LOGIN', 'EMAIL']
                ]
            );
            
            while ($user = $usersByName->Fetch()) {
                if (!in_array($user['ID'], $foundIds) && (!empty($user['NAME']) || !empty($user['LAST_NAME']) || !empty($user['LOGIN']))) {
                    $employees[] = [
                        'ID' => $user['ID'],
                        'NAME' => $user['NAME'] ?: '',
                        'LAST_NAME' => $user['LAST_NAME'] ?: '',
                        'LOGIN' => $user['LOGIN'] ?: '',
                        'EMAIL' => $user['EMAIL'] ?: ''
                    ];
                    $foundIds[] = $user['ID'];
                }
            }
            
            // Поиск по фамилии среди сотрудников компании
            $usersByLastName = $userEntity->GetList(
                ($by = "ID"),
                ($order = "ASC"),
                [
                    'ACTIVE' => 'Y',
                    '!WORK_POSITION' => false, // Должность заполнена
                    '!UF_DEPARTMENT' => false, // Отдел заполнен
                    'LAST_NAME' => $query . ' &' // Поиск по фамилии с оператором &
                ],
                [
                    'FIELDS' => ['ID', 'NAME', 'LAST_NAME', 'LOGIN', 'EMAIL']
                ]
            );
            
            while ($user = $usersByLastName->Fetch()) {
                if (!in_array($user['ID'], $foundIds) && (!empty($user['NAME']) || !empty($user['LAST_NAME']) || !empty($user['LOGIN']))) {
                    $employees[] = [
                        'ID' => $user['ID'],
                        'NAME' => $user['NAME'] ?: '',
                        'LAST_NAME' => $user['LAST_NAME'] ?: '',
                        'LOGIN' => $user['LOGIN'] ?: '',
                        'EMAIL' => $user['EMAIL'] ?: ''
                    ];
                    $foundIds[] = $user['ID'];
                }
            }
            
            // Поиск по логину среди сотрудников компании
            $usersByLogin = $userEntity->GetList(
                ($by = "ID"),
                ($order = "ASC"),
                [
                    'ACTIVE' => 'Y',
                    '!WORK_POSITION' => false, // Должность заполнена
                    '!UF_DEPARTMENT' => false, // Отдел заполнен
                    'LOGIN' => $query . ' &' // Поиск по логину с оператором &
                ],
                [
                    'FIELDS' => ['ID', 'NAME', 'LAST_NAME', 'LOGIN', 'EMAIL']
                ]
            );
            
            while ($user = $usersByLogin->Fetch()) {
                if (!in_array($user['ID'], $foundIds) && (!empty($user['NAME']) || !empty($user['LAST_NAME']) || !empty($user['LOGIN']))) {
                    $employees[] = [
                        'ID' => $user['ID'],
                        'NAME' => $user['NAME'] ?: '',
                        'LAST_NAME' => $user['LAST_NAME'] ?: '',
                        'LOGIN' => $user['LOGIN'] ?: '',
                        'EMAIL' => $user['EMAIL'] ?: ''
                    ];
                    $foundIds[] = $user['ID'];
                }
            }

            return [
                'success' => true,
                'employees' => $employees
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Сохранение настроек филиала
     */
    public function saveBranchSettingsAction($branchId, $timezoneName, $employeeIds, $branchName = null, $address = null, $phone = null, $email = null)
    {
        global $USER;
        if (!$USER || !$USER->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            if (!CModule::IncludeModule('artmax.calendar')) {
                return ['success' => false, 'error' => 'Модуль календаря не установлен'];
            }

            $calendar = new \Artmax\Calendar\Calendar();
            
            // Обновляем филиал (название, адрес, телефон, email)
            $branchObj = new \Artmax\Calendar\Branch();
            $updateData = [];

            if ($branchName !== null) $updateData['name'] = $branchName;
            if ($address !== null) $updateData['address'] = $address;
            if ($phone !== null) $updateData['phone'] = $phone;
            if ($email !== null) $updateData['email'] = $email;

            // Если есть что обновлять
            if (!empty($updateData)) {
                $updateResult = $branchObj->updateBranch($branchId,
                    $updateData['name'] ?? null,
                    $updateData['address'] ?? null,
                    $updateData['phone'] ?? null,
                    $updateData['email'] ?? null
                );

                if (!$updateResult) {
                    return ['success' => false, 'error' => 'Ошибка обновления данных филиала'];
                }

                // Обновляем страницы раздела только если изменилось название
                if (isset($updateData['name'])) {
                    try {
                        \Artmax\Calendar\EventHandlers::updateSectionPages();

                        // Дополнительно обновляем конкретную страницу филиала в настраиваемом разделе
                        \Artmax\Calendar\EventHandlers::updateBranchPageTitle($branchId, $updateData['name']);
                    } catch (\Exception $e) {
                        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                            'Ошибка обновления страниц раздела: ' . $e->getMessage() . "\n", 
                            FILE_APPEND | LOCK_EX);
                    }
                }
            }
            
            // Сохраняем часовой пояс
            if (!empty($timezoneName)) {
                $timezoneManager = new \Artmax\Calendar\TimezoneManager();
                $timezoneManager->setBranchTimezone($branchId, $timezoneName);
            }
            
            // Сохраняем сотрудников филиала
            $employeeIdsArray = json_decode($employeeIds, true);
            if (is_array($employeeIdsArray)) {
                // Получаем текущий список сотрудников филиала
                $currentEmployees = $calendar->getBranchEmployees($branchId);
                $currentEmployeeIds = array_map(function($emp) {
                    return (string)$emp['ID'];
                }, $currentEmployees);
                
                // Преобразуем новый список в строки для сравнения
                $newEmployeeIds = array_map('strval', $employeeIdsArray);
                
                // Находим отвязанных врачей (были в старом списке, но нет в новом)
                $unlinkedEmployeeIds = array_diff($currentEmployeeIds, $newEmployeeIds);
                
                // Проверяем, есть ли у отвязанных врачей активные события в этом филиале
                if (!empty($unlinkedEmployeeIds)) {
                    $employeesWithEvents = [];
                    
                    foreach ($unlinkedEmployeeIds as $unlinkedEmployeeId) {
                        // Получаем события врача в этом филиале
                        // Используем прямой SQL запрос, чтобы проверить только EMPLOYEE_ID (назначенные записи), а не USER_ID
                        $connection = \Bitrix\Main\Application::getConnection();
                        $sql = "SELECT COUNT(*) as count FROM artmax_calendar_events 
                                WHERE BRANCH_ID = " . (int)$branchId . " 
                                AND EMPLOYEE_ID = " . (int)$unlinkedEmployeeId . "
                                AND (STATUS IS NULL OR STATUS != 'cancelled')";
                        $result = $connection->query($sql);
                        $row = $result->fetch();
                        $eventsCount = (int)($row['count'] ?? 0);
                        
                        if ($eventsCount > 0) {
                            // Получаем имя врача
                            $employeeName = 'ID ' . $unlinkedEmployeeId;
                            foreach ($currentEmployees as $emp) {
                                if ((string)$emp['ID'] === $unlinkedEmployeeId) {
                                    $employeeName = trim(($emp['NAME'] ?? '') . ' ' . ($emp['LAST_NAME'] ?? ''));
                                    if (empty($employeeName)) {
                                        $employeeName = $emp['LOGIN'] ?? 'ID ' . $unlinkedEmployeeId;
                                    }
                                    break;
                                }
                            }
                            
                            $employeesWithEvents[] = [
                                'id' => $unlinkedEmployeeId,
                                'name' => $employeeName,
                                'eventsCount' => $eventsCount
                            ];
                        }
                    }
                    
                    // Если есть врачи с событиями, запрещаем сохранение
                    if (!empty($employeesWithEvents)) {
                        $employeeNames = array_map(function($emp) {
                            return $emp['name'] . ' (' . $emp['eventsCount'] . ' ' . $this->pluralizeEvents($emp['eventsCount']) . ')';
                        }, $employeesWithEvents);
                        
                        $errorMessage = 'Невозможно отвязать врачей от филиала, так как у них есть активные записи в календаре: ' . implode(', ', $employeeNames) . '. Пожалуйста, сначала переназначьте или отмените эти записи.';
                        
                        return [
                            'success' => false,
                            'error' => $errorMessage,
                            'employeesWithEvents' => $employeesWithEvents
                        ];
                    }
                }
                
                // Если проверка прошла успешно, сохраняем сотрудников
                $calendar->updateBranchEmployees($branchId, $employeeIdsArray);
            }

            return [
                'success' => true,
                'message' => 'Настройки филиала успешно сохранены'
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Склонение слова "запись" в зависимости от числа
     */
    private function pluralizeEvents($count)
    {
        $count = (int)$count;
        $mod10 = $count % 10;
        $mod100 = $count % 100;
        
        if ($mod10 == 1 && $mod100 != 11) {
            return 'запись';
        } elseif (in_array($mod10, [2, 3, 4]) && !in_array($mod100, [12, 13, 14])) {
            return 'записи';
        } else {
            return 'записей';
        }
    }

    /**
     * Получение всех филиалов
     */
    public function getBranchesAction()
    {
        global $USER;
        if (!$USER || !$USER->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            if (!CModule::IncludeModule('artmax.calendar')) {
                return ['success' => false, 'error' => 'Модуль календаря не установлен'];
            }

            $branchObj = new \Artmax\Calendar\Branch();
            $branches = $branchObj->getBranches();

            return [
                'success' => true,
                'branches' => $branches
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Ошибка получения филиалов: ' . $e->getMessage()];
        }
    }

    /**
     * Создание нового филиала
     */
    public function addBranchAction($name, $address = '', $phone = '', $email = '')
    {
        global $USER;
        if (!$USER || !$USER->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            if (!CModule::IncludeModule('artmax.calendar')) {
                return ['success' => false, 'error' => 'Модуль календаря не установлен'];
            }

            // Валидация
            if (empty($name)) {
                return ['success' => false, 'error' => 'Название филиала обязательно'];
            }

            $branchObj = new \Artmax\Calendar\Branch();
            $branchId = $branchObj->addBranch($name, $address, $phone, $email);

            if ($branchId) {
                $this->addBranchToDealFieldEnum((int)$branchId, (string)$name);

                // Обновляем страницы раздела для отображения нового филиала
                try {
                    \Artmax\Calendar\EventHandlers::updateSectionPages();
                } catch (\Exception $e) {
                    // Логируем ошибку, но не прерываем создание филиала
                    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                        'Ошибка обновления страниц раздела: ' . $e->getMessage() . "\n", 
                        FILE_APPEND | LOCK_EX);
                }

                return [
                    'success' => true,
                    'branchId' => $branchId,
                    'message' => 'Филиал успешно создан'
                ];
            } else {
                return ['success' => false, 'error' => 'Ошибка создания филиала'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Ошибка создания филиала: ' . $e->getMessage()];
        }
    }

    /**
     * Получение сотрудников филиала
     */
    public function getAvailableEmployeesForBranchAction($branchId)
    {
        artmax_log("getAvailableEmployeesForBranchAction: Called with branchId = " . $branchId);
        try {
            if (!CModule::IncludeModule('artmax.calendar')) {
                artmax_log("getAvailableEmployeesForBranchAction: Module not included");
                return ['success' => false, 'error' => 'Модуль календаря не установлен'];
            }

            artmax_log("getAvailableEmployeesForBranchAction: Creating Calendar object");
            $calendar = new \Artmax\Calendar\Calendar();
            artmax_log("getAvailableEmployeesForBranchAction: Calling getAvailableEmployeesForBranch");
            $employees = $calendar->getAvailableEmployeesForBranch($branchId);
            artmax_log("getAvailableEmployeesForBranchAction: Got " . count($employees) . " employees");

            return ['success' => true, 'employees' => $employees];
        } catch (\Exception $e) {
            artmax_log("getAvailableEmployeesForBranchAction: Exception: " . $e->getMessage());
            artmax_log("getAvailableEmployeesForBranchAction: Stack trace: " . $e->getTraceAsString());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getBranchEmployeesAction($branchId)
    {
        global $USER;
        if (!$USER || !$USER->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            if (!CModule::IncludeModule('artmax.calendar')) {
                return ['success' => false, 'error' => 'Модуль календаря не установлен'];
            }

            $calendar = new \Artmax\Calendar\Calendar();
            $employees = $calendar->getBranchEmployees($branchId);

            return [
                'success' => true,
                'employees' => $employees
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Ошибка получения сотрудников филиала: ' . $e->getMessage()];
        }
    }

    /**
     * Перенос события
     * @param array $params {
     *   @type int    $event_id
     *   @type int    $branch_id
     *   @type int    $employee_id
     *   @type string $date_from  YYYY-MM-DD HH:MM:SS
     *   @type string $date_to    YYYY-MM-DD HH:MM:SS
     * }
     */
    public function moveEventAction($params)
    {
        global $USER;
        if (!$USER || !$USER->IsAuthorized()) {
            return ['success' => false, 'error' => 'Необходима авторизация'];
        }

        try {
            if (!CModule::IncludeModule('artmax.calendar')) {
                return ['success' => false, 'error' => 'Модуль календаря не установлен'];
            }

            $calendar = new \Artmax\Calendar\Calendar();
            
            $eventId = (int)($params['event_id'] ?? 0);
            $branchId = isset($params['branch_id']) ? (int)$params['branch_id'] : null;
            $employeeId = isset($params['employee_id']) ? (int)$params['employee_id'] : null;
            $dateFrom = $params['date_from'] ?? '';
            $dateTo = $params['date_to'] ?? '';
            
            // Получаем текущее событие
            $event = $calendar->getEvent($eventId);
            if (!$event) {
                return ['success' => false, 'error' => 'Событие не найдено'];
            }

            // Проверяем права на перенос (право calendar.move или автор события)
            $hasMovePermission = false;
            if ($USER->IsAdmin()) {
                $hasMovePermission = true;
            } else {
                try {
                    $permissionsObj = new \Artmax\Calendar\Permissions();
                    $hasMovePermission = $permissionsObj->hasPermission($USER->GetID(), 'calendar.move');
                } catch (\Exception $e) {
                    $hasMovePermission = false;
                }
            }
            
            // Если нет права calendar.move, проверяем, является ли пользователь автором записи
            if (!$hasMovePermission && $event['USER_ID'] != $USER->GetID()) {
                return ['success' => false, 'error' => 'Нет прав на перенос события'];
            }

            // При переносе с обменом местами не проверяем конфликты заранее - 
            // метод moveEvent сам обрабатывает обмен местами между событиями

            // Логируем параметры для отладки
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "MOVE_EVENT_ACTION: Parameters:\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "  - eventId: " . $eventId . "\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "  - dateFrom: " . $dateFrom . "\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "  - dateTo: " . $dateTo . "\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "  - employeeId: " . ($employeeId ?? (int)$event['EMPLOYEE_ID']) . "\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "  - branchId: " . ($branchId ?? (int)$event['BRANCH_ID']) . "\n", 
                FILE_APPEND | LOCK_EX);
            
            // Используем специальный метод moveEvent для переноса с обменом местами
            $result = $calendar->moveEvent(
                $eventId,
                $dateFrom,
                $dateTo,
                $employeeId ?? (int)$event['EMPLOYEE_ID'],
                $branchId ?? (int)$event['BRANCH_ID'],
                $USER->GetID()
            );
            
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "MOVE_EVENT_ACTION: moveEvent result: " . print_r($result, true) . "\n", 
                FILE_APPEND | LOCK_EX);

            // Проверяем новый формат ответа от moveEvent
            if (is_array($result) && !empty($result['success'])) {
                // Возвращаем полный результат с информацией о затронутых событиях
                return $result;
            } elseif ($result === true) {
                // Обратная совместимость для старого формата
                return ['success' => true];
            } else {
                return ['success' => false, 'error' => 'Ошибка переноса события'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Ошибка переноса события: ' . $e->getMessage()];
        }
    }

    /**
     * Получение контакта из CRM по ID
     */
    public function getContactFromCRM($contactId)
    {
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "getContactFromCRM called with contactId: " . $contactId . "\n", 
            FILE_APPEND | LOCK_EX);
            
        if (!CModule::IncludeModule('crm')) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "CRM module not included\n", 
                FILE_APPEND | LOCK_EX);
            return null;
        }

        try {
            // Используем GetListEx с проверкой прав для текущего пользователя
            $arSelect = [
                'ID', 'NAME', 'LAST_NAME', 'SECOND_NAME', 
                'EMAIL', 'PHONE', 'COMPANY_TITLE', 'POST', 'ADDRESS'
            ];
            
            $arFilter = [
                'ID' => $contactId,
                'CHECK_PERMISSIONS' => 'N' // Отключаем проверку прав
            ];
            
            $dbContact = \CCrmContact::GetListEx([], $arFilter, false, false, $arSelect);
            $contact = $dbContact ? $dbContact->Fetch() : null;
            
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "CCrmContact::GetListEx result: " . ($contact ? 'found' : 'null') . "\n", 
                FILE_APPEND | LOCK_EX);
            if ($contact) {
                // Получаем телефоны из мультиполей
                $phones = [];
                $arPhoneFilter = [
                    'ENTITY_ID'  => 'CONTACT',
                    'ELEMENT_ID' => $contactId,
                    'TYPE_ID'    => 'PHONE',
                ];
                $dbPhones = \CCrmFieldMulti::GetListEx([], $arPhoneFilter, false, ['nTopCount' => 10], ['VALUE']);
                while ($arPhone = $dbPhones->fetch()) {
                    if (!empty($arPhone['VALUE'])) {
                        $phones[] = $arPhone['VALUE'];
                    }
                }
                
                // Получаем email из мультиполей
                $emails = [];
                $arEmailFilter = [
                    'ENTITY_ID'  => 'CONTACT',
                    'ELEMENT_ID' => $contactId,
                    'TYPE_ID'    => 'EMAIL',
                ];
                $dbEmails = \CCrmFieldMulti::GetListEx([], $arEmailFilter, false, ['nTopCount' => 10], ['VALUE']);
                while ($arEmail = $dbEmails->fetch()) {
                    if (!empty($arEmail['VALUE'])) {
                        $emails[] = $arEmail['VALUE'];
                    }
                }
                
                // Если в мультиполях ничего не найдено, берем из основных полей
                if (empty($phones) && !empty($contact['PHONE'])) {
                    $phones[] = $contact['PHONE'];
                }
                if (empty($emails) && !empty($contact['EMAIL'])) {
                    $emails[] = $contact['EMAIL'];
                }
                
                // Формируем полное имя
                $fullName = trim($contact['LAST_NAME'] .' '.$contact['NAME'] .  ' ' . $contact['SECOND_NAME']);
                if (empty($fullName)) {
                    $fullName = 'Контакт #' . $contact['ID'];
                }
                
                return [
                    'id' => $contact['ID'],
                    'name' => $fullName,
                    'phone' => implode(', ', $phones),
                    'email' => implode(', ', $emails),
                    'company' => $contact['COMPANY_TITLE'] ?? ''
                ];
            }
        } catch (\Exception $e) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "Exception in getContactFromCRM: " . $e->getMessage() . "\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                'Ошибка получения контакта из CRM: ' . $e->getMessage() . "\n", 
                FILE_APPEND | LOCK_EX);
        }

        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "getContactFromCRM returning null\n", 
            FILE_APPEND | LOCK_EX);
        return null;
    }
    
    /**
     * Получение сделки из CRM по ID
     */
    private function getDealFromCRM($dealId)
    {
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "getDealFromCRM called with dealId: " . $dealId . "\n", 
            FILE_APPEND | LOCK_EX);
            
        if (!CModule::IncludeModule('crm')) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "CRM module not included for deal\n", 
                FILE_APPEND | LOCK_EX);
            return null;
        }

        try {
            // Используем GetListEx с отключенной проверкой прав
            $arFilter = [
                'ID' => $dealId,
                'CHECK_PERMISSIONS' => 'N' // Отключаем проверку прав
            ];
            
            $dbDeal = \CCrmDeal::GetListEx([], $arFilter, false, false, ['ID', 'TITLE', 'OPPORTUNITY', 'CURRENCY_ID', 'STAGE_ID', 'COMPANY_TITLE']);
            $deal = $dbDeal ? $dbDeal->Fetch() : null;
            
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "CCrmDeal::GetListEx result: " . ($deal ? 'found' : 'null') . "\n", 
                FILE_APPEND | LOCK_EX);
            if ($deal) {
                return [
                    'id' => $deal['ID'],
                    'title' => $deal['TITLE'] ?? 'Сделка #' . $deal['ID'],
                    'amount' => $deal['OPPORTUNITY'] ?? '',
                    'currency' => $deal['CURRENCY_ID'] ?? 'RUB',
                    'stage' => $deal['STAGE_ID'] ?? '',
                    'company' => $deal['COMPANY_TITLE'] ?? ''
                ];
            }
        } catch (\Exception $e) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
                "Exception in getDealFromCRM: " . $e->getMessage() . "\n", 
                FILE_APPEND | LOCK_EX);
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                'Ошибка получения сделки из CRM: ' . $e->getMessage() . "\n", 
                FILE_APPEND | LOCK_EX);
        }

        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
            "getDealFromCRM returning null\n", 
            FILE_APPEND | LOCK_EX);
        return null;
    }
    
    /**
     * Поиск контактов в Bitrix 24 CRM через REST API
     */
    private function searchBitrix24Contacts($query)
    {
        $contacts = [];
        
        try {
            // Подключаем модуль REST API
            if (!CModule::IncludeModule('rest')) {
                file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                    'Модуль REST API не установлен' . "\n", 
                    FILE_APPEND | LOCK_EX);
                return $contacts;
            }
            
            // Получаем токен приложения
            $appId = 'local.1'; // ID локального приложения
            $appSecret = ''; // Секрет приложения (если нужен)
            
            // Создаем клиент REST API
            $restClient = new \CRestServer();
            
            // Подготавливаем параметры для поиска
            $searchParams = [
                'filter' => [
                    'LOGIC' => 'OR',
                    ['NAME' => '%' . $query . '%'],
                    ['LAST_NAME' => '%' . $query . '%'],
                    ['SECOND_NAME' => '%' . $query . '%'],
                    ['EMAIL' => '%' . $query . '%'],
                    ['PHONE' => '%' . $query . '%'],
                    ['COMPANY_TITLE' => '%' . $query . '%']
                ],
                'select' => [
                    'ID',
                    'NAME',
                    'LAST_NAME',
                    'SECOND_NAME',
                    'EMAIL',
                    'PHONE',
                    'COMPANY_TITLE',
                    'POST',
                    'ADDRESS'
                ],
                'order' => ['LAST_NAME' => 'ASC', 'NAME' => 'ASC'],
                'start' => 0,
                'limit' => 10
            ];
            
            // Если запрос похож на телефон, добавляем поиск по всем UF полям
            if (preg_match('/^[\d\s\-\+\(\)]+$/', $query)) {
                // Получаем список всех UF полей контакта
                $ufFields = $this->getContactUFFields();
                
                // Добавляем поиск по всем UF полям
                foreach ($ufFields as $ufField) {
                    $searchParams['filter'][] = [$ufField => '%' . $query . '%'];
                    $searchParams['select'][] = $ufField;
                }
            }
            
            // Выполняем REST API запрос
            $result = $restClient->callMethod('crm.contact.list', $searchParams);
            
            if ($result && isset($result['result'])) {
                foreach ($result['result'] as $contact) {
                    // Формируем полное имя
                    $fullName = trim(($contact['NAME'] ?? '') . ' ' . ($contact['LAST_NAME'] ?? '') . ' ' . ($contact['SECOND_NAME'] ?? ''));
                    if (empty($fullName)) {
                        $fullName = 'Контакт #' . $contact['ID'];
                    }
                    
                    // Собираем телефоны
                    $phones = [];
                    if (!empty($contact['PHONE'])) {
                        $phones[] = $contact['PHONE'];
                    }
                    
                    // Проверяем все UF поля на наличие телефонов
                    foreach ($contact as $fieldCode => $fieldValue) {
                        if (strpos($fieldCode, 'UF_') === 0 && 
                            !empty($fieldValue) && 
                            is_string($fieldValue) &&
                            preg_match('/[\d\s\-\+\(\)]{7,}/', $fieldValue) && // Содержит паттерн телефона
                            !in_array($fieldValue, $phones)) {
                            $phones[] = $fieldValue;
                        }
                    }
                    
                    // Собираем email адреса
                    $emails = [];
                    if (!empty($contact['EMAIL'])) {
                        $emails[] = $contact['EMAIL'];
                    }
                    
                    // Проверяем UF поля на наличие email
                    foreach ($contact as $fieldCode => $fieldValue) {
                        if (strpos($fieldCode, 'UF_') === 0 && 
                            !empty($fieldValue) && 
                            is_string($fieldValue) &&
                            filter_var($fieldValue, FILTER_VALIDATE_EMAIL) && // Валидный email
                            !in_array($fieldValue, $emails)) {
                            $emails[] = $fieldValue;
                        }
                    }
                    
                    $contacts[] = [
                        'id' => $contact['ID'],
                        'name' => $fullName,
                        'firstName' => $contact['NAME'] ?? '',
                        'lastName' => $contact['LAST_NAME'] ?? '',
                        'secondName' => $contact['SECOND_NAME'] ?? '',
                        'phone' => implode(', ', $phones),
                        'email' => implode(', ', $emails),
                        'company' => $contact['COMPANY_TITLE'] ?? '',
                        'post' => $contact['POST'] ?? '',
                        'address' => $contact['ADDRESS'] ?? ''
                    ];
                }
            }
            
        } catch (\Exception $e) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                'Ошибка поиска контактов через REST API: ' . $e->getMessage() . "\n", 
                FILE_APPEND | LOCK_EX);
        }
        
        return $contacts;
    }
    
    /**
     * Получение списка UF полей контакта
     */
    private function getContactUFFields()
    {
        $ufFields = [];
        
        try {
            if (CModule::IncludeModule('rest')) {
                $restClient = new \CRestServer();
                $result = $restClient->callMethod('crm.contact.userfield.list');
                
                if ($result && isset($result['result'])) {
                    foreach ($result['result'] as $field) {
                        if (isset($field['FIELD_NAME'])) {
                            $ufFields[] = $field['FIELD_NAME'];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar.log', 
                'Ошибка получения UF полей: ' . $e->getMessage() . "\n", 
                FILE_APPEND | LOCK_EX);
        }
        
        return $ufFields;
    }
    
    /**
     * Проверка, является ли дата выходным днем
     */
    private function isWeekend($date) {
        $dayOfWeek = $date->format('N'); // 1 = понедельник, 7 = воскресенье
        return $dayOfWeek == 6 || $dayOfWeek == 7; // Суббота или воскресенье
    }
    
    /**
     * Проверка, является ли дата праздничным днем
     * Пока возвращает false, можно расширить логику для конкретных праздников
     */
    private function isHoliday($date) {
        // Список праздничных дней (можно расширить)
        $holidays = [
            '01-01', // Новый год
            '01-07', // Рождество
            '02-23', // День защитника отечества
            '03-08', // Международный женский день
            '05-01', // Праздник весны и труда
            '05-09', // День Победы
            '06-12', // День России
            '11-04', // День народного единства
        ];
        
        $dateString = $date->format('m-d');
        return in_array($dateString, $holidays);
    }

    /**
     * Конвертирует дату из российского формата (день.месяц.год) в стандартный (год-месяц-день)
     * @param string $dateString Дата в формате "04.08.2025 09:00:00"
     * @return string Дата в формате "2025-08-04 09:00:00"
     */
    private function convertRussianDateToStandard($dateString)
    {
        // Проверяем, что строка не пустая
        if (empty($dateString)) {
            return $dateString;
        }

        // Если дата уже в стандартном формате, возвращаем как есть
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $dateString)) {
            return $dateString;
        }

        // Парсим российский формат: день.месяц.год час:минута:секунда
        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})\s+(\d{1,2}):(\d{1,2}):(\d{1,2})$/', $dateString, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $year = $matches[3];
            $hour = str_pad($matches[4], 2, '0', STR_PAD_LEFT);
            $minute = str_pad($matches[5], 2, '0', STR_PAD_LEFT);
            $second = str_pad($matches[6], 2, '0', STR_PAD_LEFT);
            
            return "{$year}-{$month}-{$day} {$hour}:{$minute}:{$second}";
        }

        // Если формат не распознан, пытаемся использовать strtotime как fallback
        $timestamp = strtotime($dateString);
        if ($timestamp !== false) {
            return date('Y-m-d H:i:s', $timestamp);
        }

        // Если ничего не получилось, возвращаем исходную строку
        return $dateString;
    }

    /**
     * Подключает необходимые скрипты и стили
     */
    private function includeAssets()
    {
        global $APPLICATION;
        
        // Подключаем библиотеку иконок Bitrix24
        \Bitrix\Main\UI\Extension::load("ui.buttons.icons");
        
        // Подключаем библиотеку SidePanel для боковых слайдеров
        \CJSCore::init("sidepanel");
        
        // Подключаем основной скрипт календаря с версионированием для обхода кэша
        $APPLICATION->AddHeadScript($this->getPath() . '/templates/.default/script.js?v=' . time());
        
        // Добавляем принудительное обновление кэша для JavaScript
        $APPLICATION->AddHeadString('<script>console.log("Calendar script loaded at:", new Date().toISOString());</script>');
        
        // Подключаем стили с версионированием для обхода кэша
        $APPLICATION->SetAdditionalCSS($this->getPath() . '/templates/.default/style.css?v=' . time());
        
        // Временное решение: добавляем функцию changeMonth прямо в class.php
        $APPLICATION->AddHeadString('<script>
            window.changeMonth = function(month) {
                console.log("changeMonth v2.4 called with month:", month);
                
                // ВСЕГДА используем текущий год, независимо от URL
                const currentYear = new Date().getFullYear();
                
                console.log("Using current year:", currentYear, "for month:", month);
                
                // Создаем новую дату с выбранным месяцем и текущим годом (1-е число месяца)
                const dateString = currentYear + "-" + String(month).padStart(2, "0") + "-" + "01";
                
                console.log("Changing month to:", month, "New date:", dateString);
                
                // Обновляем URL с новой датой
                const url = new URL(window.location);
                url.searchParams.set("date", dateString);
                window.location.href = url.toString();
            };
            
            window.initMonthSelector = function() {
                console.log("initMonthSelector v2.3 called");
                
                const monthSelect = document.getElementById("monthSelect");
                if (!monthSelect) {
                    console.log("Month selector not found!");
                    return;
                }
                
                // Получаем текущую дату из URL
                const urlParams = new URLSearchParams(window.location.search);
                const currentDateStr = urlParams.get("date") || new Date().toISOString().split("T")[0];
                
                console.log("Current date from URL for selector:", currentDateStr);
                
                // Парсим дату и получаем месяц
                const currentDate = new Date(currentDateStr);
                const month = currentDate.getMonth() + 1; // getMonth() возвращает 0-11, нам нужно 1-12
                
                console.log("Setting month selector to:", month);
                
                // Устанавливаем значение селектора
                monthSelect.value = month;
                console.log("Month selector updated to:", monthSelect.value);
            };
        </script>');
    }
}