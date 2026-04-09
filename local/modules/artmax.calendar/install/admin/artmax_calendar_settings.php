<?php
use Bitrix\Main\Localization\Loc;
use Artmax\Calendar\Permissions;
use Artmax\Calendar\ModuleSettings;
use Artmax\Calendar\TimezoneManager;
use Bitrix\Main\Application;


// Логируем информацию о запросе ДО проверки AJAX
try {
    $requestInfo = [
        'METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
        'POST_ajax_action' => $_POST['ajax_action'] ?? 'not set',
        'GET_ajax_action' => $_GET['ajax_action'] ?? 'not set',
        'REQUEST_ajax_action' => $_REQUEST['ajax_action'] ?? 'not set',
        'HTTP_X_REQUESTED_WITH' => $_SERVER['HTTP_X_REQUESTED_WITH'] ?? 'not set',
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'unknown'
    ];
} catch (\Exception $e) {
    error_log('ArtMax Calendar: Error logging request info: ' . $e->getMessage());
}

// Проверяем AJAX запрос ДО подключения прологов, чтобы не выводить HTML
// Используем $_REQUEST вместо $_POST, так как Bitrix может не распаковать POST до прологов
$isAjaxRequest = (
    ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['ajax_action']) || isset($_REQUEST['ajax_action']))) ||
    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
    (isset($_GET['ajax_action']))
);

// Логируем результат проверки AJAX
try {
} catch (\Exception $e) {
    error_log('ArtMax Calendar: AJAX check: ' . ($isAjaxRequest ? 'YES' : 'NO'));
}

// Если это не AJAX запрос, подключаем обычные прологи
if (!$isAjaxRequest) {
    require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php');
    require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php');
    Loc::loadMessages(__FILE__);

    // Подключаем модуль после прологов
    if (!CModule::IncludeModule('artmax.calendar')) {
        ShowError('Модуль artmax.calendar не установлен');
        require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
        die();
    }
}

    // Проверка прав доступа (только для обычных запросов)
    if (!$isAjaxRequest) {
        global $USER;
        try {

            // Явно подключаем файл класса
            $permissionsClassFile = $_SERVER['DOCUMENT_ROOT'] . '/local/modules/artmax.calendar/lib/Permissions.php';
            if (!file_exists($permissionsClassFile)) {
                throw new \Exception('Permissions class file not found');
            }
            require_once($permissionsClassFile);

            if (!class_exists('Artmax\\Calendar\\Permissions')) {
                throw new \Exception('Permissions class not found');
            }

            $permissionsObj = new \Artmax\Calendar\Permissions();
        if (!$permissionsObj->hasPermission($USER->GetID(), 'calendar.manage_groups') && !$USER->IsAdmin()) {
            ShowError('У вас нет прав для управления настройками модуля');
            require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
            die();
        }
    } catch (\Exception $e) {
        ShowError('Ошибка создания объекта Permissions: ' . $e->getMessage());
        require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
        die();
    }

    try {
        $moduleSettings = new ModuleSettings();
    } catch (\Exception $e) {
        ShowError('Ошибка создания объекта ModuleSettings: ' . $e->getMessage());
        require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
        die();
    }

    // ... остальные инициализации для обычных запросов
}

// Тестовое логирование (только для обычных запросов)
if (!$isAjaxRequest) {

    try {
        $moduleSettings = new ModuleSettings();
    } catch (\Exception $e) {
        ShowError('Ошибка создания ModuleSettings: ' . $e->getMessage());
        require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
        die();
    }

    try {
        $timezoneManager = new TimezoneManager();
    } catch (\Exception $e) {
        ShowError('Ошибка создания TimezoneManager: ' . $e->getMessage());
        require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
        die();
    }

    try {
        $connection = Application::getConnection();
    } catch (\Exception $e) {
        ShowError('Ошибка подключения к базе данных: ' . $e->getMessage());
        require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
        die();
    }
}

