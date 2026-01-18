<?php
// use statements должны быть в самом начале файла
use Bitrix\Main\Localization\Loc;
use Artmax\Calendar\Permissions;

// Обработка AJAX запросов - ДОЛЖНА БЫТЬ САМОЙ ПЕРВОЙ, ДО ВСЕХ require
// Проверяем, что это AJAX запрос (через POST параметр или заголовок)
$isAjaxRequest = (
    ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) ||
    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
    (isset($_GET['ajax_action']))
);

if ($isAjaxRequest) {
    // Для AJAX запросов устанавливаем заголовки ПЕРЕД любым выводом
    // Это должно быть ДО всех require, которые могут выводить HTML
    ob_start(); // Начинаем буферизацию вывода
    
    // Минимальная инициализация Bitrix для AJAX
    require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
    
    // Очищаем буфер, если что-то было выведено
    ob_clean();
    
    // Устанавливаем заголовки ПОСЛЕ очистки буфера
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    
    if (!CModule::IncludeModule('artmax.calendar')) {
        echo json_encode(['success' => false, 'error' => 'Модуль artmax.calendar не установлен']);
        die();
    }
    
    $permissionsObj = new Permissions();
    
    // Проверка прав доступа
    global $USER;
    if (!$USER || !$USER->IsAuthorized()) {
        echo json_encode(['success' => false, 'error' => 'Необходима авторизация']);
        die();
    }
    
    $hasPermission = false;
    try {
        $hasPermission = $permissionsObj->hasPermission($USER->GetID(), 'calendar.manage_groups') || $USER->IsAdmin();
    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Ошибка проверки прав доступа: ' . $e->getMessage()]);
        die();
    }
    
    if (!$hasPermission) {
        echo json_encode(['success' => false, 'error' => 'У вас нет прав для управления группами и правами доступа']);
        die();
    }
    
    // Получаем действие из POST или GET
    $action = $_POST['ajax_action'] ?? $_GET['ajax_action'] ?? '';
    $response = ['success' => false, 'error' => ''];

    switch ($action) {
        case 'create_group':
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
            
        case 'assign_permissions':
            $groupId = (int)($_POST['group_id'] ?? 0);
            $permissionIds = $_POST['permission_ids'] ?? [];
            
            if ($groupId > 0) {
                $response = $permissionsObj->assignPermissionsToGroup($groupId, $permissionIds);
            } else {
                $response['error'] = 'Не указан ID группы';
            }
            break;
            
        case 'get_group_permissions':
            $groupId = (int)($_POST['group_id'] ?? 0);
            if ($groupId > 0) {
                $permissions = $permissionsObj->getGroupPermissions($groupId);
                $response = ['success' => true, 'permissions' => $permissions];
            } else {
                $response['error'] = 'Не указан ID группы';
            }
            break;
            
        case 'get_group_users':
            $groupId = (int)($_POST['group_id'] ?? 0);
            if ($groupId > 0) {
                $users = $permissionsObj->getGroupUsers($groupId);
                $response = ['success' => true, 'users' => $users];
            } else {
                $response['error'] = 'Не указан ID группы';
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
            
        case 'get_linked_groups':
            $calendarGroupId = (int)($_POST['calendar_group_id'] ?? 0);
            if ($calendarGroupId > 0) {
                $linkedGroups = $permissionsObj->getLinkedBitrixGroups($calendarGroupId);
                $response = ['success' => true, 'linked_groups' => $linkedGroups];
            } else {
                $response['error'] = 'Не указан ID группы календаря';
            }
            break;
            
        case 'get_all_bitrix_groups':
            try {
                $calendarGroupId = (int)($_POST['calendar_group_id'] ?? $_GET['calendar_group_id'] ?? 0);
                $allGroups = $permissionsObj->getAllBitrixGroups();
                
                // Получаем уже привязанные группы для этой группы календаря
                $linkedGroups = [];
                if ($calendarGroupId > 0) {
                    $linkedGroups = $permissionsObj->getLinkedBitrixGroups($calendarGroupId);
                }
                $linkedGroupIds = array_column($linkedGroups, 'BITRIX_GROUP_ID');
                
                if ($allGroups === false || $allGroups === null) {
                    $response = [
                        'success' => false,
                        'error' => 'Метод getAllBitrixGroups вернул неверное значение'
                    ];
                } else {
                    $response = [
                        'success' => true, 
                        'groups' => $allGroups,
                        'linked_groups' => $linkedGroups,
                        'linked_group_ids' => $linkedGroupIds
                    ];
                }
            } catch (\Exception $e) {
                error_log('Ошибка в get_all_bitrix_groups: ' . $e->getMessage());
                error_log('Stack trace: ' . $e->getTraceAsString());
                $response = [
                    'success' => false,
                    'error' => 'Ошибка получения списка групп: ' . $e->getMessage()
                ];
            } catch (\Error $e) {
                error_log('Fatal error в get_all_bitrix_groups: ' . $e->getMessage());
                error_log('Stack trace: ' . $e->getTraceAsString());
                $response = [
                    'success' => false,
                    'error' => 'Критическая ошибка: ' . $e->getMessage()
                ];
            }
            break;
            
        default:
            $response['error'] = 'Неизвестное действие';
    }
    
    // Убеждаемся, что ошибка - это строка
    if (isset($response['error']) && !is_string($response['error'])) {
        if (is_object($response['error']) || is_array($response['error'])) {
            $response['error'] = 'Ошибка выполнения запроса';
        } else {
            $response['error'] = (string)$response['error'];
        }
    }
    
    // Убеждаемся, что ошибка - это строка
    if (isset($response['error']) && !is_string($response['error'])) {
        if (is_object($response['error']) || is_array($response['error'])) {
            $response['error'] = 'Ошибка выполнения запроса';
        } else {
            $response['error'] = (string)$response['error'];
        }
    }
    
    // Убеждаемся, что ошибка - это строка
    if (isset($response['error']) && !is_string($response['error'])) {
        if (is_object($response['error']) || is_array($response['error'])) {
            $response['error'] = 'Ошибка выполнения запроса';
        } else {
            $response['error'] = (string)$response['error'];
        }
    }
    
    // Убеждаемся, что ошибка - это строка
    if (isset($response['error']) && !is_string($response['error'])) {
        if (is_object($response['error']) || is_array($response['error'])) {
            $response['error'] = 'Ошибка выполнения запроса';
        } else {
            $response['error'] = (string)$response['error'];
        }
    }
    
    // Очищаем буфер и выводим JSON
    ob_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    // НЕ подключаем epilog_admin для AJAX запросов
    die();
}

// Если это не AJAX запрос, продолжаем обычную обработку страницы
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php');

Loc::loadMessages(__FILE__);

if (!CModule::IncludeModule('artmax.calendar')) {
    ShowError('Модуль artmax.calendar не установлен');
    require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
    die();
}

$permissionsObj = new Permissions();

// Проверка прав доступа
global $USER;
if (!$permissionsObj->hasPermission($USER->GetID(), 'calendar.manage_groups') && !$USER->IsAdmin()) {
    ShowError('У вас нет прав для управления группами и правами доступа');
    require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
    die();
}

$APPLICATION->SetTitle('Управление группами и правами доступа');

// Получаем данные
$groups = $permissionsObj->getCalendarGroups();

// Для каждой группы получаем привязанные группы Bitrix
foreach ($groups as &$group) {
    $group['LINKED_GROUPS'] = $permissionsObj->getLinkedBitrixGroups($group['GROUP_ID']);
}
unset($group);

$allPermissions = $permissionsObj->getAllPermissions();

CJSCore::Init(['jquery', 'ui.buttons', 'ui.dialogs.message', 'popup']);
?>

<script>
var ArtMaxPermissions = {
    createGroup: function() {
        var name = prompt('Введите название группы:');
        if (!name || name.trim() === '') {
            return;
        }
        
        var description = prompt('Введите описание группы (необязательно):', '');
        
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
                if (response.success) {
                    BX.reload();
                } else {
                    alert('Ошибка: ' + (response.error || 'Неизвестная ошибка'));
                }
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
                    BX.reload();
                } else {
                    alert('Ошибка: ' + (response.error || 'Неизвестная ошибка'));
                }
            }
        });
    },
    
    editPermissions: function(groupId) {
        var url = '/bitrix/admin/artmax.calendar_artmax_calendar_group_permissions.php?group_id=' + groupId + '&lang=<?=LANGUAGE_ID?>&ajax=y';
        
        // Слушаем событие закрытия SidePanel для обновления страницы
        // Используем уникальный идентификатор для этого конкретного SidePanel
        var eventHandler = function(event) {
            console.log('SidePanel closed, reloading page to update permissions');
            // Удаляем обработчик после первого срабатывания
            BX.removeCustomEvent('SidePanel.Slider:onClose', eventHandler);
            // Обновляем страницу для обновления счетчиков прав
            setTimeout(function() {
                BX.reload();
            }, 500);
        };
        
        BX.addCustomEvent('SidePanel.Slider:onClose', eventHandler);
        
        // Открываем SidePanel с параметрами, предотвращающими редирект
        BX.SidePanel.Instance.open(url, {
            width: 800,
            cacheable: false,
            allowChangeHistory: false,
            events: {
                onClose: function(event) {
                    console.log('SidePanel onClose callback fired');
                    // Обновляем страницу после закрытия
                    setTimeout(function() {
                        BX.reload();
                    }, 500);
                }
            }
        });
    },
    
    viewUsers: function(groupId) {
        var url = '/bitrix/admin/artmax.calendar_artmax_calendar_group_users.php?group_id=' + groupId + '&lang=<?=LANGUAGE_ID?>';
        BX.SidePanel.Instance.open(url, {
            width: 800,
            cacheable: false
        });
    },
    
    refreshGroupsList: function() {
        // Перезагружаем страницу для обновления списка групп
        BX.reload();
    },
    
    updateGroupItem: function(groupId) {
        // Обновляет информацию о конкретной группе через AJAX
        console.log('Updating group item for groupId:', groupId);
        
        BX.ajax({
            url: window.location.href,
            method: 'POST',
            data: {
                ajax_action: 'get_group_info',
                group_id: groupId
            },
            dataType: 'json',
            onsuccess: function(response) {
                if (response && response.success && response.group) {
                    // Находим элемент группы
                    var groupItem = document.querySelector('.artmax-group-item[data-group-id="' + groupId + '"]');
                    if (!groupItem) {
                        // Если элемент не найден по data-атрибуту, ищем по кнопке
                        var buttons = document.querySelectorAll('button[onclick*="editPermissions(' + groupId + ')"]');
                        if (buttons.length > 0) {
                            groupItem = buttons[0].closest('.artmax-group-item');
                        }
                    }
                    
                    if (groupItem) {
                        // Обновляем информацию о правах
                        var infoDiv = groupItem.querySelector('.artmax-group-info');
                        if (infoDiv) {
                            var permissionsCount = response.group.permissions_count || 0;
                            var usersCount = response.group.users_count || 0;
                            
                            // Обновляем счетчик прав
                            var permissionsText = infoDiv.querySelector('div[style*="color: #666"]');
                            if (permissionsText) {
                                var linkedGroupsText = '';
                                if (response.group.linked_groups && response.group.linked_groups.length > 0) {
                                    linkedGroupsText = ' | Привязанных групп: ' + response.group.linked_groups.length;
                                }
                                permissionsText.textContent = 'Права: ' + permissionsCount + ' | Пользователей: ' + usersCount + linkedGroupsText;
                            }
                            
                            console.log('Group item updated successfully');
                        } else {
                            console.log('Info div not found, reloading page');
                            BX.reload();
                        }
                    } else {
                        console.log('Group item not found, reloading page');
                        BX.reload();
                    }
                } else {
                    console.log('Invalid response, reloading page');
                    BX.reload();
                }
            },
            onfailure: function() {
                console.log('AJAX failed, reloading page');
                BX.reload();
            }
        });
    },
    
    linkBitrixGroup: function(calendarGroupId) {
        console.log('linkBitrixGroup called with groupId:', calendarGroupId);
        
        // Загружаем список всех групп Bitrix
        // Используем чистый URL без параметров IFRAME
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
                
                // Проверяем формат ответа
                if (!response) {
                    console.error('Пустой ответ от сервера');
                    alert('Ошибка: пустой ответ от сервера');
                    return;
                }
                
                // Проверяем наличие ошибки
                if (!response.success) {
                    console.error('Ошибка в ответе:', response);
                    var errorMessage = 'Неизвестная ошибка';
                    if (response.error) {
                        if (typeof response.error === 'string') {
                            errorMessage = response.error;
                        } else if (typeof response.error === 'object') {
                            errorMessage = JSON.stringify(response.error);
                        } else {
                            errorMessage = String(response.error);
                        }
                    }
                    alert('Ошибка загрузки групп: ' + errorMessage);
                    return;
                }
                
                if (response.success && response.groups) {
                    // Получаем ID уже привязанных групп
                    var linkedGroupIds = response.linked_group_ids || [];
                    var linkedGroups = response.linked_groups || [];
                    
                    // Фильтруем группы: исключаем уже привязанные
                    var availableGroups = response.groups.filter(function(group) {
                        return linkedGroupIds.indexOf(group.ID) === -1;
                    });
                    
                    // Показываем диалог выбора группы
                    var options = availableGroups.map(function(group) {
                        return '<option value="' + group.ID + '">' + BX.util.htmlspecialchars(group.NAME) + '</option>';
                    }).join('');
                    
                    // Если все группы уже привязаны
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
                    
                    // Показываем уже привязанные группы
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
                    
                    // Используем BX.PopupWindow если доступен, иначе простой prompt
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
                                                        // Перезагружаем страницу для обновления списка
                                                        BX.reload();
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
                    } else {
                        // Fallback: используем простой prompt
                        var groupNames = availableGroups.map(function(g) { return g.NAME; }).join('\n');
                        var promptText = 'Введите название группы для привязки:\n\nДоступные группы:\n' + groupNames;
                        if (linkedGroups.length > 0) {
                            promptText += '\n\nУже привязанные: ' + linkedGroups.map(function(g) { return g.NAME; }).join(', ');
                        }
                        var selectedName = prompt(promptText);
                        if (selectedName) {
                            var selectedGroup = availableGroups.find(function(g) { return g.NAME === selectedName; });
                            if (selectedGroup) {
                                BX.ajax({
                                    url: ajaxUrl,
                                    method: 'POST',
                                    data: {
                                        ajax_action: 'link_bitrix_group',
                                        calendar_group_id: calendarGroupId,
                                        bitrix_group_id: selectedGroup.ID
                                    },
                                    dataType: 'json',
                                    headers: {
                                        'X-Requested-With': 'XMLHttpRequest'
                                    },
                                    onsuccess: function(response) {
                                        if (response && response.success) {
                                            BX.reload();
                                        } else {
                                            var errorMsg = 'Неизвестная ошибка';
                                            if (response && response.error) {
                                                errorMsg = typeof response.error === 'string' ? response.error : JSON.stringify(response.error);
                                            }
                                            alert('Ошибка: ' + errorMsg);
                                        }
                                    },
                                    onfailure: function(data, errorText) {
                                        alert('Ошибка при выполнении запроса: ' + (errorText || 'Неизвестная ошибка'));
                                    }
                                });
                            } else {
                                alert('Группа не найдена');
                            }
                        }
                    }
                } else {
                    alert('Ошибка загрузки групп: ' + (response.error || 'Неизвестная ошибка'));
                }
            },
            onfailure: function(data, errorText, errorThrown) {
                console.error('AJAX request failed:', {
                    data: data,
                    errorText: errorText,
                    errorThrown: errorThrown
                });
                alert('Ошибка при загрузке списка групп Bitrix: ' + (errorText || 'Неизвестная ошибка'));
            }
        });
    },
    
    unlinkBitrixGroup: function(calendarGroupId, bitrixGroupId) {
        if (!confirm('Вы уверены, что хотите отвязать эту группу?')) {
            return;
        }
        
        // Используем чистый URL без параметров IFRAME
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
                    // Перезагружаем страницу для обновления списка
                    BX.reload();
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
</script>

<style>
.artmax-permissions-container {
    padding: 20px;
}

.artmax-permissions-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.artmax-groups-list {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    padding: 15px;
}

.artmax-group-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border-bottom: 1px solid #f0f0f0;
}

