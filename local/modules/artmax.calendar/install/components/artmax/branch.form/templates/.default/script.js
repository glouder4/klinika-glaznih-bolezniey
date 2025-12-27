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
                    showNotification('Филиал успешно создан!');
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
                    showNotification('Ошибка создания филиала: ' + (response.error || 'Неизвестная ошибка'), 'error');
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
                showNotification('Произошла ошибка при отправке данных', 'error');
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
    function showNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = `artmax-calendar-notification artmax-calendar-${type}`;
        notification.textContent = message;

        // Стили для уведомления
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            z-index: 10001;
            max-width: 300px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            transform: translateX(100%);
            transition: transform 0.3s ease;
        `;

        if (type === 'error') {
            notification.style.background = 'linear-gradient(135deg, #ff6b6b, #ee5a24)';
        } else if (type === 'warning') {
            notification.style.background = 'linear-gradient(135deg, #f39c12, #e67e22)';
        } else {
            notification.style.background = 'linear-gradient(135deg, #00b894, #00a085)';
        }

        document.body.appendChild(notification);

        // Анимация появления
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 100);

        // Автоматическое удаление
        setTimeout(() => {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (document.body.contains(notification)) {
                    document.body.removeChild(notification);
                }
            }, 300);
        }, 5000);
    }

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

    // Предотвращаем стандартную отправку формы
    const branchForm = document.getElementById('add-branch-form');
    if (branchForm) {
        branchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        });
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