// Если это AJAX запрос, обрабатываем его без прологов (проверка уже выполнена выше)
if ($isAjaxRequest) {
    // Регистрируем обработчик фатальных ошибок для AJAX запросов
    register_shutdown_function(function() {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $errorMsg = 'Fatal error: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line'];
            error_log('ArtMax Calendar AJAX FATAL: ' . $errorMsg);

            
            // Выводим JSON ответ об ошибке
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['success' => false, 'error' => 'Фатальная ошибка: ' . $error['message']], JSON_UNESCAPED_UNICODE);
        }
    });
    
    try {
    } catch (\Exception $e) {
        error_log('ArtMax Calendar AJAX: REQUEST DETECTED');
    }

    // Очищаем весь буфер вывода
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Устанавливаем заголовки для JSON ответа ПЕРЕД любым выводом
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
    }

    // Подключаем минимальную инициализацию Bitrix только для работы с БД
    // Используем буферизацию, чтобы перехватить любой вывод
    $_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?: dirname(__DIR__, 4);
    ob_start();

    define('BX_SKIP_POST_UNPACK', true);
    define('NOT_CHECK_PERMISSIONS', true);
    define('NO_AGENT_CHECK', true);
    define('DisableEventsCheck', true);
    define('BX_NO_ACCELERATOR_RESET', true);

    require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

    // Очищаем все, что могло вывестись из prolog_before
    ob_end_clean();

    // Устанавливаем заголовки еще раз после очистки
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
    }

    if (!CModule::IncludeModule('artmax.calendar')) {
        echo json_encode(['success' => false, 'error' => 'Модуль artmax.calendar не установлен'], JSON_UNESCAPED_UNICODE);
        die();
    }
    
    // Загружаем необходимые модули Bitrix
    CModule::IncludeModule('main');
    
    // Инициализируем глобальные переменные Bitrix
    global $USER, $APPLICATION;
    
    // Инициализируем объекты для работы с настройками и правами
    try {
        $moduleSettings = new \Artmax\Calendar\ModuleSettings();

        $permissionsObj = new \Artmax\Calendar\Permissions();
        $timezoneManager = new \Artmax\Calendar\TimezoneManager();
    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Ошибка инициализации: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        die();
    } catch (\Error $e) {
        echo json_encode(['success' => false, 'error' => 'Критическая ошибка инициализации: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        die();
    }
    
    $action = $_POST['ajax_action'] ?? $_GET['ajax_action'] ?? '';
    $response = ['success' => false, 'error' => ''];

    try {
        switch ($action) {
        case 'save_module_setting':
            $key = trim($_POST['setting_key'] ?? '');
            $value = $_POST['setting_value'] ?? null;
            $type = trim($_POST['setting_type'] ?? 'string');
            $description = trim($_POST['setting_description'] ?? '');
            
            if (empty($key)) {
                $response['error'] = 'Ключ настройки не указан';
            } else {
                $result = $moduleSettings->set($key, $value, $type, $description);
                $response = ['success' => $result];
            }
            break;
            
        case 'get_all_bitrix_groups':
            // Получаем все группы Bitrix для выбора группы сотрудников
            $allGroups = $permissionsObj->getAllBitrixGroups();
            $response = [
                'success' => true,
                'groups' => $allGroups
            ];
            break;
            
        // Обработка действий для управления группами (перенесено из artmax_calendar_permissions.php)
        case 'create_group':
            // Проверяем права доступа
            global $USER;
            if (!$hasPermission) {
                $response['error'] = 'У вас нет прав для создания групп';
                break;
            }

            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (empty($name)) {
                $response['error'] = 'Название группы обязательно';
            } else {
                $result = $permissionsObj->createGroup($name, $description);
                $response = $result;
            }
            break;
            
        case 'delete_group':
            $groupId = (int)($_POST['group_id'] ?? 0);
            if ($groupId > 0) {
                $response = $permissionsObj->deleteGroup($groupId);
            } else {
                $response['error'] = 'Не указан ID группы';
            }
            break;

        case 'get_groups_list':
            // Возвращает HTML со списком групп для обновления вкладки
            try {
                $calendarGroups = $permissionsObj->getCalendarGroups();
                $allPermissions = $permissionsObj->getAllPermissions();

                // Генерируем HTML
                ob_start();
                if (empty($calendarGroups)): ?>
                    <p>Группы не найдены. Создайте первую группу.</p>
                <?php else: ?>
                    <?php
                    foreach ($calendarGroups as $group):
                        $group['LINKED_GROUPS'] = $permissionsObj->getLinkedBitrixGroups($group['GROUP_ID']);
                        $groupPermissions = $permissionsObj->getGroupPermissions($group['GROUP_ID']);
                        $groupUsers = $permissionsObj->getGroupUsers($group['GROUP_ID']);
                    ?>
                        <div class="artmax-group-item" data-group-id="<?= $group['GROUP_ID'] ?>" style="display: flex; justify-content: space-between; align-items: center; padding: 15px; border-bottom: 1px solid #f0f0f0;">
                            <div class="artmax-group-info" style="flex: 1;">
                                <div class="artmax-group-name" style="font-weight: bold; font-size: 16px; margin-bottom: 5px;">
                                    <?= htmlspecialchars($group['GROUP_NAME']) ?>
                                    <?php if ($group['ACTIVE'] !== 'Y'): ?>
                                        <span style="color: #999;">(неактивна)</span>
                                    <?php endif; ?>
                                </div>
                                <div style="color: #666; font-size: 14px; margin-top: 5px;">
                                    Права: <?= count($groupPermissions) ?> |
                                    Пользователей: <?= count($groupUsers) ?>
                                    <?php if (!empty($group['LINKED_GROUPS'])): ?>
                                        | Привязанных групп: <?= count($group['LINKED_GROUPS']) ?>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($group['LINKED_GROUPS'])): ?>
                                    <div style="margin-top: 8px; padding: 8px; background: #f8f9fa; border-radius: 4px; font-size: 12px;">
                                        <strong>Привязанные группы Bitrix:</strong>
                                        <?php foreach ($group['LINKED_GROUPS'] as $linkedGroup): ?>
                                            <span style="display: inline-block; margin: 2px 5px; padding: 2px 8px; background: #e3f2fd; border-radius: 3px;">
                                                <?= htmlspecialchars($linkedGroup['NAME']) ?>
                                                <a href="javascript:void(0)"
                                                   onclick="ArtMaxPermissions.unlinkBitrixGroup(<?= $group['GROUP_ID'] ?>, <?= $linkedGroup['BITRIX_GROUP_ID'] ?>)"
                                                   style="margin-left: 5px; color: #d32f2f; text-decoration: none;"
                                                   title="Отвязать группу">×</a>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="artmax-group-actions" style="display: flex; gap: 10px;">
                                <button class="ui-btn ui-btn-primary" onclick="ArtMaxPermissions.editPermissions(<?= $group['GROUP_ID'] ?>)">
                                    Права доступа
                                </button>
                                <button class="ui-btn ui-btn-link" onclick="ArtMaxPermissions.viewUsers(<?= $group['GROUP_ID'] ?>)">
                                    Пользователи
                                </button>
                                <button class="ui-btn ui-btn-link" onclick="ArtMaxPermissions.linkBitrixGroup(<?= $group['GROUP_ID'] ?>)" title="Привязать существующую группу Bitrix">
                                    Привязать группу
                                </button>
                                <button class="ui-btn ui-btn-danger" onclick="ArtMaxPermissions.deleteGroup(<?= $group['GROUP_ID'] ?>)">
                                    Удалить
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif;

                $html = ob_get_clean();
                $response = [
                    'success' => true,
                    'html' => $html
                ];
            } catch (\Exception $e) {
                $response['error'] = 'Ошибка получения списка групп: ' . $e->getMessage();
            }
            break;
            
        case 'link_bitrix_group':
            $calendarGroupId = (int)($_POST['calendar_group_id'] ?? 0);
            $bitrixGroupId = (int)($_POST['bitrix_group_id'] ?? 0);
            if ($calendarGroupId > 0 && $bitrixGroupId > 0) {
                $response = $permissionsObj->linkBitrixGroup($calendarGroupId, $bitrixGroupId);
            } else {
                $response['error'] = 'Не указаны ID групп';
            }
            break;
            
        case 'unlink_bitrix_group':
            $calendarGroupId = (int)($_POST['calendar_group_id'] ?? 0);
            $bitrixGroupId = (int)($_POST['bitrix_group_id'] ?? 0);
            if ($calendarGroupId > 0 && $bitrixGroupId > 0) {
                $response = $permissionsObj->unlinkBitrixGroup($calendarGroupId, $bitrixGroupId);
            } else {
                $response['error'] = 'Не указаны ID групп';
            }
            break;
            
        case 'get_group_info':
            $groupId = (int)($_POST['group_id'] ?? 0);
            if ($groupId > 0) {
                $groupPermissions = $permissionsObj->getGroupPermissions($groupId);
                $groupUsers = $permissionsObj->getGroupUsers($groupId);
                $linkedGroups = $permissionsObj->getLinkedBitrixGroups($groupId);
                
                $response = [
                    'success' => true,
                    'group' => [
                        'group_id' => $groupId,
                        'permissions_count' => count($groupPermissions),
                        'users_count' => count($groupUsers),
                        'linked_groups' => $linkedGroups
                    ]
                ];
            } else {
                $response['error'] = 'Не указан ID группы';
            }
            break;
            
        // Обработка действий для управления филиалами
        case 'create_branch':
            $name = trim($_POST['name'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $timezoneName = trim($_POST['timezone_name'] ?? 'Europe/Moscow');
            
            if (empty($name)) {
                $response['error'] = 'Название филиала обязательно';
            } else {
                try {
                    $branchObj = new \Artmax\Calendar\Branch();
                    $branchId = $branchObj->addBranch($name, $address, $phone, $email);
                    if ($branchId) {
                        // Устанавливаем часовой пояс
                        $timezoneManager->setBranchTimezone($branchId, $timezoneName);
                        $response = ['success' => true, 'branch_id' => $branchId];
                    } else {
                        $response['error'] = 'Ошибка создания филиала';
                    }
                } catch (\Exception $e) {
                    $response['error'] = 'Ошибка: ' . $e->getMessage();
                }
            }
            break;
            
        case 'update_branch':
            $branchId = (int)($_POST['branch_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $timezoneName = trim($_POST['timezone_name'] ?? 'Europe/Moscow');
            
            if ($branchId <= 0) {
                $response['error'] = 'Не указан ID филиала';
            } elseif (empty($name)) {
                $response['error'] = 'Название филиала обязательно';
            } else {
                try {
                    $branchObj = new \Artmax\Calendar\Branch();
                    if ($branchObj->updateBranch($branchId, $name, $address, $phone, $email)) {
                        // Обновляем часовой пояс
                        $timezoneManager->setBranchTimezone($branchId, $timezoneName);
                        $response = ['success' => true];
                    } else {
                        $response['error'] = 'Ошибка обновления филиала';
                    }
                } catch (\Exception $e) {
                    $response['error'] = 'Ошибка: ' . $e->getMessage();
                }
            }
            break;
            
        case 'delete_branch':
            $branchId = (int)($_POST['branch_id'] ?? 0);
            if ($branchId > 0) {
                try {
                    $branchObj = new \Artmax\Calendar\Branch();
                    $response = ['success' => $branchObj->deleteBranch($branchId)];
                } catch (\Exception $e) {
                    $response['error'] = 'Ошибка: ' . $e->getMessage();
                }
            } else {
                $response['error'] = 'Не указан ID филиала';
            }
            break;
            
        case 'get_branch':
            $branchId = (int)($_POST['branch_id'] ?? 0);
            if ($branchId > 0) {
                try {
                    $branchObj = new \Artmax\Calendar\Branch();
                    $branch = $branchObj->getBranch($branchId);
                    if ($branch) {
                        $timezone = $timezoneManager->getBranchTimezone($branchId);
                        $response = [
                            'success' => true,
                            'branch' => $branch,
                            'timezone' => $timezone
                        ];
                    } else {
                        $response['error'] = 'Филиал не найден';
                    }
                } catch (\Exception $e) {
                    $response['error'] = 'Ошибка: ' . $e->getMessage();
                }
            } else {
                $response['error'] = 'Не указан ID филиала';
            }
            break;

        case 'get_branches_list':
            // Возвращает HTML со списком филиалов для обновления вкладки
            try {
                // Инициализируем connection для AJAX запроса
                if (!isset($connection)) {
                    $connection = \Bitrix\Main\Application::getConnection();
                }
                
                // Инициализируем timezoneManager для AJAX запроса
                if (!isset($timezoneManager)) {
                    $timezoneManager = new \Artmax\Calendar\TimezoneManager();
                }
                
                $branchesList = [];

                // Получаем филиалы с безопасной обработкой ошибок
                try {
                    // Проверяем наличие колонки IS_ACTIVE
                    $hasIsActive = false;
                    try {
                        $checkSql = "SHOW COLUMNS FROM artmax_calendar_branches LIKE 'IS_ACTIVE'";
                        $checkResult = $connection->query($checkSql);
                        $hasIsActive = $checkResult->getSelectedRowsCount() > 0;
                    } catch (\Exception $e) {
                        // Игнорируем ошибку, просто используем запрос без IS_ACTIVE
                    }

                    // Формируем запрос в зависимости от наличия колонки
                    if ($hasIsActive) {
                        $sql = "SELECT * FROM artmax_calendar_branches WHERE IS_ACTIVE = 1 ORDER BY NAME";
                    } else {
                        $sql = "SELECT * FROM artmax_calendar_branches ORDER BY NAME";
                    }

                    $result = $connection->query($sql);
                    while ($row = $result->fetch()) {
                        $branchesList[] = $row;
                    }
                } catch (\Exception $e) {
                    $branchesList = [];
                }

                // Генерируем HTML
                ob_start();
                if (empty($branchesList)): ?>
                    <p>Филиалы не найдены. Создайте первый филиал.</p>
                <?php else: ?>
                    <?php foreach ($branchesList as $branch): ?>
                        <?php
                        $timezone = $timezoneManager->getBranchTimezone($branch['ID']);
                        $currentOffset = $timezoneManager->getCurrentOffset($branch['ID']);
                        ?>

                        <div class="artmax-branch-item" data-branch-id="<?= $branch['ID'] ?>" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background: #fff;">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div style="flex: 1;">
                                    <h3 style="margin-top: 0;"><?= htmlspecialchars($branch['NAME']) ?></h3>
                                    <?php if (!empty($branch['ADDRESS'])): ?>
                                        <p><strong>Адрес:</strong> <?= htmlspecialchars($branch['ADDRESS']) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($branch['PHONE'])): ?>
                                        <p><strong>Телефон:</strong> <?= htmlspecialchars($branch['PHONE']) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($branch['EMAIL'])): ?>
                                        <p><strong>Email:</strong> <?= htmlspecialchars($branch['EMAIL']) ?></p>
                                    <?php endif; ?>
                                    <p><strong>Часовой пояс:</strong> <?= $timezone && !empty($timezone['TIMEZONE_NAME']) ? htmlspecialchars($timezone['TIMEZONE_NAME']) : 'Не настроен' ?> (UTC<?= $currentOffset >= 0 ? '+' : '' ?><?= $currentOffset ?>)</p>
                                </div>
                                <div style="display: flex; gap: 10px;">
                                    <button type="button" class="ui-btn ui-btn-primary" onclick="ArtMaxBranches.editBranch(<?= $branch['ID'] ?>)">
                                        Редактировать
                                    </button>
                                    <button type="button" class="ui-btn ui-btn-danger" onclick="ArtMaxBranches.deleteBranch(<?= $branch['ID'] ?>)">
                                        Удалить
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif;

                $html = ob_get_clean();
                $response = [
                    'success' => true,
                    'html' => $html
                ];
            } catch (\Exception $e) {
                $response['error'] = 'Ошибка получения списка филиалов: ' . $e->getMessage();
            }
            break;
            
        default:
            $response['error'] = 'Неизвестное действие';
        }
    } catch (\Exception $e) {
        $response = [
            'success' => false,
            'error' => 'Ошибка при выполнении действия: ' . $e->getMessage()
        ];
    } catch (\Error $e) {
        $response = [
            'success' => false,
            'error' => 'Критическая ошибка при выполнении действия: ' . $e->getMessage()
        ];
    }

    // Убеждаемся, что заголовки установлены правильно
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
    }

    // Выводим JSON напрямую (без буферизации)
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

    // НЕ подключаем epilog_admin для AJAX запросов
    die();
}

// Для AJAX запросов выходим здесь, чтобы не выполнять остальной код
if ($isAjaxRequest) {
    die('Unexpected AJAX execution');
}

// Обработка сохранения настроек через обычную форму
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Сохранение основных настроек
    if (isset($_POST['save_settings'])) {
        // Сохраняем настройку группы для сотрудников филиала
        if (isset($_POST['branch_employees_group_id'])) {
            $groupId = (int)$_POST['branch_employees_group_id'];
            $oldGroupId = $moduleSettings->getBranchEmployeesGroupId();

            if ($groupId > 0) {
                $moduleSettings->setBranchEmployeesGroupId($groupId);
                $successMessage = 'Настройки успешно сохранены.';
                
                // Проверяем, что настройка сохранилась
                $savedGroupId = $moduleSettings->getBranchEmployeesGroupId();
            } else {
                // Если выбрано "Не использовать группу", удаляем настройку
                // Привязки сотрудников сохраняются для возможного возврата к групповому режиму
                $moduleSettings->delete('branch_employees_group_id');
                $successMessage = 'Настройки успешно сохранены. Режим переключен на индивидуальную настройку.';
            }
        }
    }
    
    // Сохранение настроек часового пояса
    if (isset($_POST['action']) && $_POST['action'] === 'update_timezone') {
        $branchId = (int)$_POST['branch_id'];
        $timezoneName = $_POST['timezone_name'] ?? 'Europe/Moscow';
        
        // Используем существующий метод setBranchTimezone для обновления часового пояса
        if ($timezoneManager->setBranchTimezone($branchId, $timezoneName)) {
            $successMessage = 'Настройки часового пояса обновлены успешно';
        } else {
            $errorMessage = 'Ошибка обновления настроек часового пояса';
        }
    }
}

