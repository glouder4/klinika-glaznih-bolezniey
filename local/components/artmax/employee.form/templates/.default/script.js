/**
 * ArtMax Employee Form Component JavaScript
 */

// Функция инициализации после загрузки BX или сразу если BX недоступен
function initializeEmployeeForm() {
    let eventId = window.employeeFormData ? window.employeeFormData.eventId : null;
    
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

    // Функция показа уведомления
    function showNotification(message, type) {
        if (typeof BX !== 'undefined' && BX.UI && BX.UI.Notification) {
            BX.UI.Notification.Center.notify({
                content: message,
                position: 'top-right'
            });
        } else {
            alert(message);
        }
    }

    // Функция проверки и сохранения выбранного врача
    window.saveEmployeeData = function() {
        const employeeSelect = document.getElementById('employee-select');
        if (!employeeSelect) {
            showNotification('Ошибка: элемент формы не найден', 'error');
            return;
        }
        
        const employeeId = employeeSelect.value;
        if (!employeeId) {
            showNotification('Выберите врача', 'error');
            const errorElement = document.getElementById('employee-error');
            if (errorElement) {
                errorElement.style.display = 'block';
            }
            return;
        }
        
        // Скрываем ошибку если она была показана
        const errorElement = document.getElementById('employee-error');
        if (errorElement) {
            errorElement.style.display = 'none';
        }
        
        if (!eventId) {
            showNotification('Ошибка: не найдено событие', 'error');
            return;
        }

        // Сначала получаем данные о текущем событии для проверки времени
        const csrfToken = getCSRFToken();
        
        // Получаем данные события
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
            },
            body: new URLSearchParams({
                action: 'getEvent',
                eventId: eventId,
                sessid: csrfToken
            })
        })
        .then(response => response.json())
        .then(eventData => {
            if (!eventData.success) {
                showNotification('Ошибка получения данных события', 'error');
                return Promise.reject('Failed to get event data');
            }

            const event = eventData.event;
            
            // Проверяем занятость времени для выбранного врача
            return fetch('/local/components/artmax/calendar/ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Bitrix-Csrf-Token': csrfToken
                },
                body: new URLSearchParams({
                    action: 'checkTimeAvailability',
                    dateFrom: event.DATE_FROM,
                    dateTo: event.DATE_TO,
                    employeeId: employeeId,
                    excludeEventId: eventId,
                    sessid: csrfToken
                })
            });
        })
        .then(response => response.json())
        .then(availabilityData => {
            if (!availabilityData.success) {
                showNotification('Ошибка проверки времени: ' + (availabilityData.error || 'Неизвестная ошибка'), 'error');
                return Promise.reject('Failed to check time availability');
            }

            if (!availabilityData.available) {
                showNotification('Время занято у выбранного врача. Выберите другое время или другого врача.', 'error');
                return Promise.reject('Time not available');
            }

            // Если время свободно, назначаем врача
            return fetch('/local/components/artmax/calendar/ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Bitrix-Csrf-Token': csrfToken
                },
                body: new URLSearchParams({
                    action: 'assignDoctor',
                    eventId: eventId,
                    employee_id: employeeId,
                    sessid: csrfToken
                })
            });
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Находим имя врача из списка сотрудников
                const employees = window.employeeFormData ? window.employeeFormData.employees : [];
                const employee = employees.find(emp => emp.ID == employeeId);
                let employeeName = 'Врач #' + employeeId;
                if (employee) {
                    const name = trim((employee.NAME ?? '') + ' ' + (employee.LAST_NAME ?? ''));
                    employeeName = name || (employee.LOGIN ?? employeeName);
                }
                
                showNotification('Врач назначен', 'success');
                
                // Отправляем сообщение родительскому окну
                if (window.parent) {
                    window.parent.postMessage({
                        type: 'calendar:employeeAssigned',
                        eventId: eventId,
                        employeeId: employeeId,
                        employeeName: employeeName
                    }, '*');
                }
                
                // Закрываем SidePanel
                setTimeout(() => {
                    closeSidePanel();
                }, 300);
            } else {
                showNotification('Ошибка назначения врача: ' + (data.error || 'Неизвестная ошибка'), 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка при назначении врача:', error);
            // Показываем общую ошибку только если это не была ошибка занятости времени
            if (error !== 'Time not available') {
                showNotification('Ошибка соединения с сервером', 'error');
            }
        });
    };

    // Предотвращаем стандартную отправку формы
    const employeeForm = document.getElementById('employee-form');
    if (employeeForm) {
        employeeForm.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        });
    }
}

// Вспомогательная функция для trim
function trim(str) {
    return str ? str.trim() : '';
}

// Инициализируем форму
if (typeof BX !== 'undefined' && BX.ready) {
    BX.ready(function() {
        initializeEmployeeForm();
    });
} else {
    // Если BX недоступен, инициализируем сразу
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeEmployeeForm);
    } else {
        initializeEmployeeForm();
    }
}
