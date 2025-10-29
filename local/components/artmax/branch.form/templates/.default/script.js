/**
 * ArtMax Branch Form Component JavaScript
 */

// Функция инициализации после загрузки BX или сразу если BX недоступен
function initializeBranchForm() {
    // Функция закрытия SidePanel
    window.closeSidePanel = function() {
        if (typeof BX !== 'undefined' && BX.SidePanel && BX.SidePanel.Instance) {
            BX.SidePanel.Instance.close();
        } else {
            // Fallback для старых версий или если SidePanel не доступен
            if (window.parent) {
                window.parent.postMessage({
                    type: 'calendar:closePanel'
                }, '*');
            }
            window.close();
        }
    };

    // Функция получения CSRF токена
    function getCSRFToken() {
        const tokenInput = document.querySelector('input[name="sessid"]');
        return tokenInput ? tokenInput.value : '';
    }

    // Валидация email
    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    // Функция сохранения филиала
    window.saveBranch = function() {
        const form = document.getElementById('add-branch-form');
        
        // Валидация формы
        let isValid = true;
        
        // Очищаем предыдущие ошибки
        document.querySelectorAll('.artmax-field-error').forEach(error => {
            error.style.display = 'none';
        });
        
        document.querySelectorAll('.artmax-form-field, .artmax-event-title-section').forEach(field => {
            field.classList.remove('error');
        });
        
        // Проверяем обязательные поля
        const name = document.getElementById('branch-name');
        const email = document.getElementById('branch-email');
        
        if (!name.value.trim()) {
            isValid = false;
            showFieldError('branch-name', 'name-error', 'Заполните название филиала');
        }
        
        // Валидация email, если он указан
        if (email.value.trim() && !validateEmail(email.value.trim())) {
            isValid = false;
            showFieldError('branch-email', 'email-error', 'Введите корректный email');
        }
        
        if (!isValid) {
            return;
        }
        
        // Показываем индикатор загрузки
        const saveBtn = document.getElementById('save-branch-btn');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.classList.add('loading');
            saveBtn.value = 'Создание...';
        }
        
        // Собираем данные формы
        const branchData = {
            action: 'addBranch',
            name: name.value.trim(),
            address: document.getElementById('branch-address').value.trim(),
            phone: document.getElementById('branch-phone').value.trim(),
            email: email.value.trim(),
            sessid: getCSRFToken()
        };
        
        // Отправляем AJAX запрос
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': getCSRFToken()
            },
            body: new URLSearchParams(branchData)
        })
        .then(response => response.json())
        .then(function(response) {
            if (response.success) {
                // Отправляем сообщение родительскому окну о создании филиала
                if (window.parent) {
                    window.parent.postMessage({
                        type: 'calendar:branchCreated',
                        branchId: response.branchId || null
                    }, '*');
                }
                
                // Показываем уведомление об успехе
                if (typeof BX !== 'undefined' && BX.UI && BX.UI.Notification) {
                    BX.UI.Notification.Center.notify({
                        content: 'Филиал успешно создан! Переключатель филиалов обновится автоматически.',
                        position: 'top-right'
                    });
                } else {
                    alert('Филиал успешно создан! Переключатель филиалов обновится автоматически.');
                }
                
                // Закрываем SidePanel
                closeSidePanel();
            } else {
                // Показываем уведомление об ошибке
                if (typeof BX !== 'undefined' && BX.UI && BX.UI.Notification) {
                    BX.UI.Notification.Center.notify({
                        content: 'Ошибка создания филиала: ' + (response.error || 'Неизвестная ошибка'),
                        position: 'top-right'
                    });
                } else {
                    alert('Ошибка создания филиала: ' + (response.error || 'Неизвестная ошибка'));
                }
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            // Показываем уведомление об ошибке
            if (typeof BX !== 'undefined' && BX.UI && BX.UI.Notification) {
                BX.UI.Notification.Center.notify({
                    content: 'Произошла ошибка при отправке данных',
                    position: 'top-right'
                });
            } else {
                alert('Произошла ошибка при отправке данных');
            }
        })
        .finally(function() {
            // Восстанавливаем кнопку
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.classList.remove('loading');
                saveBtn.value = 'Создать филиал';
            }
        });
    };

    // Вспомогательная функция для отображения ошибок
    function showFieldError(fieldId, errorId, message) {
        const field = document.getElementById(fieldId);
        const error = document.getElementById(errorId);
        const fieldContainer = field ? (field.closest('.artmax-form-field') || field.closest('.artmax-event-title-section')) : null;
        
        if (fieldContainer) {
            fieldContainer.classList.add('error');
        }
        
        if (error) {
            error.textContent = message;
            error.style.display = 'block';
        }
    }
}

// Инициализируем форму
if (typeof BX !== 'undefined' && BX.ready) {
    BX.ready(function() {
        initializeBranchForm();
    });
} else {
    // Fallback для случаев, когда BX недоступен
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeBranchForm);
    } else {
        initializeBranchForm();
    }
}

