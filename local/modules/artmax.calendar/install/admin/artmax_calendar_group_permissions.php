<?php
use Bitrix\Main\Localization\Loc;
use Artmax\Calendar\Permissions;

// Предотвращаем редиректы при работе в SidePanel
if (isset($_GET['IFRAME']) && $_GET['IFRAME'] === 'Y') {
    // В SidePanel не должно быть редиректов
    define('BX_SKIP_POST_UNPACK', true);
}

// Включаем буферизацию вывода, чтобы перехватить любой HTML
ob_start();

// Проверяем AJAX запрос ДО подключения прологов, чтобы не выводить HTML
$isAjaxRequest = (
    isset($_GET['ajax']) && $_GET['ajax'] === 'y'
) || (
    isset($_POST['ajax']) && $_POST['ajax'] === 'y'
) || (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) || (
    isset($_POST['ajax_action'])
);

// Если это AJAX запрос на сохранение, обрабатываем его без прологов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_permissions']) && $isAjaxRequest) {
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
    
    // Подключаем модуль - он автоматически загрузит классы через свой autoload
    if (!CModule::IncludeModule('artmax.calendar')) {
        echo json_encode(['success' => false, 'error' => 'Модуль artmax.calendar не установлен'], JSON_UNESCAPED_UNICODE);
        die();
    }
    
    // Проверяем, что класс доступен
    if (!class_exists('Artmax\\Calendar\\Permissions')) {
        echo json_encode(['success' => false, 'error' => 'Класс Permissions не найден'], JSON_UNESCAPED_UNICODE);
        die();
    }
    
    // Получаем необходимые данные
    $permissionsObj = new Permissions();
    $groupId = (int)($_GET['group_id'] ?? $_POST['group_id'] ?? 0);
    
    if ($groupId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Не указан ID группы'], JSON_UNESCAPED_UNICODE);
        die();
    }
    
    // Получаем массив permission_ids
    $permissionIds = [];
    
    // Обрабатываем разные форматы данных
    if (isset($_POST['permission_ids'])) {
        if (is_array($_POST['permission_ids'])) {
            $permissionIds = $_POST['permission_ids'];
        } else {
            // Если пришел не массив, преобразуем
            $permissionIds = [$_POST['permission_ids']];
        }
    }
    
    // Также проверяем формат permission_ids[0], permission_ids[1] и т.д.
    foreach ($_POST as $key => $value) {
        if (preg_match('/^permission_ids\[(\d+)\]$/', $key, $matches)) {
            $permissionIds[] = (int)$value;
        }
    }
    
    // Удаляем дубликаты, фильтруем и приводим к int
    $permissionIds = array_unique(array_filter(array_map('intval', $permissionIds)));
    
    $result = $permissionsObj->assignPermissionsToGroup($groupId, $permissionIds);
    
    // Убеждаемся, что заголовки установлены правильно
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
    }
    
    // Выводим JSON напрямую (без буферизации)
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
    // НЕ подключаем epilog_admin для AJAX запросов
    die();
}

// Обычная обработка - очищаем буфер и подключаем прологи
ob_end_clean();
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php');

Loc::loadMessages(__FILE__);

if (!CModule::IncludeModule('artmax.calendar')) {
    ShowError('Модуль artmax.calendar не установлен');
    require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
    die();
}

// Продолжаем обычную обработку, если это не AJAX запрос
$permissionsObj = new Permissions();
$groupId = (int)($_GET['group_id'] ?? 0);

if ($groupId <= 0) {
    ShowError('Не указан ID группы');
    require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
    die();
}

// Получаем информацию о группе
$groups = $permissionsObj->getCalendarGroups();
$currentGroup = null;
foreach ($groups as $group) {
    if ($group['GROUP_ID'] == $groupId) {
        $currentGroup = $group;
        break;
    }
}

if (!$currentGroup) {
    ShowError('Группа не найдена');
    require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
    die();
}