// Получаем текущие настройки
try {
    $branchEmployeesGroupId = $moduleSettings->getBranchEmployeesGroupId();
} catch (\Exception $e) {
    $branchEmployeesGroupId = null;
}

try {
    $allBitrixGroups = $permissionsObj->getAllBitrixGroups();
} catch (\Exception $e) {
    $allBitrixGroups = [];
}

// Получаем данные для вкладок
try {
    $calendarGroups = $permissionsObj->getCalendarGroups();
} catch (\Exception $e) {
    $calendarGroups = [];
    error_log('Error getting calendar groups: ' . $e->getMessage());
}

$branches = [];

// Получаем филиалы с безопасной обработкой ошибок
try {
    // Проверяем наличие колонки IS_ACTIVE
    $hasIsActive = false;
    try {
        $checkSql = "SHOW COLUMNS FROM artmax_calendar_branches LIKE 'IS_ACTIVE'";
        $checkResult = $connection->query($checkSql);
        $hasIsActive = $checkResult->getSelectedRowsCount() > 0;
    } catch (\Exception $e) {
        // Игнорируем ошибку, просто используем запрос без IS_ACTIVE
    }

    // Формируем запрос в зависимости от наличия колонки
    if ($hasIsActive) {
        $sql = "SELECT * FROM artmax_calendar_branches WHERE IS_ACTIVE = 1 ORDER BY NAME";
    } else {
        $sql = "SELECT * FROM artmax_calendar_branches ORDER BY NAME";
    }

    $result = $connection->query($sql);
    while ($row = $result->fetch()) {
        $branches[] = $row;
    }
} catch (\Exception $e) {
    error_log('Error getting branches: ' . $e->getMessage());
    $branches = [];
}

