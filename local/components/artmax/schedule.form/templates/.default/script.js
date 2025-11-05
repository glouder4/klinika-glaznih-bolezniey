/**
 * ArtMax Schedule Form Component JavaScript
 */

// Функция инициализации после загрузки BX или сразу если BX недоступен
function initializeScheduleForm() {
    // Функции для работы с цветами
    window.selectSchedulePresetColor = function(color) {
        document.querySelectorAll('.artmax-color-preset').forEach(preset => {
            preset.classList.remove('active');
        });
        event.target.classList.add('active');
        document.getElementById('schedule-selected-color').value = color;
        document.getElementById('custom-color-input').value = color;
    };

    window.selectScheduleCustomColor = function(color) {
        document.querySelectorAll('.artmax-color-preset').forEach(preset => {
            preset.classList.remove('active');
        });
        document.getElementById('schedule-selected-color').value = color;
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

    // Функция переключения полей повторения
    window.toggleRepeatFields = function() {
        const repeatCheckbox = document.getElementById('schedule-repeat');
        const repeatFields = document.getElementById('repeat-fields');
        
        if (repeatCheckbox && repeatFields) {
            if (repeatCheckbox.checked) {
                repeatFields.style.display = 'block';
                // Убеждаемся, что weekly-days скрыт, если не выбрана еженедельная частота
                toggleWeeklyDays();
            } else {
                repeatFields.style.display = 'none';
                // Скрываем дни недели при отключении повторения
                const weeklyDays = document.getElementById('weekly-days');
                if (weeklyDays) {
                    weeklyDays.style.display = 'none';
                }
            }
        }
    };
    
    // Функция для переключения отображения дней недели
    window.toggleWeeklyDays = function() {
        const frequency = document.getElementById('schedule-frequency');
        const weeklyDays = document.getElementById('weekly-days');
        
        if (frequency && weeklyDays) {
            if (frequency.value === 'weekly') {
                weeklyDays.style.display = 'block';
            } else {
                weeklyDays.style.display = 'none';
            }
        }
    };
    
    // Функция для переключения полей окончания повторения
    window.toggleEndFields = function() {
        const repeatEnd = document.querySelector('input[name="repeat-end"]:checked');

        if (!repeatEnd) {
            return;
        }

        const repeatEndValue = repeatEnd.value;
        const repeatCountInput = document.getElementById('repeat-count');
        const repeatEndDateInput = document.getElementById('repeat-end-date');
        const includeEndDateContainer = document.getElementById('include-end-date-container');

        // Скрываем все поля
        if (repeatCountInput) {
            repeatCountInput.style.display = 'none';
        }
        if (repeatEndDateInput) {
            repeatEndDateInput.style.display = 'none';
        }
        if (includeEndDateContainer) {
            includeEndDateContainer.style.display = 'none';
        }

        // Показываем нужные поля
        if (repeatEndValue === 'after') {
            if (repeatCountInput) {
                repeatCountInput.style.display = 'inline-block';
            }
        } else if (repeatEndValue === 'date') {
            if (repeatEndDateInput) {
                repeatEndDateInput.style.display = 'inline-block';
            }
            if (includeEndDateContainer) {
                includeEndDateContainer.style.display = 'block';
            }
        }
    };

    // Функция получения CSRF токена
    function getCSRFToken() {
        const tokenInput = document.querySelector('input[name="sessid"]');
        return tokenInput ? tokenInput.value : '';
    }

    // Функция сохранения расписания
    window.saveSchedule = function() {
        const form = document.getElementById('schedule-form');
        
        // Валидация формы
        let isValid = true;
        
        // Очищаем предыдущие ошибки
        document.querySelectorAll('.artmax-field-error').forEach(error => {
            error.style.display = 'none';
        });
        
        document.querySelectorAll('.artmax-form-field, .artmax-form-row').forEach(field => {
            field.classList.remove('error');
        });
        
        // Проверяем обязательные поля
        const title = document.getElementById('schedule-title');
        const date = document.getElementById('schedule-date');
        const time = document.getElementById('schedule-time');
        const employee = document.getElementById('schedule-employee');
        
        if (!title.value.trim()) {
            isValid = false;
            showFieldError('schedule-title', 'title-error', 'Заполните название расписания');
        }
        
        if (!date.value) {
            isValid = false;
            showFieldError('schedule-date', 'date-error', 'Выберите дату');
        }
        
        if (!time.value) {
            isValid = false;
            showFieldError('schedule-time', 'time-error', 'Выберите время');
        }
        
        if (!employee.value) {
            isValid = false;
            showFieldError('schedule-employee', 'employee-error', 'Выберите ответственного сотрудника');
        }

        // Проверка выбора окончания повторения, если включено повторение
        const repeatCheckbox = document.getElementById('schedule-repeat');
        if (repeatCheckbox && repeatCheckbox.checked) {
            const repeatEnd = document.querySelector('input[name="repeat-end"]:checked');
            if (!repeatEnd) {
                isValid = false;
                // Можно добавить обработку ошибки для repeat-end-group
            } else {
                const repeatEndValue = repeatEnd.value;
                if (repeatEndValue === 'after') {
                    const repeatCount = document.getElementById('repeat-count');
                    if (!repeatCount || !repeatCount.value || parseInt(repeatCount.value) < 1) {
                        isValid = false;
                    }
                } else if (repeatEndValue === 'date') {
                    const repeatEndDate = document.getElementById('repeat-end-date');
                    if (!repeatEndDate || !repeatEndDate.value) {
                        isValid = false;
                    }
                }
            }

            // Если выбрано еженедельное повторение, проверяем наличие выбранных дней
            const frequency = document.getElementById('schedule-frequency');
            if (frequency && frequency.value === 'weekly') {
                const weekdays = document.querySelectorAll('input[name="weekdays[]"]:checked');
                if (weekdays.length === 0) {
                    isValid = false;
                }
            }
        }
        
        if (!isValid) {
            return;
        }
        
        // Показываем индикатор загрузки
        const saveBtn = document.getElementById('save-schedule-btn');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.classList.add('loading');
            saveBtn.value = 'Сохранение...';
        }
        
        // Собираем данные формы
        const scheduleData = {
            title: title.value.trim(),
            date: date.value,
            time: time.value,
            employee_id: employee.value,
            branch_id: form.querySelector('[name="branch_id"]').value,
            repeat: repeatCheckbox ? repeatCheckbox.checked : false,
            frequency: null,
            weekdays: [],
            repeatEnd: 'after',
            repeatCount: null,
            repeatEndDate: null,
            includeEndDate: false,
            excludeWeekends: false,
            excludeHolidays: false,
            eventColor: document.getElementById('schedule-selected-color').value || '#2fc6f6'
        };

        // Если включено повторение, собираем дополнительные данные
        if (scheduleData.repeat) {
            const frequencySelect = document.getElementById('schedule-frequency');
            if (frequencySelect) {
                scheduleData.frequency = frequencySelect.value;
                
                // Собираем выбранные дни недели
                if (scheduleData.frequency === 'weekly') {
                    const checkedWeekdays = document.querySelectorAll('input[name="weekdays[]"]:checked');
                    scheduleData.weekdays = Array.from(checkedWeekdays).map(cb => parseInt(cb.value));
                }
            }

            // Собираем данные об окончании повторения
            const repeatEnd = document.querySelector('input[name="repeat-end"]:checked');
            if (repeatEnd) {
                scheduleData.repeatEnd = repeatEnd.value;
                
                if (scheduleData.repeatEnd === 'after') {
                    const repeatCount = document.getElementById('repeat-count');
                    if (repeatCount) {
                        scheduleData.repeatCount = parseInt(repeatCount.value);
                    }
                } else if (scheduleData.repeatEnd === 'date') {
                    const repeatEndDate = document.getElementById('repeat-end-date');
                    if (repeatEndDate) {
                        scheduleData.repeatEndDate = repeatEndDate.value;
                    }
                    const includeEndDate = document.getElementById('include-end-date');
                    if (includeEndDate) {
                        scheduleData.includeEndDate = includeEndDate.checked;
                    }
                }
            }
        }
        
        // Подготавливаем данные для отправки
        const postData = {
            action: 'addSchedule',
            title: scheduleData.title,
            date: scheduleData.date,
            time: scheduleData.time,
            employee_id: scheduleData.employee_id,
            branch_id: scheduleData.branch_id,
            repeat: scheduleData.repeat ? 'Y' : 'N',
            frequency: scheduleData.frequency || null,
            weekdays: scheduleData.weekdays || [],
            repeatEnd: scheduleData.repeatEnd || 'after',
            repeatCount: scheduleData.repeatCount || null,
            repeatEndDate: scheduleData.repeatEndDate || null,
            includeEndDate: scheduleData.includeEndDate ? 'Y' : 'N',
            excludeWeekends: scheduleData.excludeWeekends ? 'Y' : 'N',
            excludeHolidays: scheduleData.excludeHolidays ? 'Y' : 'N',
            eventColor: scheduleData.eventColor,
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
            body: new URLSearchParams(postData)
        })
        .then(response => response.json())
        .then(function(response) {
            if (response.success) {
                // Отправляем сообщение родительскому окну о создании расписания
                if (window.parent) {
                    window.parent.postMessage({
                        type: 'calendar:scheduleCreated',
                        eventsCount: response.eventsCreated || 1,
                        events: response.events || []
                    }, '*');
                }
                
                // Показываем уведомление об успехе
                if (typeof BX !== 'undefined' && BX.UI && BX.UI.Notification) {
                    const eventsCount = response.eventsCreated || 1;
                    const message = eventsCount > 1 
                        ? `Расписание успешно создано! Создано ${eventsCount} событий.`
                        : 'Расписание успешно создано!';
                    BX.UI.Notification.Center.notify({
                        content: message,
                        position: 'top-right'
                    });
                }
                
                // Закрываем SidePanel
                closeSidePanel();
            } else {
                // Показываем уведомление об ошибке
                if (typeof BX !== 'undefined' && BX.UI && BX.UI.Notification) {
                    BX.UI.Notification.Center.notify({
                        content: 'Ошибка при создании расписания: ' + (response.error || 'Неизвестная ошибка'),
                        position: 'top-right'
                    });
                } else {
                    alert('Ошибка при создании расписания: ' + (response.error || 'Неизвестная ошибка'));
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
                saveBtn.value = 'Создать';
            }
        });
    };

    // Вспомогательная функция для отображения ошибок
    function showFieldError(fieldId, errorId, message) {
        const field = document.getElementById(fieldId);
        const error = document.getElementById(errorId);
        const fieldContainer = field ? (field.closest('.artmax-form-field') || field.closest('.artmax-form-row')) : null;
        
        if (fieldContainer) {
            fieldContainer.classList.add('error');
        }
        
        if (error) {
            error.textContent = message;
            error.style.display = 'block';
        }
    }

    // Устанавливаем текущую дату и время по умолчанию
    const dateInput = document.getElementById('schedule-date');
    const timeInput = document.getElementById('schedule-time');
    
    if (dateInput && !dateInput.value) {
        const today = new Date();
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');
        dateInput.value = `${year}-${month}-${day}`;
    }
    
    if (timeInput && !timeInput.value) {
        const now = new Date();
        const hours = now.getHours().toString().padStart(2, '0');
        const minutes = now.getMinutes().toString().padStart(2, '0');
        timeInput.value = `${hours}:${minutes}`;
    }

    // Инициализируем поля окончания повторения
    if (document.getElementById('schedule-repeat')) {
        toggleEndFields();
    }
    
    // Инициализируем состояние weekly-days (должен быть скрыт по умолчанию)
    toggleWeeklyDays();

    // Предотвращаем стандартную отправку формы
    const scheduleForm = document.getElementById('schedule-form');
    if (scheduleForm) {
        scheduleForm.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        });
    }
}

// Инициализируем форму
if (typeof BX !== 'undefined' && BX.ready) {
    BX.ready(function() {
        initializeScheduleForm();
    });
} else {
    // Fallback для случаев, когда BX недоступен
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeScheduleForm);
    } else {
        initializeScheduleForm();
    }
}

