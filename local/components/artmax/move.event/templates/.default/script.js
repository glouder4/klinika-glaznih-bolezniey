/**
 * ArtMax Move Event Form Component JavaScript
 */

// Функция инициализации после загрузки BX или сразу если BX недоступен
function initializeMoveEventForm() {
    let eventId = window.moveEventData ? window.moveEventData.eventId : null;
    let currentBranchId = window.moveEventData ? window.moveEventData.currentBranchId : null;
    let currentEmployeeId = window.moveEventData ? window.moveEventData.currentEmployeeId : null;
    
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

    // Функция загрузки врачей для выбранного филиала
    function loadEmployeesForBranch(branchId) {
        const csrfToken = getCSRFToken();
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
            },
            body: new URLSearchParams({
                action: 'getBranchEmployees',
                branchId: branchId,
                sessid: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.employees) {
                populateEmployeeSelect(data.employees, currentEmployeeId);
                // Если есть выбранный врач и дата, загружаем расписание
                if (currentEmployeeId && document.getElementById('move-event-date').value) {
                    loadDoctorSchedule(currentEmployeeId, document.getElementById('move-event-date').value);
                }
            } else {
                console.error('Ошибка загрузки врачей:', data.error);
                showNotification('Ошибка загрузки врачей', 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка при загрузке врачей:', error);
            showNotification('Ошибка соединения с сервером', 'error');
        });
    }

    // Функция заполнения селектора врачей
    function populateEmployeeSelect(employees, selectedEmployeeId) {
        const employeeSelect = document.getElementById('move-event-employee');
        employeeSelect.innerHTML = '<option value="">Выберите врача</option>';
        
        employees.forEach(employee => {
            const option = document.createElement('option');
            option.value = employee.ID;
            const employeeName = `${employee.NAME || ''} ${employee.LAST_NAME || ''}`.trim() || employee.LOGIN || 'Сотрудник #' + employee.ID;
            option.textContent = employeeName;
            
            if (employee.ID == selectedEmployeeId) {
                option.selected = true;
                currentEmployeeId = employee.ID;
            }
            
            employeeSelect.appendChild(option);
        });
    }

    // Функция загрузки расписания врача
    function loadDoctorSchedule(employeeId, date) {
        const branchSelect = document.getElementById('move-event-branch');
        const branchId = branchSelect ? branchSelect.value : null;
        
        const csrfToken = getCSRFToken();
        const postData = {
            action: 'getDoctorScheduleForMove',
            employeeId: employeeId,
            date: date,
            excludeEventId: eventId,
            sessid: csrfToken
        };
        
        if (branchId) {
            postData.branchId = branchId;
        }
        
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
            },
            body: new URLSearchParams(postData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && Array.isArray(data.availableTimes)) {
                populateTimeSelect(data.availableTimes);
            } else {
                console.error('Ошибка загрузки расписания:', data.error);
                populateTimeSelect([]);
            }
        })
        .catch(error => {
            console.error('Ошибка при загрузке расписания:', error);
            populateTimeSelect([]);
        });
    }

    // Функция заполнения селектора времени
    function populateTimeSelect(availableTimes) {
        const timeSelect = document.getElementById('move-event-time');
        timeSelect.innerHTML = '<option value="">Выберите время</option>';
        
        if (!Array.isArray(availableTimes) || availableTimes.length === 0) {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'Нет доступных времен';
            option.disabled = true;
            timeSelect.appendChild(option);
            return;
        }
        
        availableTimes.forEach(timeSlot => {
            const option = document.createElement('option');
            option.value = timeSlot.time;
            option.textContent = timeSlot.time;
            timeSelect.appendChild(option);
        });
    }

    // Обработчик изменения филиала
    const branchSelect = document.getElementById('move-event-branch');
    if (branchSelect) {
        branchSelect.addEventListener('change', function() {
            const selectedBranchId = this.value;
            if (selectedBranchId) {
                // Очищаем селекторы врачей и времени
                const employeeSelect = document.getElementById('move-event-employee');
                employeeSelect.innerHTML = '<option value="">Выберите врача</option>';
                
                populateTimeSelect([]);
                
                // Загружаем врачей для выбранного филиала
                loadEmployeesForBranch(selectedBranchId);
            }
        });
    }

    // Обработчик изменения врача
    const employeeSelect = document.getElementById('move-event-employee');
    if (employeeSelect) {
        employeeSelect.addEventListener('change', function() {
            const selectedEmployeeId = this.value;
            const dateInput = document.getElementById('move-event-date');
            const selectedDate = dateInput.value;
            
            if (selectedEmployeeId && selectedDate) {
                loadDoctorSchedule(selectedEmployeeId, selectedDate);
            } else {
                populateTimeSelect([]);
            }
        });
    }

    // Обработчик изменения даты
    const dateInput = document.getElementById('move-event-date');
    if (dateInput) {
        dateInput.addEventListener('change', function() {
            const selectedDate = this.value;
            const selectedEmployeeId = employeeSelect ? employeeSelect.value : null;
            
            if (selectedEmployeeId && selectedDate) {
                loadDoctorSchedule(selectedEmployeeId, selectedDate);
            } else {
                populateTimeSelect([]);
            }
        });
    }

    // Функция вычисления длительности события в минутах
    function getEventDuration(dateFrom, dateTo) {
        const start = new Date(dateFrom);
        const end = new Date(dateTo);
        return Math.round((end - start) / (1000 * 60));
    }

    // Функция форматирования даты для отправки на сервер
    function formatLocalDateTime(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        const seconds = String(date.getSeconds()).padStart(2, '0');
        return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    }

    // Функция сохранения переноса записи
    window.saveMoveEvent = function() {
        const branchId = document.getElementById('move-event-branch').value;
        const employeeId = document.getElementById('move-event-employee').value;
        const date = document.getElementById('move-event-date').value;
        const time = document.getElementById('move-event-time').value;
        
        // Валидация
        if (!branchId) {
            showNotification('Выберите филиал', 'error');
            document.getElementById('branch-error').style.display = 'block';
            return;
        }
        document.getElementById('branch-error').style.display = 'none';
        
        if (!employeeId) {
            showNotification('Выберите врача', 'error');
            document.getElementById('employee-error').style.display = 'block';
            return;
        }
        document.getElementById('employee-error').style.display = 'none';
        
        if (!date) {
            showNotification('Выберите дату', 'error');
            document.getElementById('date-error').style.display = 'block';
            return;
        }
        document.getElementById('date-error').style.display = 'none';
        
        if (!time) {
            showNotification('Выберите время', 'error');
            document.getElementById('time-error').style.display = 'block';
            return;
        }
        document.getElementById('time-error').style.display = 'none';
        
        if (!eventId) {
            showNotification('Ошибка: не найдено событие', 'error');
            return;
        }
        
        // Создаем дату и время для переноса
        const [year, month, day] = date.split('-');
        const [hours, minutes] = time.split(':');
        const newDateTime = `${year}-${month}-${day} ${hours}:${minutes}:00`;
        
        // Получаем длительность события
        const csrfToken = getCSRFToken();
        const event = window.moveEventData ? window.moveEventData.event : null;
        
        if (!event) {
            showNotification('Ошибка: данные события не найдены', 'error');
            return;
        }
        
        const duration = getEventDuration(event.DATE_FROM, event.DATE_TO);
        
        // Вычисляем новое время окончания
        const startTime = new Date(newDateTime);
        const endTime = new Date(startTime.getTime() + duration * 60 * 1000);
        const newEndDateTime = formatLocalDateTime(endTime);
        
        // Переносим событие
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
            },
            body: new URLSearchParams({
                action: 'moveEvent',
                eventId: eventId,
                branchId: branchId,
                employeeId: employeeId,
                dateFrom: newDateTime,
                dateTo: newEndDateTime,
                sessid: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Запись успешно перенесена', 'success');
                
                // Отправляем сообщение родительскому окну
                if (window.parent) {
                    window.parent.postMessage({
                        type: 'calendar:eventMoved',
                        eventId: eventId,
                        affectedEvents: data.affectedEvents || [eventId]
                    }, '*');
                }
                
                // Закрываем SidePanel
                setTimeout(() => {
                    closeSidePanel();
                }, 300);
            } else {
                showNotification('Ошибка переноса записи: ' + (data.error || 'Неизвестная ошибка'), 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка при переносе записи:', error);
            showNotification('Ошибка соединения с сервером', 'error');
        });
    };
    
    // Если есть текущий филиал, загружаем врачей (если они еще не загружены)
    if (currentBranchId && employeeSelect && employeeSelect.children.length <= 1) {
        loadEmployeesForBranch(currentBranchId);
    }
    
    // Если есть текущий врач и дата, загружаем расписание
    if (currentEmployeeId && dateInput && dateInput.value) {
        loadDoctorSchedule(currentEmployeeId, dateInput.value);
    }

    // Предотвращаем стандартную отправку формы
    const moveEventForm = document.getElementById('move-event-form');
    if (moveEventForm) {
        moveEventForm.addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        });
    }
}

// Инициализируем форму
if (typeof BX !== 'undefined' && BX.ready) {
    BX.ready(function() {
        initializeMoveEventForm();
    });
} else {
    // Если BX недоступен, инициализируем сразу
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeMoveEventForm);
    } else {
        initializeMoveEventForm();
    }
}