// Получаем часовые пояса
try {
    $availableTimezones = $timezoneManager->getAvailableTimezones();
} catch (\Exception $e) {
    $availableTimezones = [];
    error_log('Error getting timezones: ' . $e->getMessage());
}

$APPLICATION->SetTitle('Настройки модуля календаря');

CJSCore::Init(['jquery', 'ui.buttons', 'ui.tabs']);
?>

<?php if (isset($successMessage)): ?>
    <div class="adm-info-message"><?= htmlspecialchars($successMessage) ?></div>
<?php endif; ?>

<?php if (isset($errorMessage)): ?>
    <div class="adm-error-message"><?= htmlspecialchars($errorMessage) ?></div>
<?php endif; ?>

<style>
.artmax-settings-container {
    padding: 20px;
    background: #fff;
    max-width: 1200px;
    margin: 0 auto;
}

.artmax-settings-tabs {
    margin-bottom: 20px;
    display: flex;
    gap: 0;
    border-bottom: 2px solid #e0e0e0;
    padding-bottom: 0;
}

.artmax-settings-tabs .artmax-tab-btn {
    padding: 12px 24px;
    text-decoration: none;
    border-radius: 0;
    transition: all 0.3s;
    cursor: pointer;
    border: none;
    border-bottom: 3px solid transparent;
    background: transparent;
    color: #666;
    font-size: 14px;
    font-weight: 500;
    display: inline-block;
    margin-right: 0;
    position: relative;
    outline: none;
}

.artmax-settings-tabs .artmax-tab-btn:hover {
    background-color: #f5f5f5;
    color: #333;
}

.artmax-settings-tabs .artmax-tab-btn.artmax-tab-active {
    color: #2066b0;
    border-bottom-color: #2066b0;
    background-color: #fff;
    font-weight: 600;
}

.artmax-settings-tab-content {
    display: none;
    padding: 20px;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    background: #fafafa;
    min-height: 400px;
}

.artmax-settings-tab-content.active {
    display: block;
}

.artmax-settings-form-group {
    margin-bottom: 20px;
}

.artmax-settings-form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: #333;
}

.artmax-settings-form-group select,
.artmax-settings-form-group input[type="text"] {
    width: 100%;
    max-width: 400px;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    line-height: 1.5;
    box-sizing: border-box;
    background-color: #fff;
    min-height: 38px;
}

