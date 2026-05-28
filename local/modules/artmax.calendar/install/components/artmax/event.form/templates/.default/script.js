/**
 * ArtMax Event Form Component JavaScript
 */

// Функция инициализации после загрузки BX или сразу если BX недоступен
function initializeEventForm() {
    function applyInfoEventMode() {
        const isInfoEvent = !!document.getElementById('event-is-info')?.checked;
        const employeeGroup = document.getElementById('event-employee-group');
        const durationGroup = document.getElementById('event-duration-group');
        const employeeField = document.getElementById('event-employee');
        const durationField = document.getElementById('event-duration');

        if (employeeGroup) {
            employeeGroup.classList.toggle('artmax-form-field--hidden', isInfoEvent);
        }
        if (durationGroup) {
            durationGroup.classList.toggle('artmax-form-field--hidden', isInfoEvent);
        }
        if (employeeField) {
            employeeField.required = !isInfoEvent;
        }
        if (durationField) {
            durationField.required = !isInfoEvent;
        }
    }

    // Функции для работы с цветами
    window.selectPresetColor = function(color) {
        document.querySelectorAll('.artmax-color-preset').forEach(preset => {
            preset.classList.remove('active');
        });
        event.target.classList.add('active');
        document.getElementById('selected-color').value = color;
        document.getElementById('custom-color-input').value = color;
    };

    window.selectCustomColor = function(color) {
        if (!color) return;
        document.querySelectorAll('.artmax-color-preset').forEach(preset => {
            preset.classList.remove('active');
        });
        const hidden = document.getElementById('selected-color');
        const native = document.getElementById('custom-color-input');
        if (hidden) {
            hidden.value = color;
        }
        if (native && native.value !== color) {
            try {
                native.value = color;
            } catch (e) { /* невалидный формат для type=color */ }
        }
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

    // Функция сохранения события
    window.saveEvent = function() {
        const form = document.getElementById('add-event-form');
        
        // Валидация формы
        let isValid = true;
        
        // Очищаем предыдущие ошибки
        document.querySelectorAll('.artmax-field-error').forEach(error => {
            error.style.display = 'none';
        });
        
        document.querySelectorAll('.artmax-form-field').forEach(field => {
            field.classList.remove('error');
        });
        
        const isInfoEvent = !!document.getElementById('event-is-info')?.checked;

        // Проверяем обязательные поля
        const requiredFields = [
            { id: 'event-title', errorId: 'title-error', message: 'Заполните это поле' },
            { id: 'event-date', errorId: 'date-error', message: 'Заполните это поле' },
            { id: 'event-time', errorId: 'time-error', message: 'Заполните это поле' },
            ...(isInfoEvent ? [] : [
                { id: 'event-employee', errorId: 'employee-error', message: 'Выберите ответственного сотрудника' },
                { id: 'event-duration', errorId: 'duration-error', message: 'Заполните это поле' }
            ])
        ];
        
        requiredFields.forEach(field => {
            const input = document.getElementById(field.id);
            const error = document.getElementById(field.errorId);
            const fieldContainer = input.closest('.artmax-form-field') || input.closest('.artmax-form-row');
            
            if (!input.value.trim()) {
                isValid = false;
                fieldContainer.classList.add('error');
                error.textContent = field.message;
                error.style.display = 'block';
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
            saveBtn.textContent = 'Сохранение...';
        }
        
        // Формируем данные в формате, ожидаемом обработчиком addEvent
        const formData = new FormData();
        formData.append('action', 'addEvent');
        formData.append('title', document.getElementById('event-title').value);
        formData.append('description', document.getElementById('event-description').value);
        formData.append('employee_id', document.getElementById('event-employee').value);
        formData.append('branch_id', form.querySelector('[name="branch_id"]').value);
        const customColorPeek = document.getElementById('custom-color-input');
        if (customColorPeek && customColorPeek.value) {
            window.selectCustomColor(customColorPeek.value);
        }
        formData.append('eventColor', document.getElementById('selected-color').value);
        formData.append('info_event', document.getElementById('event-is-info')?.checked ? 'Y' : 'N');
        
        // Формируем дату и время начала
        const date = document.getElementById('event-date').value;
        const time = document.getElementById('event-time').value;
        const durationRaw = document.getElementById('event-duration').value;
        const duration = isInfoEvent ? (parseInt(durationRaw, 10) || 5) : parseInt(durationRaw, 10);
        
        // Формируем дату точно как указал пользователь
        const dateFrom = date + ' ' + time + ':00';
        
        // Вычисляем время окончания
        const [hours, minutes] = time.split(':');
        const startMinutes = parseInt(hours) * 60 + parseInt(minutes);
        const endMinutes = startMinutes + (Number.isFinite(duration) ? duration : 5);
        const endHours = Math.floor(endMinutes / 60);
        const endMins = endMinutes % 60;
        const endTime = String(endHours).padStart(2, '0') + ':' + String(endMins).padStart(2, '0');
        const dateTo = date + ' ' + endTime + ':00';
        
        // Отправляем даты как есть, без конвертации
        formData.append('dateFrom', dateFrom);
        formData.append('dateTo', dateTo);
        
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            body: formData
        }).then(response => response.json()).then(function(response) {
            if (response.success) {
                // Отправляем сообщение родительскому окну о создании события
                if (window.parent) {
                    window.parent.postMessage({
                        type: 'calendar:eventCreated',
                        eventId: response.eventId || null,
                        date: document.getElementById('event-date').value
                    }, '*');
                }
                
                // Закрываем SidePanel
                closeSidePanel();
            } else {
                // Показываем уведомление об ошибке
                if (typeof BX !== 'undefined' && BX.UI && BX.UI.Notification) {
                    BX.UI.Notification.Center.notify({
                        content: 'Ошибка при создании события: ' + (response.error || 'Неизвестная ошибка'),
                        position: 'top-right'
                    });
                } else {
                    alert('Ошибка при создании события: ' + (response.error || 'Неизвестная ошибка'));
                }
            }
        }).catch(function(error) {
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
        }).finally(function() {
            // Восстанавливаем кнопку
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.classList.remove('loading');
                saveBtn.textContent = 'Сохранить';
            }
        });
    };

    // Предотвращаем стандартную отправку формы
    const eventForm = document.getElementById('add-event-form');
    if (eventForm) {
        const infoCheckbox = document.getElementById('event-is-info');
        if (infoCheckbox) {
            infoCheckbox.addEventListener('change', applyInfoEventMode);
        }
        applyInfoEventMode();

        eventForm.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        });

        const customColorInput = document.getElementById('custom-color-input');
        if (customColorInput) {
            const syncCustomColorToHidden = function() {
                window.selectCustomColor(customColorInput.value);
            };
            customColorInput.addEventListener('input', syncCustomColorToHidden);
            customColorInput.addEventListener('change', syncCustomColorToHidden);
        }
    }
}

// Инициализируем форму
if (typeof BX !== 'undefined' && BX.ready) {
    BX.ready(function() {
        initializeEventForm();
    });
} else {
    // Fallback для случаев, когда BX недоступен
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeEventForm);
    } else {
        initializeEventForm();
    }
}
