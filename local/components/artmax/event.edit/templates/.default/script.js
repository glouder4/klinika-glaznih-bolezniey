/**
 * ArtMax Event Edit Form Component JavaScript
 */

// Функция инициализации после загрузки BX или сразу если BX недоступен
function initializeEventEditForm() {
    let eventId = window.eventEditData ? window.eventEditData.eventId : null;
    let eventData = window.eventEditData ? window.eventEditData.event : null;
    
    // Функции для работы с цветами
    window.selectEditPresetColor = function(color) {
        document.querySelectorAll('.artmax-color-preset').forEach(preset => {
            preset.classList.remove('active');
        });
        event.target.classList.add('active');
        document.getElementById('edit-selected-color').value = color;
        document.getElementById('edit-custom-color-input').value = color;
    };

    window.selectEditCustomColor = function(color) {
        document.querySelectorAll('.artmax-color-preset').forEach(preset => {
            preset.classList.remove('active');
        });
        document.getElementById('edit-selected-color').value = color;
    };

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

    // Функция обновления занятых временных слотов (заглушка для обратной совместимости)
    function updateOccupiedTimeSlots(employeeId, currentEventId) {
        // Эта функция должна быть реализована в родительском окне
        // Пока оставляем как заглушку
        console.log('updateOccupiedTimeSlots called:', employeeId, currentEventId);
    }

    // Функция сохранения редактированного события
    window.saveEditEvent = function() {
        const form = document.getElementById('edit-event-form');
        
        if (!form || !eventId) {
            showNotification('Ошибка: форма или ID события не найдены', 'error');
            return;
        }
        
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
        const requiredFields = [
            { id: 'edit-event-title', errorId: 'title-error', message: 'Заполните это поле' },
            { id: 'edit-event-employee', errorId: 'employee-error', message: 'Выберите ответственного сотрудника' },
            { id: 'edit-event-date', errorId: 'date-error', message: 'Заполните это поле' },
            { id: 'edit-event-time', errorId: 'time-error', message: 'Заполните это поле' },
            { id: 'edit-event-duration', errorId: 'duration-error', message: 'Заполните это поле' }
        ];
        
        requiredFields.forEach(field => {
            const input = document.getElementById(field.id);
            if (!input) return;
            
            const error = document.getElementById(field.errorId);
            const fieldContainer = input.closest('.artmax-form-field') || input.closest('.artmax-form-row') || input.closest('.artmax-event-title-section');
            
            if (!input.value.trim()) {
                isValid = false;
                if (fieldContainer) {
                    fieldContainer.classList.add('error');
                }
                if (error) {
                    error.textContent = field.message;
                    error.style.display = 'block';
                }
            }
        });
        
        if (!isValid) {
            return;
        }
        
        // Показываем индикатор загрузки
        const saveBtn = document.getElementById('save-event-btn');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.classList.add('loading');
            saveBtn.value = 'Сохранение...';
        }
        
        // Формируем данные в формате, ожидаемом обработчиком updateEvent
        const formData = new FormData();
        formData.append('action', 'updateEvent');
        formData.append('eventId', eventId);
        formData.append('title', document.getElementById('edit-event-title').value.trim());
        formData.append('description', document.getElementById('edit-event-description').value.trim());
        formData.append('employee_id', document.getElementById('edit-event-employee').value);
        formData.append('branchId', form.querySelector('[name="branch_id"]').value);
        formData.append('eventColor', document.getElementById('edit-selected-color').value || '#2fc6f6');
        
        // Формируем дату и время начала
        const date = document.getElementById('edit-event-date').value;
        const time = document.getElementById('edit-event-time').value;
        const duration = parseInt(document.getElementById('edit-event-duration').value);
        
        // Формируем дату точно как указал пользователь
        const dateFrom = date + ' ' + time + ':00';
        
        // Вычисляем время окончания
        const [hours, minutes] = time.split(':');
        const startMinutes = parseInt(hours) * 60 + parseInt(minutes);
        const endMinutes = startMinutes + duration;
        const endHours = Math.floor(endMinutes / 60);
        const endMins = endMinutes % 60;
        const endTime = String(endHours).padStart(2, '0') + ':' + String(endMins).padStart(2, '0');
        const dateTo = date + ' ' + endTime + ':00';
        
        formData.append('dateFrom', dateFrom);
        formData.append('dateTo', dateTo);
        formData.append('sessid', getCSRFToken());
        
        // Конвертируем FormData в URLSearchParams для корректной отправки
        const postData = new URLSearchParams();
        for (const [key, value] of formData.entries()) {
            postData.append(key, value);
        }
        
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': getCSRFToken()
            },
            body: postData
        })
        .then(response => response.text())
        .then(text => {
            try {
                const data = JSON.parse(text);
                
                if (data.success) {
                    showNotification('Событие успешно обновлено!', 'success');
                    
                    // Отправляем сообщение родительскому окну об обновлении события
                    if (window.parent) {
                        window.parent.postMessage({
                            type: 'calendar:eventUpdated',
                            eventId: eventId,
                            date: date
                        }, '*');
                    }
                    
                    // Закрываем SidePanel
                    setTimeout(() => {
                        closeSidePanel();
                    }, 500);
                } else {
                    showNotification('Ошибка при обновлении события: ' + (data.error || 'Неизвестная ошибка'), 'error');
                }
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Raw response:', text);
                showNotification('Ошибка парсинга ответа сервера', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Произошла ошибка при отправке данных', 'error');
        })
        .finally(() => {
            // Восстанавливаем кнопку
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.classList.remove('loading');
                saveBtn.value = 'Сохранить';
            }
        });
    };

    // Функция удаления события
    window.deleteEvent = function() {
        if (!eventId) {
            showNotification('Ошибка: ID события не найден', 'error');
            return;
        }
        
        if (!confirm('Вы уверены, что хотите удалить это событие?')) {
            return;
        }
        
        const csrfToken = getCSRFToken();
        
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
            },
            body: new URLSearchParams({
                action: 'deleteEvent',
                eventId: eventId,
                sessid: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Событие успешно удалено!', 'success');
                
                // Отправляем сообщение родительскому окну об удалении события
                if (window.parent) {
                    window.parent.postMessage({
                        type: 'calendar:eventDeleted',
                        eventId: eventId
                    }, '*');
                }
                
                // Закрываем SidePanel
                setTimeout(() => {
                    closeSidePanel();
                }, 500);
            } else {
                showNotification('Ошибка при удалении события: ' + (data.error || 'Неизвестная ошибка'), 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка при удалении события:', error);
            showNotification('Ошибка при удалении события', 'error');
        });
    };

    // Добавляем обработчик изменения даты для обновления занятых слотов (если нужно)
    const dateInput = document.getElementById('edit-event-date');
    const employeeSelect = document.getElementById('edit-event-employee');
    
    if (dateInput && employeeSelect) {
        // Обновляем занятые слоты при изменении даты или сотрудника
        function updateOccupiedSlots() {
            const employeeId = employeeSelect.value;
            if (employeeId && eventId) {
                updateOccupiedTimeSlots(employeeId, eventId);
            }
        }
        
        dateInput.addEventListener('change', updateOccupiedSlots);
        employeeSelect.addEventListener('change', updateOccupiedSlots);
    }

    // Предотвращаем стандартную отправку формы
    const editEventForm = document.getElementById('edit-event-form');
    if (editEventForm) {
        editEventForm.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        });
    }
}

// Инициализируем форму
if (typeof BX !== 'undefined' && BX.ready) {
    BX.ready(function() {
        initializeEventEditForm();
    });
} else {
    // Fallback для случаев, когда BX недоступен
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeEventEditForm);
    } else {
        initializeEventEditForm();
    }
}