.artmax-settings-form-group select {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23333' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    padding-right: 35px;
}

.artmax-settings-form-group .help-text {
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}
</style>

<div class="artmax-settings-container">
    <h1>Настройки модуля календаря</h1>
    
    <div class="artmax-settings-tabs" id="settings-tabs">
        <a href="#" class="artmax-tab-btn artmax-tab-active" data-tab="main">Основные</a>
        <a href="#" class="artmax-tab-btn" data-tab="groups">Группы пользователей</a>
        <a href="#" class="artmax-tab-btn" data-tab="branches">Филиалы</a>
    </div>
    
    <!-- Вкладка: Основные настройки -->
    <div id="tab-main" class="artmax-settings-tab-content active">
        <h2>Основные настройки</h2>
        
        <form method="POST" id="main-settings-form">
            <input type="hidden" name="save_settings" value="1">
            
            <div class="artmax-settings-form-group">
                <label for="branch_employees_group_id">
                    Группа пользователей для сотрудников филиала
                </label>
                <select name="branch_employees_group_id" id="branch_employees_group_id">
                    <option value="0">Не использовать группу (использовать таблицу artmax_calendar_branch_employees)</option>
                    <?php foreach ($allBitrixGroups as $group): ?>
                        <option value="<?= $group['ID'] ?>" <?= $branchEmployeesGroupId == $group['ID'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($group['NAME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="help-text">
                    <strong>Логика работы:</strong><br>
                    • <strong>Группа выбрана:</strong> В настройках каждого филиала можно выбрать сотрудников из этой группы. Существующие привязки сохраняются при смене группы.<br>
                    • <strong>Группа не выбрана:</strong> В настройках филиала доступны все пользователи системы для индивидуального назначения.<br>
                    • <strong>Важно:</strong> Привязки сотрудников филиалов сохраняются независимо от изменений группы. Сотрудники из предыдущих групп остаются доступными для управления.<br>
                    • <strong>Рекомендация:</strong> Используйте групповой режим - создайте группу "Врачи клиники" или выберите существующую группу пользователей.
                </div>
            </div>
            
            <div style="margin-top: 20px;">
                <button type="submit" class="ui-btn ui-btn-success">Сохранить настройки</button>
            </div>
        </form>
    </div>
    
    <!-- Вкладка: Группы пользователей -->
    <div id="tab-groups" class="artmax-settings-tab-content">
        <div class="artmax-permissions-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>Группы пользователей календаря</h2>
            <button class="ui-btn ui-btn-success" onclick="ArtMaxPermissions.createGroup()">
                Создать группу
            </button>
        </div>
        
        <div class="artmax-groups-list" style="background: #fff; border: 1px solid #e0e0e0; border-radius: 4px; padding: 15px;">
            <?php if (empty($calendarGroups)): ?>
                <p>Группы не найдены. Создайте первую группу.</p>
            <?php else: ?>
                <?php 
                $allPermissions = $permissionsObj->getAllPermissions();
                foreach ($calendarGroups as $group): 
                    $group['LINKED_GROUPS'] = $permissionsObj->getLinkedBitrixGroups($group['GROUP_ID']);
                    $groupPermissions = $permissionsObj->getGroupPermissions($group['GROUP_ID']);
                    $groupUsers = $permissionsObj->getGroupUsers($group['GROUP_ID']);
                ?>
                    <div class="artmax-group-item" data-group-id="<?= $group['GROUP_ID'] ?>" style="display: flex; justify-content: space-between; align-items: center; padding: 15px; border-bottom: 1px solid #f0f0f0;">
                        <div class="artmax-group-info" style="flex: 1;">
                            <div class="artmax-group-name" style="font-weight: bold; font-size: 16px; margin-bottom: 5px;">
                                <?= htmlspecialchars($group['GROUP_NAME']) ?>
                                <?php if ($group['ACTIVE'] !== 'Y'): ?>
                                    <span style="color: #999;">(неактивна)</span>
                                <?php endif; ?>
                            </div>
                            <div style="color: #666; font-size: 14px; margin-top: 5px;">
                                Права: <?= count($groupPermissions) ?> | 
                                Пользователей: <?= count($groupUsers) ?>
                                <?php if (!empty($group['LINKED_GROUPS'])): ?>
                                    | Привязанных групп: <?= count($group['LINKED_GROUPS']) ?>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($group['LINKED_GROUPS'])): ?>
                                <div style="margin-top: 8px; padding: 8px; background: #f8f9fa; border-radius: 4px; font-size: 12px;">
                                    <strong>Привязанные группы Bitrix:</strong>
                                    <?php foreach ($group['LINKED_GROUPS'] as $linkedGroup): ?>
                                        <span style="display: inline-block; margin: 2px 5px; padding: 2px 8px; background: #e3f2fd; border-radius: 3px;">
                                            <?= htmlspecialchars($linkedGroup['NAME']) ?>
                                            <a href="javascript:void(0)" 
                                               onclick="ArtMaxPermissions.unlinkBitrixGroup(<?= $group['GROUP_ID'] ?>, <?= $linkedGroup['BITRIX_GROUP_ID'] ?>)"
                                               style="margin-left: 5px; color: #d32f2f; text-decoration: none;" 
                                               title="Отвязать группу">×</a>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="artmax-group-actions" style="display: flex; gap: 10px;">
                            <button class="ui-btn ui-btn-primary" onclick="ArtMaxPermissions.editPermissions(<?= $group['GROUP_ID'] ?>)">
                                Права доступа
                            </button>
                            <button class="ui-btn ui-btn-link" onclick="ArtMaxPermissions.viewUsers(<?= $group['GROUP_ID'] ?>)">
                                Пользователи
                            </button>
                            <button class="ui-btn ui-btn-link" onclick="ArtMaxPermissions.linkBitrixGroup(<?= $group['GROUP_ID'] ?>)" title="Привязать существующую группу Bitrix">
                                Привязать группу
                            </button>
                            <button class="ui-btn ui-btn-danger" onclick="ArtMaxPermissions.deleteGroup(<?= $group['GROUP_ID'] ?>)">
                                Удалить
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Вкладка: Филиалы -->
    <div id="tab-branches" class="artmax-settings-tab-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>Управление филиалами</h2>
            <button type="button" class="ui-btn ui-btn-success" onclick="ArtMaxBranches.createBranch()">
                Создать филиал
            </button>
        </div>
        
        <div style="margin-top: 20px;">
            <?php if (empty($branches)): ?>
                <p>Филиалы не найдены. Создайте первый филиал.</p>
            <?php else: ?>
                <?php foreach ($branches as $branch): ?>
                    <?php
                    $timezone = $timezoneManager->getBranchTimezone($branch['ID']);
                    $currentOffset = $timezoneManager->getCurrentOffset($branch['ID']);
                    ?>
                    
                    <div class="artmax-branch-item" data-branch-id="<?= $branch['ID'] ?>" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px; background: #fff;">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div style="flex: 1;">
                                <h3 style="margin-top: 0;"><?= htmlspecialchars($branch['NAME']) ?></h3>
                                <?php if (!empty($branch['ADDRESS'])): ?>
                                    <p><strong>Адрес:</strong> <?= htmlspecialchars($branch['ADDRESS']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($branch['PHONE'])): ?>
                                    <p><strong>Телефон:</strong> <?= htmlspecialchars($branch['PHONE']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($branch['EMAIL'])): ?>
                                    <p><strong>Email:</strong> <?= htmlspecialchars($branch['EMAIL']) ?></p>
                                <?php endif; ?>
                                <p><strong>Часовой пояс:</strong> <?= $timezone && !empty($timezone['TIMEZONE_NAME']) ? htmlspecialchars($timezone['TIMEZONE_NAME']) : 'Не настроен' ?> (UTC<?= $currentOffset >= 0 ? '+' : '' ?><?= $currentOffset ?>)</p>
                            </div>
                            <div style="display: flex; gap: 10px;">
                                <button type="button" class="ui-btn ui-btn-primary" onclick="ArtMaxBranches.editBranch(<?= $branch['ID'] ?>)">
                                    Редактировать
                                </button>
                                <button type="button" class="ui-btn ui-btn-danger" onclick="ArtMaxBranches.deleteBranch(<?= $branch['ID'] ?>)">
                                    Удалить
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Обработчик сообщений от дочерних окон
window.addEventListener('message', function(event) {
    // Пропускаем логирование всех postMessage, чтобы не засорять консоль
    // Раскомментируйте строку ниже для отладки, если нужно
    // console.log('Received postMessage:', data);
    // Проверяем происхождение сообщения для безопасности
    if (event.origin !== window.location.origin) {
        console.log('Message blocked: wrong origin', event.origin);
        return;
    }

    var data = event.data;
    // Пропускаем логирование всех postMessage, чтобы не засорять консоль
    // Раскомментируйте строку ниже для отладки, если нужно
    // console.log('Received postMessage:', data);

    if (data && data.type) {
        switch (data.type) {
            case 'calendar:groupPermissionsChanged':
                // Обновляем вкладку групп после изменения прав
                console.log('Refreshing groups tab after permissions change');
                ArtMaxPermissions.refreshGroupsTab();
                break;
            case 'calendar:groupUsersChanged':
                // Обновляем вкладку групп после изменения пользователей
                console.log('Refreshing groups tab after users change');
                ArtMaxPermissions.refreshGroupsTab();
                break;
            case 'calendar:branchCreated':
                // Обновляем вкладку филиалов после создания филиала
                console.log('Refreshing branches tab after branch creation');
                ArtMaxBranches.refreshBranchesTab();
                break;
            case 'calendar:branchSettingsSaved':
                // Обновляем вкладку филиалов после изменения настроек филиала
                console.log('Refreshing branches tab after branch settings saved');
                ArtMaxBranches.refreshBranchesTab();
                break;
        }
    }
});

// JavaScript для управления группами (перенесено из artmax_calendar_permissions.php)
var ArtMaxPermissions = {
    createGroup: function() {
        var name = prompt('Введите название группы:');
        if (!name || name.trim() === '') {
            return;
        }
        
        var description = prompt('Введите описание группы (необязательно):', '');
        
        console.log('JavaScript: Sending createGroup AJAX request');
        console.log('JavaScript: Name:', name, 'Description:', description);

        BX.ajax({
            url: window.location.href,
            method: 'POST',
            data: {
                ajax_action: 'create_group',
                name: name.trim(),
                description: description ? description.trim() : ''
            },
            dataType: 'json',
            onsuccess: function(response) {
                console.log('JavaScript: createGroup response:', response);
                if (response.success) {
                    console.log('JavaScript: Group created, calling refreshGroupsTab');
                    ArtMaxPermissions.refreshGroupsTab();
                } else {
                    console.log('JavaScript: Error creating group:', response.error);
                    alert('Ошибка: ' + (response.error || 'Неизвестная ошибка'));
                }
            },
            onfailure: function(data, errorText, errorThrown) {
                console.log('JavaScript: AJAX failed:', {data: data, errorText: errorText, errorThrown: errorThrown});
            }
        });
    },
    
    deleteGroup: function(groupId) {
        if (!confirm('Вы уверены, что хотите удалить эту группу? Права группы будут удалены, но группа останется в Bitrix.')) {
            return;
        }

        BX.ajax({
            url: window.location.href,
            method: 'POST',
            data: {
                ajax_action: 'delete_group',
                group_id: groupId
            },
            dataType: 'json',
            onsuccess: function(response) {
                if (response.success) {
                    ArtMaxPermissions.refreshGroupsTab();
                } else {
                    alert('Ошибка: ' + (response.error || 'Неизвестная ошибка'));
                }
            }
        });
    },

    refreshGroupsTab: function() {
        console.log('Starting refreshGroupsTab');
        // Обновляем только вкладку групп через AJAX
        BX.ajax({
            url: window.location.href,
            method: 'POST',
            data: {
                ajax_action: 'get_groups_list'
            },
            dataType: 'json',
            onsuccess: function(response) {
                console.log('refreshGroupsTab response:', response);
                if (response.success && response.html) {
                    console.log('Updating groups container with HTML length:', response.html.length);
                    // Обновляем содержимое вкладки групп
                    var groupsTab = document.getElementById('tab-groups');
                    if (groupsTab) {
                        // Находим контейнер со списком групп и обновляем его
                        var groupsContainer = groupsTab.querySelector('.artmax-groups-list');
                        console.log('Found groups container:', groupsContainer);
                        if (groupsContainer) {
                            groupsContainer.innerHTML = response.html;
                            console.log('Groups container updated');
                        } else {
                            console.error('Groups container not found');
                        }
                    } else {
                        console.error('Groups tab not found');
                    }
                } else {
                    console.error('Invalid response:', response);
                    // Если не удалось обновить AJAX'ом, делаем полную перезагрузку
                    BX.reload();
                }
            },
            onfailure: function() {
                // В случае ошибки делаем полную перезагрузку
                BX.reload();
            }
        });
    },
    
    editPermissions: function(groupId) {
        var url = '/bitrix/admin/artmax.calendar_artmax_calendar_group_permissions.php?group_id=' + groupId + '&lang=<?=LANGUAGE_ID?>&ajax=y';
        
        var eventHandler = function(event) {
            console.log('SidePanel closed, reloading page to update permissions');
            BX.removeCustomEvent('SidePanel.Slider:onClose', eventHandler);
            setTimeout(function() {
                BX.reload();
            }, 500);
        };
        
        BX.addCustomEvent('SidePanel.Slider:onClose', eventHandler);
        
        BX.SidePanel.Instance.open(url, {
            width: 800,
            cacheable: false,
            allowChangeHistory: false
        });
    },
    
    viewUsers: function(groupId) {
        var url = '/bitrix/admin/artmax.calendar_artmax_calendar_group_users.php?group_id=' + groupId + '&lang=<?=LANGUAGE_ID?>';
        BX.SidePanel.Instance.open(url, {
            width: 800,
            cacheable: false
        });
    },
    
    linkBitrixGroup: function(calendarGroupId) {
        console.log('linkBitrixGroup called with groupId:', calendarGroupId);
        
        var ajaxUrl = window.location.pathname + '?lang=<?=LANGUAGE_ID?>';
        
        BX.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                ajax_action: 'get_all_bitrix_groups',
                calendar_group_id: calendarGroupId
            },
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            onsuccess: function(response) {
                console.log('AJAX response:', response);
                
                if (!response) {
                    console.error('Пустой ответ от сервера');
                    alert('Ошибка: пустой ответ от сервера');
                    return;
                }
                
                if (!response.success) {
                    console.error('Ошибка в ответе:', response);
                    var errorMessage = 'Неизвестная ошибка';
                    if (response.error) {
                        errorMessage = typeof response.error === 'string' ? response.error : JSON.stringify(response.error);
                    }
                    alert('Ошибка загрузки групп: ' + errorMessage);
                    return;
                }
                
                if (response.success && response.groups) {
                    var linkedGroupIds = response.linked_group_ids || [];
                    var linkedGroups = response.linked_groups || [];
                    
                    var availableGroups = response.groups.filter(function(group) {
                        return linkedGroupIds.indexOf(group.ID) === -1;
                    });
                    
                    var options = availableGroups.map(function(group) {
                        return '<option value="' + group.ID + '">' + BX.util.htmlspecialchars(group.NAME) + '</option>';
                    }).join('');
                    
                    if (availableGroups.length === 0) {
                        alert('Все доступные группы Bitrix уже привязаны к этой группе календаря.');
                        return;
                    }
                    
                    var select = document.createElement('select');
                    select.innerHTML = '<option value="">Выберите группу...</option>' + options;
                    select.style.width = '100%';
                    select.style.marginBottom = '10px';
                    select.style.padding = '5px';
                    
                    var contentDiv = document.createElement('div');
                    contentDiv.style.padding = '20px';
                    
                    var title = document.createElement('h3');
                    title.textContent = 'Привязать группу Bitrix';
                    contentDiv.appendChild(title);
                    
                    var description = document.createElement('p');
                    description.textContent = 'Выберите группу, пользователи из которой будут автоматически добавлены в группу календаря:';
                    contentDiv.appendChild(description);
                    
                    if (linkedGroups.length > 0) {
                        var linkedInfo = document.createElement('div');
                        linkedInfo.style.marginTop = '15px';
                        linkedInfo.style.marginBottom = '15px';
                        linkedInfo.style.padding = '10px';
                        linkedInfo.style.backgroundColor = '#f0f0f0';
                        linkedInfo.style.borderRadius = '4px';
                        linkedInfo.style.fontSize = '12px';
                        
                        var linkedTitle = document.createElement('strong');
                        linkedTitle.textContent = 'Уже привязанные группы: ';
                        linkedInfo.appendChild(linkedTitle);
                        
                        var linkedNames = linkedGroups.map(function(g) { return g.NAME; }).join(', ');
                        var linkedText = document.createTextNode(linkedNames);
                        linkedInfo.appendChild(linkedText);
                        
                        contentDiv.appendChild(linkedInfo);
                    }
                    
                    contentDiv.appendChild(select);
                    
                    if (typeof BX.PopupWindow !== 'undefined') {
                        var dialog = new BX.PopupWindow('link_group_dialog', null, {
                            content: contentDiv,
                            width: 500,
                            height: 300,
                            buttons: [
                                new BX.PopupWindowButton({
                                    text: 'Привязать',
                                    className: 'ui-btn ui-btn-success',
                                    events: {
                                        click: function() {
                                            var selectedGroupId = select.value;
                                            if (!selectedGroupId) {
                                                BX.UI.Notification.show('Выберите группу', {type: 'error'});
                                                return;
                                            }
                                            
                                            BX.ajax({
                                                url: ajaxUrl,
                                                method: 'POST',
                                                data: {
                                                    ajax_action: 'link_bitrix_group',
                                                    calendar_group_id: calendarGroupId,
                                                    bitrix_group_id: selectedGroupId
                                                },
                                                dataType: 'json',
                                                headers: {
                                                    'X-Requested-With': 'XMLHttpRequest'
                                                },
                                                onsuccess: function(response) {
                                                    console.log('Link response:', response);
                                                    if (response && response.success) {
                                                        dialog.close();
                                                        ArtMaxPermissions.refreshGroupsTab();
                                                    } else {
                                                        var errorMsg = 'Неизвестная ошибка';
                                                        if (response && response.error) {
                                                            errorMsg = typeof response.error === 'string' ? response.error : JSON.stringify(response.error);
                                                        }
                                                        alert('Ошибка: ' + errorMsg);
                                                    }
                                                },
                                                onfailure: function(data, errorText, errorThrown) {
                                                    console.error('Link request failed:', {data: data, errorText: errorText, errorThrown: errorThrown});
                                                    alert('Ошибка при выполнении запроса: ' + (errorText || 'Неизвестная ошибка'));
                                                }
                                            });
                                        }
                                    }
                                }),
                                new BX.PopupWindowButtonLink({
                                    text: 'Отмена',
                                    className: 'ui-btn ui-btn-link',
                                    events: {
                                        click: function() {
                                            dialog.close();
                                        }
                                    }
                                })
                            ]
                        });
                        dialog.show();
                    }
                } else {
                    alert('Ошибка загрузки групп: ' + (response.error || 'Неизвестная ошибка'));
                }
            },
            onfailure: function(data, errorText, errorThrown) {
                console.error('AJAX request failed:', {data: data, errorText: errorText, errorThrown: errorThrown});
                alert('Ошибка при загрузке списка групп Bitrix: ' + (errorText || 'Неизвестная ошибка'));
            }
        });
    },
    
    unlinkBitrixGroup: function(calendarGroupId, bitrixGroupId) {
        if (!confirm('Вы уверены, что хотите отвязать эту группу?')) {
            return;
        }
        
        var ajaxUrl = window.location.pathname + '?lang=<?=LANGUAGE_ID?>';
        
        BX.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                ajax_action: 'unlink_bitrix_group',
                calendar_group_id: calendarGroupId,
                bitrix_group_id: bitrixGroupId
            },
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            onsuccess: function(response) {
                console.log('Unlink response:', response);
                if (response && response.success) {
                    ArtMaxPermissions.refreshGroupsTab();
                } else {
                    var errorMsg = 'Неизвестная ошибка';
                    if (response && response.error) {
                        errorMsg = typeof response.error === 'string' ? response.error : JSON.stringify(response.error);
                    }
                    alert('Ошибка: ' + errorMsg);
                }
            },
            onfailure: function(data, errorText, errorThrown) {
                console.error('Unlink request failed:', {data: data, errorText: errorText, errorThrown: errorThrown});
                alert('Ошибка при выполнении запроса: ' + (errorText || 'Неизвестная ошибка'));
            }
        });
    }
};