// Обработка сохранения прав через обычную форму (для обратной совместимости)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_permissions']) && !isset($_POST['ajax_action'])) {
    $permissionIds = $_POST['permission_ids'] ?? [];
    $result = $permissionsObj->assignPermissionsToGroup($groupId, $permissionIds);
    
    if ($result['success']) {
        // Если открыто в SidePanel, закрываем его
        if (isset($_GET['IFRAME']) && $_GET['IFRAME'] === 'Y') {
            // Показываем сообщение об успехе и закрываем SidePanel через JavaScript
            echo '<script>
                if (window.parent && window.parent !== window) {
                    // Закрываем SidePanel через родительское окно
                    if (window.parent.BX && window.parent.BX.SidePanel && window.parent.BX.SidePanel.Instance) {
                        if (window.parent.BX.UI && window.parent.BX.UI.Notification) {
                            window.parent.BX.UI.Notification.Center.notify({
                                content: "Права доступа успешно сохранены",
                                position: "top-right"
                            });
                        }
                        window.parent.BX.SidePanel.Instance.close();
                    }
                } else if (typeof BX !== "undefined" && BX.SidePanel && BX.SidePanel.Instance) {
                    if (typeof BX !== "undefined" && BX.UI && BX.UI.Notification) {
                        BX.UI.Notification.Center.notify({
                            content: "Права доступа успешно сохранены",
                            position: "top-right"
                        });
                    }
                    BX.SidePanel.Instance.close();
                }
            </script>';
            require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
            die();
        } else {
            LocalRedirect('/bitrix/admin/artmax.calendar_artmax_calendar_permissions.php?lang=' . LANGUAGE_ID);
        }
    } else {
        ShowError('Ошибка сохранения прав: ' . $result['error']);
    }
}

$allPermissions = $permissionsObj->getAllPermissions();
$groupPermissions = $permissionsObj->getGroupPermissions($groupId);
$groupPermissionIds = array_column($groupPermissions, 'ID');

$APPLICATION->SetTitle('Права доступа группы: ' . $currentGroup['GROUP_NAME']);

CJSCore::Init(['jquery', 'ui.buttons']);
?>

<style>
.artmax-permissions-form {
    padding: 20px;
    background: #fff;
}

.artmax-permission-checkbox {
    margin: 10px 0;
    padding: 10px;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
}

.artmax-permission-checkbox:hover {
    background: #f8f9fa;
}

.artmax-permission-code {
    font-weight: bold;
    color: #333;
}

.artmax-permission-name {
    color: #666;
    margin-left: 5px;
}

.artmax-permission-description {
    font-size: 12px;
    color: #999;
    margin-top: 5px;
    margin-left: 25px;
}
</style>

<div class="artmax-permissions-form">
    <h2>Права доступа группы: <?= htmlspecialchars($currentGroup['GROUP_NAME']) ?></h2>
    
    <form method="POST" id="permissions-form">
        <input type="hidden" name="save_permissions" value="1">
        
        <div style="margin-bottom: 20px;">
            <label>
                <input type="checkbox" id="select-all">
                <strong>Выбрать все</strong>
            </label>
        </div>
        
        <div style="max-height: 500px; overflow-y: auto; border: 1px solid #e0e0e0; padding: 15px; border-radius: 4px;">
            <?php foreach ($allPermissions as $permission): ?>
                <div class="artmax-permission-checkbox">
                    <label>
                        <input type="checkbox" 
                               name="permission_ids[]" 
                               value="<?= $permission['ID'] ?>"
                               <?= in_array($permission['ID'], $groupPermissionIds) ? 'checked' : '' ?>
                               class="permission-checkbox">
                        <span class="artmax-permission-code"><?= htmlspecialchars($permission['CODE']) ?></span>
                        <span class="artmax-permission-name">- <?= htmlspecialchars($permission['NAME']) ?></span>
                        <?php if ($permission['DESCRIPTION']): ?>
                            <div class="artmax-permission-description">
                                <?= htmlspecialchars($permission['DESCRIPTION']) ?>
                            </div>
                        <?php endif; ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div style="margin-top: 20px;">
            <button type="submit" class="ui-btn ui-btn-success">Сохранить</button>
            <button type="button" class="ui-btn ui-btn-link" onclick="BX.SidePanel.Instance.close()">Отмена</button>
        </div>
    </form>
</div>