.artmax-group-item:last-child {
    border-bottom: none;
}

.artmax-group-info {
    flex: 1;
}

.artmax-group-name {
    font-weight: bold;
    font-size: 16px;
    margin-bottom: 5px;
}

.artmax-group-actions {
    display: flex;
    gap: 10px;
}

.artmax-permissions-info {
    margin-top: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 4px;
}

.artmax-permissions-list {
    margin-top: 10px;
}

.artmax-permission-item {
    padding: 5px 0;
    border-bottom: 1px solid #e0e0e0;
}

.artmax-permission-item:last-child {
    border-bottom: none;
}
</style>

<div class="artmax-permissions-container">
    <div class="artmax-permissions-header">
        <h2>Группы пользователей календаря</h2>
        <button class="ui-btn ui-btn-success" onclick="ArtMaxPermissions.createGroup()">
            Создать группу
        </button>
    </div>
    
    <div class="artmax-groups-list">
        <?php if (empty($groups)): ?>
            <p>Группы не найдены. Создайте первую группу.</p>
        <?php else: ?>
            <?php foreach ($groups as $group): ?>
                <div class="artmax-group-item" data-group-id="<?= $group['GROUP_ID'] ?>">
                    <div class="artmax-group-info">
                        <div class="artmax-group-name">
                            <?= htmlspecialchars($group['GROUP_NAME']) ?>
                            <?php if ($group['ACTIVE'] !== 'Y'): ?>
                                <span style="color: #999;">(неактивна)</span>
                            <?php endif; ?>
                        </div>
                        <?php
                        $groupPermissions = $permissionsObj->getGroupPermissions($group['GROUP_ID']);
                        $groupUsers = $permissionsObj->getGroupUsers($group['GROUP_ID']);
                        
                        // Проверяем права доступа к файлам Bitrix
                        $fileAccessRights = $permissionsObj->checkBitrixFileAccessRights($group['GROUP_ID']);
                        $allFoldersHaveAccess = $fileAccessRights['/page/'] && $fileAccessRights['/local/components/artmax/'] && $fileAccessRights['/local/modules/artmax.calendar/'];
                        ?>
                        <div style="color: #666; font-size: 14px; margin-top: 5px;">
                            Права: <?= count($groupPermissions) ?> | 
                            Пользователей: <?= count($groupUsers) ?>
                            <?php if (!empty($group['LINKED_GROUPS'])): ?>
                                | Привязанных групп: <?= count($group['LINKED_GROUPS']) ?>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($group['LINKED_GROUPS'])): ?>
                            <div style="margin-top: 8px; padding: 8px; background: <?= $allFoldersHaveAccess ? '#e8f5e9' : '#fff3e0' ?>; border-radius: 4px; font-size: 12px; border-left: 3px solid <?= $allFoldersHaveAccess ? '#4caf50' : '#ff9800' ?>;">
                                <strong>Права доступа к файлам Bitrix:</strong>
                                <div style="margin-top: 4px;">
                                    <span style="display: inline-block; margin: 2px 5px; padding: 2px 6px; background: <?= $fileAccessRights['/page/'] ? '#c8e6c9' : '#ffccbc' ?>; border-radius: 3px; font-size: 11px;">
                                        /page/ <?= $fileAccessRights['/page/'] ? '✓' : '✗' ?>
                                    </span>
                                    <span style="display: inline-block; margin: 2px 5px; padding: 2px 6px; background: <?= $fileAccessRights['/local/components/artmax/'] ? '#c8e6c9' : '#ffccbc' ?>; border-radius: 3px; font-size: 11px;">
                                        /local/components/artmax/ <?= $fileAccessRights['/local/components/artmax/'] ? '✓' : '✗' ?>
                                    </span>
                                    <span style="display: inline-block; margin: 2px 5px; padding: 2px 6px; background: <?= $fileAccessRights['/local/modules/artmax.calendar/'] ? '#c8e6c9' : '#ffccbc' ?>; border-radius: 3px; font-size: 11px;">
                                        /local/modules/artmax.calendar/ <?= $fileAccessRights['/local/modules/artmax.calendar/'] ? '✓' : '✗' ?>
                                    </span>
                                </div>
                                <?php if (!$allFoldersHaveAccess): ?>
                                    <div style="margin-top: 6px; color: #e65100; font-size: 11px;">
                                        ⚠️ Необходимо выдать права "Чтение" для всех папок (см. <a href="/bitrix/admin/fileman_file_access.php" target="_blank" style="color: #1976d2; text-decoration: underline;">Права доступа</a>)
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
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
                    <div class="artmax-group-actions">
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
    
    <div class="artmax-permissions-info">
        <h3>Доступные права доступа</h3>
        <p>Для добавления новых прав обратитесь к администратору базы данных.</p>
        <div class="artmax-permissions-list">
            <?php foreach ($allPermissions as $permission): ?>
                <div class="artmax-permission-item">
                    <strong><?= htmlspecialchars($permission['CODE']) ?></strong> - 
                    <?= htmlspecialchars($permission['NAME']) ?>
                    <?php if ($permission['DESCRIPTION']): ?>
                        <br><span style="color: #666; font-size: 12px;"><?= htmlspecialchars($permission['DESCRIPTION']) ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
?>