// Переключение вкладок - простой и надежный вариант
(function() {
    function switchTab(tabName) {
        // Убираем активный класс у всех вкладок
        var tabs = document.querySelectorAll('.artmax-tab-btn');
        for (var i = 0; i < tabs.length; i++) {
            tabs[i].classList.remove('artmax-tab-active');
        }
        
        // Убираем активный класс у всего контента
        var contents = document.querySelectorAll('.artmax-settings-tab-content');
        for (var i = 0; i < contents.length; i++) {
            contents[i].classList.remove('active');
        }
        
        // Активируем нужную вкладку
        var activeTab = document.querySelector('.artmax-tab-btn[data-tab="' + tabName + '"]');
        if (activeTab) {
            activeTab.classList.add('artmax-tab-active');
        }
        
        // Активируем нужный контент
        var activeContent = document.getElementById('tab-' + tabName);
        if (activeContent) {
            activeContent.classList.add('active');
        }
    }
    
    function initTabs() {
        var tabs = document.querySelectorAll('.artmax-tab-btn[data-tab]');
        
        if (tabs.length === 0) {
            setTimeout(initTabs, 100);
            return;
        }
        
        for (var i = 0; i < tabs.length; i++) {
            (function(tab) {
                var tabName = tab.getAttribute('data-tab');
                if (!tabName) return;
                
                // Удаляем все обработчики через клонирование
                var newTab = tab.cloneNode(true);
                tab.parentNode.replaceChild(newTab, tab);
                
                // Добавляем обработчик
                newTab.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    switchTab(tabName);
                    return false;
                });
            })(tabs[i]);
        }
    }
    
    // Инициализация
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTabs);
    } else {
        setTimeout(initTabs, 50);
    }
    
    if (typeof BX !== 'undefined') {
        BX.ready(initTabs);
    }
})();