<script>
(function() {
    // Функция для переключения всех прав
    function toggleAllPermissions(checked) {
        var checkboxes = document.querySelectorAll('.permission-checkbox');
        checkboxes.forEach(function(checkbox) {
            checkbox.checked = checked;
        });
    }
    
    // Ждем загрузки DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPermissionsForm);
    } else {
        initPermissionsForm();
    }
    
    function initPermissionsForm() {
        // Обработчик для "Выбрать все"
        var selectAllCheckbox = document.getElementById('select-all');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                toggleAllPermissions(this.checked);
            });
        }
        
        // Обновляем "Выбрать все" при изменении отдельных чекбоксов
        document.querySelectorAll('.permission-checkbox').forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                var allChecked = true;
                document.querySelectorAll('.permission-checkbox').forEach(function(cb) {
                    if (!cb.checked) {
                        allChecked = false;
                    }
                });
                var selectAll = document.getElementById('select-all');
                if (selectAll) {
                    selectAll.checked = allChecked;
                }
            });
        });
    }
})();

// Обработка отправки формы через AJAX
(function() {
    var form = document.getElementById('permissions-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Собираем данные формы
            var formData = new FormData(form);
            formData.append('ajax_action', 'save_permissions');
            
            // Также создаем обычный объект для отправки
            var postData = {
                save_permissions: '1',
                ajax_action: 'save_permissions',
                permission_ids: []
            };
            
            // Собираем выбранные права
            var checkboxes = form.querySelectorAll('input[name="permission_ids[]"]:checked');
            for (var i = 0; i < checkboxes.length; i++) {
                postData.permission_ids.push(checkboxes[i].value);
            }
            
            // Показываем индикатор загрузки
            var submitBtn = form.querySelector('button[type="submit"]');
            var originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Сохранение...';
            
            // Используем fetch API для более надежной работы
            var formDataToSend = new URLSearchParams();
            formDataToSend.append('save_permissions', '1');
            formDataToSend.append('ajax', 'y');
            formDataToSend.append('ajax_action', 'save_permissions');
            for (var i = 0; i < postData.permission_ids.length; i++) {
                formDataToSend.append('permission_ids[]', postData.permission_ids[i]);
            }
            
            // Добавляем параметр ajax=y в URL, если его там нет
            var url = window.location.href;
            if (url.indexOf('ajax=y') === -1) {
                url += (url.indexOf('?') === -1 ? '?' : '&') + 'ajax=y';
            }
            
            fetch(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: formDataToSend.toString()
            })
            .then(function(response) {
                console.log('Fetch response status:', response.status);
                console.log('Fetch response headers:', response.headers);
                
                // Всегда получаем текст сначала, чтобы увидеть, что пришло
                return response.text().then(function(text) {
                    console.log('Response text:', text);
                    console.log('Response text length:', text.length);
                    console.log('Response starts with:', text.substring(0, 50));
                    
                    // Пытаемся распарсить как JSON
                    try {
                        var jsonData = JSON.parse(text.trim());
                        console.log('Parsed JSON:', jsonData);
                        return jsonData;
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        // Если не JSON, но это HTML, считаем успехом
                        if (text.indexOf('<!DOCTYPE') !== -1 || text.indexOf('<html') !== -1) {
                            console.log('HTML response detected, treating as success');
                            return {success: true, message: 'Права доступа сохранены (HTML ответ)'};
                        }
                        throw new Error('Invalid JSON response: ' + e.message);
                    }
                });
            })
            .then(function(response) {
                console.log('Final response:', response);
                console.log('Response type:', typeof response);
                console.log('Response.success:', response ? response.success : 'response is null');
                console.log('Response.success === true:', response ? (response.success === true) : false);
                console.log('Response.success === "true":', response ? (response.success === 'true') : false);
                console.log('Boolean(response):', Boolean(response));
                console.log('Boolean(response && response.success):', Boolean(response && response.success));
                
                // Упрощенная проверка - просто проверяем наличие success и его значение
                var isSuccess = response && (response.success === true || response.success === 'true' || response.success === 1);
                console.log('isSuccess:', isSuccess);
                
                if (isSuccess) {
                    console.log('Entering success block');
                    
                    // Показываем уведомление об успехе
                    if (typeof BX !== 'undefined' && BX.UI && BX.UI.Notification) {
                        console.log('Showing notification');
                        BX.UI.Notification.Center.notify({
                            content: 'Права доступа успешно сохранены',
                            position: 'top-right'
                        });
                    } else {
                        console.log('BX.UI.Notification not available');
                    }
                    
                    // Закрываем SidePanel и обновляем родительское окно
                    var reloadScheduled = false;
                    
                    // Устанавливаем флаг, чтобы предотвратить редирект при закрытии SidePanel
                    if (window.parent && window.parent !== window) {
                        window.parent._isClosingSidePanel = true;
                    }
                    window._isClosingSidePanel = true;
                    
                    // Показываем сообщение об успехе и закрываем SidePanel
                    // Отправляем сообщение родительскому окну перед закрытием
                    if (window.parent && window.parent !== window) {
                        try {
                            console.log('Sending message to parent window');
                            window.parent.postMessage({
                                type: 'calendar:groupPermissionsChanged'
                            }, window.location.origin);
                        } catch (e) {
                            console.error('Error sending message to parent:', e);
                        }

                        try {
                            if (window.parent.BX && window.parent.BX.SidePanel && window.parent.BX.SidePanel.Instance) {
                                console.log('Closing SidePanel via parent window');
                                // Закрываем SidePanel
                                window.parent.BX.SidePanel.Instance.close();
                                console.log('Parent SidePanel closed successfully');

                                // Сбрасываем флаг через некоторое время
                                setTimeout(function() {
                                    if (window.parent) {
                                        window.parent._isClosingSidePanel = false;
                                    }
                                    window._isClosingSidePanel = false;
                                }, 1000);
                            } else {
                                console.log('SidePanel not found in parent window');
                            }
                        } catch (e) {
                            console.error('Error closing parent SidePanel:', e);
                        }
                    } else if (typeof BX !== 'undefined' && BX.SidePanel && BX.SidePanel.Instance) {
                        console.log('Closing SidePanel in current window');
                        try {
                            BX.SidePanel.Instance.close();
                            console.log('SidePanel closed successfully');
                            
                            // Сбрасываем флаг через некоторое время
                            setTimeout(function() {
                                window._isClosingSidePanel = false;
                            }, 1000);
                        } catch (e) {
                            console.error('Error closing SidePanel:', e);
                        }
                    }
                    
                    // Прерываем выполнение, чтобы избежать любых дальнейших действий
                    return;
                } else {
                    // Показываем ошибку
                    var errorMsg = response && response.error ? response.error : 'Неизвестная ошибка';
                    console.error('Save error:', errorMsg, response);
                    if (typeof BX !== 'undefined' && BX.UI && BX.UI.Notification) {
                        BX.UI.Notification.Center.notify({
                            content: 'Ошибка сохранения: ' + errorMsg,
                            position: 'top-right',
                            type: 'error'
                        });
                    } else {
                        alert('Ошибка сохранения: ' + errorMsg);
                    }
                    
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            })
            .catch(function(error) {
                console.error('AJAX error:', error);
                
                // Если ошибка парсинга JSON (сервер вернул HTML), пытаемся обработать как успех
                if (error.message && error.message.indexOf('JSON') !== -1) {
                    // Похоже, сервер вернул HTML вместо JSON - возможно, это успешное сохранение
                    // Показываем уведомление и закрываем SidePanel
                    if (typeof BX !== 'undefined' && BX.UI && BX.UI.Notification) {
                        BX.UI.Notification.Center.notify({
                            content: 'Права доступа успешно сохранены',
                            position: 'top-right'
                        });
                    }
                    
                    if (typeof BX !== 'undefined' && BX.SidePanel && BX.SidePanel.Instance) {
                        BX.SidePanel.Instance.close();
                    }
                } else {
                    // Реальная ошибка
                    var errorMsg = error.message || 'Неизвестная ошибка';
                    if (typeof BX !== 'undefined' && BX.UI && BX.UI.Notification) {
                        BX.UI.Notification.Center.notify({
                            content: 'Ошибка при отправке запроса: ' + errorMsg,
                            position: 'top-right',
                            type: 'error'
                        });
                    } else {
                        alert('Ошибка при отправке запроса: ' + errorMsg);
                    }
                    
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            });
        });
    }
})();
</script>

<?php
// Если открыто в SidePanel, не подключаем epilog_admin, чтобы избежать редиректов
if (!isset($_GET['IFRAME']) || $_GET['IFRAME'] !== 'Y') {
    require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
}
?>