// JavaScript для управления филиалами
var ArtMaxBranches = {
    createBranch: function() {
        var url = '/local/components/artmax/branch.form/page.php?IFRAME=Y&IFRAME_TYPE=SIDE_SLIDER';

        // Закрываем предыдущий SidePanel если он открыт
        if (BX.SidePanel.Instance) {
            BX.SidePanel.Instance.close();
        }

        // Небольшая задержка перед открытием нового SidePanel
        setTimeout(function() {
            BX.SidePanel.Instance.open(url, {
                width: 800,
                cacheable: false,
                allowChangeHistory: false,
                events: {
                    onClose: function(event) {
                        // Обновление происходит через postMessage от дочернего окна
                        console.log('SidePanel closed (createBranch), waiting for postMessage');
                    }
                }
            });
        }, 100);
    },
    
    editBranch: function(branchId) {
        var url = '/local/components/artmax/branch.settings/page.php?BRANCH_ID=' + branchId + '&IFRAME=Y&IFRAME_TYPE=SIDE_SLIDER';

        // Закрываем предыдущий SidePanel если он открыт
        if (BX.SidePanel.Instance) {
            BX.SidePanel.Instance.close();
        }

        // Небольшая задержка перед открытием нового SidePanel
        setTimeout(function() {
            BX.SidePanel.Instance.open(url, {
                width: 800,
                cacheable: false,
                allowChangeHistory: false,
                events: {
                    onClose: function(event) {
                        // Обновление происходит через postMessage от дочернего окна
                        console.log('SidePanel closed (editBranch), waiting for postMessage');
                    }
                }
            });
        }, 100);
    },
    
    deleteBranch: function(branchId) {
        if (!confirm('Вы уверены, что хотите удалить этот филиал? Все связанные данные будут удалены.')) {
            return;
        }

        BX.ajax({
            url: window.location.href,
            method: 'POST',
            data: {
                ajax_action: 'delete_branch',
                branch_id: branchId
            },
            dataType: 'json',
            onsuccess: function(response) {
                if (response.success) {
                    ArtMaxBranches.refreshBranchesTab();
                } else {
                    alert('Ошибка: ' + (response.error || 'Неизвестная ошибка'));
                }
            }
        });
    },

    refreshBranchesTab: function() {
        console.log('Starting refreshBranchesTab AJAX request');
        // Обновляем только вкладку филиалов без перезагрузки всей страницы
        BX.ajax({
            url: window.location.href,
            method: 'POST',
            data: {
                ajax_action: 'get_branches_list'
            },
            dataType: 'json',
            onsuccess: function(response) {
                console.log('refreshBranchesTab response:', response);
                if (response.success && response.html) {
                    console.log('Updating branches container with new HTML');
                    // Обновляем содержимое вкладки филиалов
                    var branchesTab = document.getElementById('tab-branches');
                    if (branchesTab) {
                        // Находим контейнер со списком филиалов (div с margin-top: 20px)
                        var branchesContainer = branchesTab.querySelector('div[style*="margin-top: 20px"]');
                        console.log('Found branches container:', branchesContainer);
                        if (branchesContainer) {
                            branchesContainer.innerHTML = response.html;
                            console.log('Branches container updated successfully');
                        } else {
                            console.error('Branches container not found');
                        }
                    } else {
                        console.error('Branches tab not found');
                    }
                } else {
                    console.error('Invalid response or no HTML:', response);
                    // Если не удалось обновить AJAX'ом, делаем полную перезагрузку
                    BX.reload();
                }
            },
            onfailure: function() {
                console.error('AJAX request failed for refreshBranchesTab');
                // В случае ошибки делаем полную перезагрузку
                BX.reload();
            }
        });
    }
};
</script>

<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
?>
