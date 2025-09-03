/**
 * Artmax Calendar Module - Calendar Grid JavaScript
 */

(function() {
    'use strict';

    // Инициализация модуля
    document.addEventListener('DOMContentLoaded', function() {
        initCalendar();
    });

    function initCalendar() {
        // Инициализация календарных ячеек
        initCalendarCells();

        // Инициализация навигации
        initNavigation();

        // Инициализация поиска
        initSearch();

        // Инициализация переключателей
        initToggles();

        // Инициализация модального окна
        initModal();

        // Автозаполнение даты окончания
        initDateAutoFill();

        // Валидация формы
        initFormValidation();

        // Анимации
        initAnimations();
        
        // Инициализация цветового пикера
        initColorPicker();
    }

    function initCalendarCells() {
        const calendarDays = document.querySelectorAll('.calendar-day');

        calendarDays.forEach(day => {
            // Добавляем обработчик клика для открытия формы
            day.addEventListener('click', function(e) {
                // Не открываем форму при клике на событие
                if (e.target.closest('.calendar-event')) {
                    return;
                }

                const date = this.getAttribute('data-date');
                if (date) {
                    openEventForm(date);
                }
            });

            // Добавляем обработчик для событий
            const events = day.querySelectorAll('.calendar-event');
            events.forEach(event => {
                event.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const eventId = this.getAttribute('data-event-id');
                    if (eventId) {
                        showEventDetails(eventId);
                    }
                });
            });

            // Добавляем hover эффекты
            day.addEventListener('mouseenter', function() {
                this.style.zIndex = '10';
            });

            day.addEventListener('mouseleave', function() {
                this.style.zIndex = '1';
            });
        });
    }

    function initNavigation() {
        // Обработка вкладок навигации
        /*const navTabs = document.querySelectorAll('.nav-tab');
        navTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                navTabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');

                // Здесь можно добавить логику переключения между видами
                const viewType = this.textContent.trim();
                console.log('Переключение на вид:', viewType);
            });
        });*/

        // Обработка кнопки "СОЗДАТЬ РАСПИСАНИЕ"
        const createBtn = document.querySelector('.btn-create');
        if (createBtn) {
            createBtn.addEventListener('click', function() {
                openScheduleModal();
            });
        }
    }

    function initSearch() {
        const searchInput = document.querySelector('.search-input');
        if (searchInput) {
            let searchTimeout;

            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    const query = this.value.trim();
                    if (query.length > 2) {
                        searchEvents(query);
                    } else if (query.length === 0) {
                        clearSearch();
                    }
                }, 300);
            });

            // Поиск по Enter
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    const query = this.value.trim();
                    if (query.length > 0) {
                        searchEvents(query);
                    }
                }
            });
        }
    }

    function initToggles() {
        const toggleSwitch = document.querySelector('.toggle-switch input');
        if (toggleSwitch) {
            toggleSwitch.addEventListener('change', function() {
                const isEnabled = this.checked;
                console.log('Свободные слоты:', isEnabled ? 'включены' : 'выключены');

                // Здесь можно добавить логику для показа/скрытия свободных слотов
                if (isEnabled) {
                    showFreeSlots();
                } else {
                    hideFreeSlots();
                }
            });
        }
    }

    function initModal() {
        // Закрытие модального окна при клике вне его
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('eventFormModal');
            if (event.target === modal) {
                closeEventForm();
            }
            
            // Закрытие модального окна редактирования
            const editModal = document.getElementById('editEventModal');
            if (event.target === editModal) {
                closeEditEventModal();
            }
        });

        // Закрытие по Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEventForm();
                closeEditEventModal();
            }
        });
    }

    function initDateAutoFill() {
        const dateInput = document.getElementById('event-date');
        const timeSelect = document.getElementById('event-time');
        const durationSelect = document.getElementById('event-duration');

        if (dateInput && timeSelect) {
            // Устанавливаем текущую дату по умолчанию
            const today = new Date();
            // Форматируем дату в локальном формате, избегая проблем с часовыми поясами
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');
            const todayString = `${year}-${month}-${day}`;
            dateInput.value = todayString;
            
            // Устанавливаем время по умолчанию (9:00)
            timeSelect.value = '09:00';
            
            // Устанавливаем длительность по умолчанию (30 минут)
            if (durationSelect) {
                durationSelect.value = '30';
            }
        }
    }

    function initFormValidation() {
        const form = document.getElementById('add-event-form');
        if (form) {
            form.addEventListener('submit', validateAndSubmitForm);
            
            // Добавляем обработчики для очистки ошибок при вводе
            const titleInput = document.getElementById('event-title');
            if (titleInput) {
                titleInput.addEventListener('input', () => clearFieldError('title-group'));
            }
            
            const dateInput = document.getElementById('event-date');
            if (dateInput) {
                dateInput.addEventListener('input', () => clearFieldError('date-group'));
            }
            
            const timeSelect = document.getElementById('event-time');
            if (timeSelect) {
                timeSelect.addEventListener('change', () => clearFieldError('time-group'));
            }
            
            const durationSelect = document.getElementById('event-duration');
            if (durationSelect) {
                durationSelect.addEventListener('change', () => clearFieldError('duration-group'));
            }
        }

        // Инициализация формы редактирования
        const editForm = document.getElementById('edit-event-form');
        if (editForm) {
            editForm.addEventListener('submit', validateAndSubmitEditForm);
            
            // Добавляем обработчики для очистки ошибок при вводе
            const editTitleInput = document.getElementById('edit-event-title');
            if (editTitleInput) {
                editTitleInput.addEventListener('input', () => clearFieldError('edit-title-group'));
            }
            
            const editDateInput = document.getElementById('edit-event-date');
            if (editDateInput) {
                editDateInput.addEventListener('input', () => clearFieldError('edit-date-group'));
            }
            
            const editTimeSelect = document.getElementById('edit-event-time');
            if (editTimeSelect) {
                editTimeSelect.addEventListener('change', () => clearFieldError('edit-time-group'));
            }
            
            const editDurationSelect = document.getElementById('edit-event-duration');
            if (editDurationSelect) {
                editDurationSelect.addEventListener('change', () => clearFieldError('edit-duration-group'));
            }
        }
    }

    function initAnimations() {
        // Анимация появления календарных ячеек
        const calendarDays = document.querySelectorAll('.calendar-day');
        calendarDays.forEach((day, index) => {
            day.style.opacity = '0';
            day.style.transform = 'translateY(20px)';

            setTimeout(() => {
                day.style.transition = 'all 0.3s ease';
                day.style.opacity = '1';
                day.style.transform = 'translateY(0)';
            }, index * 10);
        });
    }

    // Функции для работы с календарем
    function openEventForm(date) {
        const modal = document.getElementById('eventFormModal');
        if (modal) {
            // Устанавливаем выбранную дату
            const dateInput = document.getElementById('event-date');
            if (dateInput) {
                dateInput.value = date;
            }

            const timeSelect = document.getElementById('event-time');
            if (timeSelect) {
                timeSelect.value = '09:00';
            }

            const durationSelect = document.getElementById('event-duration');
            if (durationSelect) {
                durationSelect.value = '30';
            }

            modal.style.display = 'block';

            // Фокус на первое поле
            setTimeout(() => {
                const titleInput = document.getElementById('event-title');
                if (titleInput) {
                    titleInput.focus();
                }
            }, 100);
        }
    }

    function closeEventForm() {
        const modal = document.getElementById('eventFormModal');
        if (modal) {
            modal.style.display = 'none';

            // Сбрасываем форму
            const form = document.getElementById('add-event-form');
            if (form) {
                form.reset();
            }
        }
    }

    function closeEditEventModal() {
        const modal = document.getElementById('editEventModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';

            // Сбрасываем форму
            const form = document.getElementById('edit-event-form');
            if (form) {
                form.reset();
            }
        }
    }

    function validateAndSubmitForm(event) {
        event.preventDefault();
        
        // Сбрасываем все ошибки
        clearAllErrors();
        
        const title = document.getElementById('event-title');
        const date = document.getElementById('event-date');
        const timeSelect = document.getElementById('event-time');
        const duration = document.getElementById('event-duration');

        let isValid = true;

        // Проверка названия
        if (!title.value.trim()) {
            isValid = false;
            showFieldError('title-group', 'Заполните это поле.');
        }

        // Проверка даты
        if (!date.value) {
            isValid = false;
            showFieldError('date-group', 'Заполните это поле.');
        }

        // Проверка времени
        if (!timeSelect.value) {
            isValid = false;
            showFieldError('time-group', 'Выберите время приема.');
        }

        // Проверка длительности
        if (!duration.value) {
            isValid = false;
            showFieldError('duration-group', 'Выберите длительность приема.');
        }

        if (!isValid) {
            return;
        }

        // Отправляем форму
        submitEventForm();
    }

    function validateAndSubmitEditForm(event) {
        event.preventDefault();
        
        // Сбрасываем все ошибки
        clearAllErrors();
        
        const title = document.getElementById('edit-event-title');
        const date = document.getElementById('edit-event-date');
        const timeSelect = document.getElementById('edit-event-time');
        const duration = document.getElementById('edit-event-duration');

        let isValid = true;

        // Проверка названия
        if (!title.value.trim()) {
            isValid = false;
            showFieldError('edit-title-group', 'Заполните это поле.');
        }

        // Проверка даты
        if (!date.value) {
            isValid = false;
            showFieldError('edit-date-group', 'Заполните это поле.');
        }

        // Проверка времени
        if (!timeSelect.value) {
            isValid = false;
            showFieldError('edit-time-group', 'Выберите время приема.');
        }

        // Проверка длительности
        if (!duration.value) {
            isValid = false;
            showFieldError('edit-duration-group', 'Выберите длительность приема.');
        }

        if (!isValid) {
            return;
        }

        // Отправляем форму редактирования
        submitEditEventForm();
    }

    function showFieldError(groupId, message) {
        const group = document.getElementById(groupId);
        if (group) {
            group.classList.add('error');
            const errorMessage = group.querySelector('.error-message');
            if (errorMessage) {
                errorMessage.querySelector('span:last-child').textContent = message;
                errorMessage.style.display = 'flex';
            }
        }
    }

    function clearAllErrors() {
        const errorGroups = document.querySelectorAll('.form-group.error');
        errorGroups.forEach(group => {
            group.classList.remove('error');
            const errorMessage = group.querySelector('.error-message');
            if (errorMessage) {
                errorMessage.style.display = 'none';
            }
        });
    }

    function clearFieldError(fieldId) {
        const field = document.getElementById(fieldId);
        if (field) {
            field.classList.remove('error');
            const errorMessage = field.querySelector('.error-message');
            if (errorMessage) {
                errorMessage.style.display = 'none';
            }
        }
    }

    function submitEventForm() {
        const form = document.getElementById('add-event-form');
        const formData = new FormData(form);

        // Показываем индикатор загрузки
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Добавление...';
        submitBtn.disabled = true;

        // Формируем дату и время начала
        const dateValue = formData.get('date');
        const timeValue = formData.get('time');
        const durationValue = formData.get('duration');
        
        console.log('Значения формы (добавление):', { dateValue, timeValue, durationValue });
        
        // Проверяем корректность даты
        if (!dateValue || !timeValue) {
            showNotification('Ошибка: некорректные дата или время', 'error');
            return;
        }
        
        let startDateTime, endDateTime;
        
        try {
            // Создаем объект Date для начала события в локальном времени
            // Разбираем дату и время на компоненты
            const [year, month, day] = dateValue.split('-').map(Number);
            const [hours, minutes] = timeValue.split(':').map(Number);
            
            // Создаем дату в локальном времени (без конвертации в UTC)
            startDateTime = new Date(year, month - 1, day, hours, minutes, 0, 0);
            
            if (isNaN(startDateTime.getTime())) {
                showNotification('Ошибка: некорректная дата начала', 'error');
                return;
            }
            
            // Создаем объект Date для окончания события (добавляем длительность)
            endDateTime = new Date(startDateTime.getTime() + parseInt(durationValue) * 60 * 1000);
            
            if (isNaN(endDateTime.getTime())) {
                showNotification('Ошибка: некорректная дата окончания', 'error');
                return;
            }
            
            console.log('Созданные даты (добавление):', { startDateTime, endDateTime });
        } catch (e) {
            console.error('Ошибка при создании дат (добавление):', e);
            showNotification('Ошибка при обработке дат', 'error');
            return;
        }
        
        // Отправляем AJAX запрос для добавления события
        // Форматируем время в локальном формате, избегая конвертации в UTC
        const formatLocalDateTime = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            const seconds = String(date.getSeconds()).padStart(2, '0');
            return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
        };

        const postData = {
            action: 'addEvent',
            title: formData.get('title'),
            description: formData.get('description'),
            dateFrom: formatLocalDateTime(startDateTime),
            dateTo: formatLocalDateTime(endDateTime),
            branchId: getBranchId() || 1,
            eventColor: formData.get('event-color') || '#3498db'
        };
        
        // Логируем данные, которые отправляем
        console.log('submitEventForm: Отправляем данные:', postData);
        console.log('submitEventForm: Цвет события:', postData.eventColor);
        
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams(postData)
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            return response.text(); // Сначала получаем как текст
        })
        .then(text => {
            console.log('Raw response:', text);
            try {
                const data = JSON.parse(text);
                console.log('Parsed data:', data);
                
                if (data.success) {
                    showNotification('Событие добавлено успешно!', 'success');
                    closeEventForm();
                    form.reset();

                    // Динамически добавляем событие в календарь
                    addEventToCalendar({
                        id: data.eventId,
                        title: formData.get('title'),
                        description: formData.get('description'),
                        dateFrom: formatLocalDateTime(startDateTime),
                        dateTo: formatLocalDateTime(endDateTime),
                        eventColor: formData.get('event-color') || '#3498db'
                    });
                } else {
                    showNotification('Ошибка: ' + (data.error || 'Неизвестная ошибка'), 'error');
                }
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Raw response that failed to parse:', text);
                showNotification('Ошибка парсинга ответа сервера', 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка при добавлении события:', error);
            showNotification('Ошибка при добавлении события', 'error');
        })
        .finally(() => {
            // Восстанавливаем кнопку
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        });
    }

    function submitEditEventForm() {
        const form = document.getElementById('edit-event-form');
        const formData = new FormData(form);
        const eventId = form.getAttribute('data-event-id');

        if (!eventId) {
            showNotification('Ошибка: ID события не найден', 'error');
            return;
        }

        // Показываем индикатор загрузки
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Сохранение...';
        submitBtn.disabled = true;

        // Формируем дату и время начала
        const dateValue = formData.get('date');
        const timeValue = formData.get('time');
        const durationValue = formData.get('duration');
        
        console.log('Значения формы (редактирование):', { dateValue, timeValue, durationValue });
        
        // Проверяем корректность даты
        if (!dateValue || !timeValue) {
            showNotification('Ошибка: некорректные дата или время', 'error');
            return;
        }
        
        let startDateTime, endDateTime;
        
        try {
            // Создаем объект Date для начала события в локальном времени
            // Разбираем дату и время на компоненты
            const [year, month, day] = dateValue.split('-').map(Number);
            const [hours, minutes] = timeValue.split(':').map(Number);
            
            // Создаем дату в локальном времени (без конвертации в UTC)
            startDateTime = new Date(year, month - 1, day, hours, minutes, 0, 0);
            
            if (isNaN(startDateTime.getTime())) {
                showNotification('Ошибка: некорректная дата начала', 'error');
                return;
            }
            
            // Создаем объект Date для окончания события (добавляем длительность)
            endDateTime = new Date(startDateTime.getTime() + parseInt(durationValue) * 60 * 1000);
            
            if (isNaN(endDateTime.getTime())) {
                showNotification('Ошибка: некорректная дата окончания', 'error');
                return;
            }
            
            console.log('Созданные даты (редактирование):', { startDateTime, endDateTime });
        } catch (e) {
            console.error('Ошибка при создании дат (редактирование):', e);
            showNotification('Ошибка при обработке дат', 'error');
            return;
        }
        
        // Получаем цвет события из скрытого поля
        const eventColor = document.getElementById('edit-selected-color').value || '#3498db';
        
        // Отправляем AJAX запрос для обновления события
        // Форматируем время в локальном формате, избегая конвертации в UTC
        const formatLocalDateTime = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            const seconds = String(date.getSeconds()).padStart(2, '0');
            return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
        };

        const postData = {
            action: 'updateEvent',
            eventId: eventId,
            title: formData.get('title'),
            description: formData.get('description'),
            dateFrom: formatLocalDateTime(startDateTime),
            dateTo: formatLocalDateTime(endDateTime),
            eventColor: eventColor,
            branchId: getBranchId() || 1
        };
        
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams(postData)
        })
        .then(response => response.text())
        .then(text => {
            console.log('Raw response:', text);
            try {
                const data = JSON.parse(text);
                console.log('Parsed data:', data);
                
                if (data.success) {
                    showNotification('Событие обновлено успешно!', 'success');
                    closeEditEventModal();
                    form.reset();

                    // Обновляем событие в календаре
                    updateEventInCalendar({
                        id: eventId,
                        title: formData.get('title'),
                        description: formData.get('description'),
                        dateFrom: formatLocalDateTime(startDateTime),
                        dateTo: formatLocalDateTime(endDateTime),
                        eventColor: eventColor
                    });
                } else {
                    showNotification('Ошибка: ' + (data.error || 'Неизвестная ошибка'), 'error');
                }
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.error('Raw response that failed to parse:', text);
                showNotification('Ошибка парсинга ответа сервера', 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка при обновлении события:', error);
            showNotification('Ошибка при обновлении события', 'error');
        })
        .finally(() => {
            // Восстанавливаем кнопку
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        });
    }

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

    function searchEvents(query) {
        console.log('Поиск событий:', query);

        // Здесь можно добавить AJAX запрос для поиска событий
        // Пока просто подсвечиваем ячейки с событиями
        const calendarDays = document.querySelectorAll('.calendar-day');
        calendarDays.forEach(day => {
            const events = day.querySelectorAll('.calendar-event');
            let hasMatch = false;

            events.forEach(event => {
                const title = event.querySelector('.event-title').textContent.toLowerCase();
                if (title.includes(query.toLowerCase())) {
                    hasMatch = true;
                    event.style.background = '#f39c12';
                } else {
                    event.style.background = '#27ae60';
                }
            });

            if (hasMatch) {
                day.style.background = '#fff3cd';
            }
        });
    }

    function clearSearch() {
        const calendarDays = document.querySelectorAll('.calendar-day');
        calendarDays.forEach(day => {
            day.style.background = '';
            const events = day.querySelectorAll('.calendar-event');
            events.forEach(event => {
                event.style.background = '#27ae60';
            });
        });
    }

    function showFreeSlots() {
        console.log('Показываем свободные слоты');
        // Здесь можно добавить логику для показа свободных слотов
    }

    function hideFreeSlots() {
        console.log('Скрываем свободные слоты');
        // Здесь можно добавить логику для скрытия свободных слотов
    }

    function showEventDetails(eventId) {
        console.log('showEventDetails: Показываем детали события:', eventId);
        openEditEventModal(eventId);
    }

    function openEditEventModal(eventId) {
        // Получаем данные события по AJAX
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'getEvent',
                eventId: eventId
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log('showEventDetails: Получены данные события:', data);
            
            if (data.success && data.event) {
                const event = data.event;
                console.log('showEventDetails: Данные события для заполнения формы:', event);
                console.log('showEventDetails: DATE_FROM:', event.DATE_FROM, 'тип:', typeof event.DATE_FROM);
                console.log('showEventDetails: DATE_TO:', event.DATE_TO, 'тип:', typeof event.DATE_TO);
                console.log('showEventDetails: Длина DATE_FROM:', event.DATE_FROM ? event.DATE_FROM.length : 'undefined');
                console.log('showEventDetails: Длина DATE_TO:', event.DATE_TO ? event.DATE_TO.length : 'undefined');
                
                // Заполняем форму данными события
                const titleInput = document.getElementById('edit-event-title');
                const dateInput = document.getElementById('edit-event-date');
                const timeInput = document.getElementById('edit-event-time');
                const durationInput = document.getElementById('edit-event-duration');
                const descriptionInput = document.getElementById('edit-event-description');
                const colorInput = document.getElementById('edit-selected-color');
                
                // Проверяем, что все поля найдены
                console.log('showEventDetails: Заполняем форму события:', event.TITLE);
                console.log('showEventDetails: Найденные поля:');
                console.log('  - titleInput:', titleInput);
                console.log('  - dateInput:', dateInput);
                console.log('  - timeInput:', timeInput);
                console.log('  - durationInput:', durationInput);
                console.log('  - descriptionInput:', descriptionInput);
                console.log('  - colorInput:', colorInput);
                
                if (titleInput) titleInput.value = event.TITLE || '';
                if (dateInput) {
                    try {
                        console.log('showEventDetails: Извлекаем дату из DATE_FROM:', event.DATE_FROM);
                        
                        // Извлекаем дату из строки, избегая проблем с часовыми поясами
                        let dateMatch;
                        let dateSet = false;
                        
                        if (event.DATE_FROM.includes(' ')) {
                            // Проверяем российский формат: "04.08.2025 12:00:00"
                            dateMatch = event.DATE_FROM.match(/^(\d{1,2})\.(\d{1,2})\.(\d{4})/);
                            if (dateMatch) {
                                console.log('showEventDetails: Найден российский формат даты:', dateMatch);
                                const [, day, month, year] = dateMatch;
                                const formattedDate = `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
                                console.log('showEventDetails: Преобразованная дата:', formattedDate);
                                dateInput.value = formattedDate;
                                console.log('showEventDetails: Дата установлена из российского формата:', dateInput.value);
                                dateSet = true;
                            } else {
                                // Проверяем стандартный формат: "2025-08-04 12:00:00"
                                dateMatch = event.DATE_FROM.match(/^(\d{4})-(\d{1,2})-(\d{1,2})/);
                                console.log('showEventDetails: Ищем дату с пробелом, результат:', dateMatch);
                            }
                        } else {
                            // Если дата без пробела, проверяем ISO формат
                            dateMatch = event.DATE_FROM.match(/^(\d{4})-(\d{1,2})-(\d{1,2})/);
                            console.log('showEventDetails: Ищем дату без пробела, результат:', dateMatch);
                        }
                        
                        if (!dateSet && dateMatch) {
                            const year = dateMatch[1];
                            const month = dateMatch[2].padStart(2, '0');
                            const day = dateMatch[3].padStart(2, '0');
                            dateInput.value = `${year}-${month}-${day}`;
                            console.log('showEventDetails: Дата извлечена:', dateInput.value);
                            dateSet = true;
                        }
                        
                        if (!dateSet) {
                            console.error('showEventDetails: Не удалось извлечь дату из строки:', event.DATE_FROM);
                            // Устанавливаем текущую дату без конвертации в UTC
                            const today = new Date();
                            const year = today.getFullYear();
                            const month = String(today.getMonth() + 1).padStart(2, '0');
                            const day = String(today.getDate()).padStart(2, '0');
                            dateInput.value = `${year}-${month}-${day}`;
                            console.log('showEventDetails: Установлена текущая дата:', dateInput.value);
                        }
                    } catch (e) {
                        console.error('showEventDetails: Ошибка при обработке даты DATE_FROM:', e);
                        // Устанавливаем текущую дату без конвертации в UTC
                        const today = new Date();
                        const year = today.getFullYear();
                        const month = String(today.getMonth() + 1).padStart(2, '0');
                        const day = String(today.getDate()).padStart(2, '0');
                        dateInput.value = `${year}-${month}-${day}`;
                    }
                    
                    // Логируем финальное значение поля даты
                    console.log('showEventDetails: Финальное значение поля даты:', dateInput.value);
                }
                if (timeInput) {
                    try {
                        console.log('showEventDetails: Извлекаем время из DATE_FROM:', event.DATE_FROM);
                        
                        // Извлекаем время из строки DATE_FROM, избегая проблем с часовыми поясами
                        let timeMatch;
                        if (event.DATE_FROM.includes(' ')) {
                            // Если дата в формате "2025-08-04 12:00:00"
                            timeMatch = event.DATE_FROM.match(/\s+(\d{1,2}):(\d{1,2}):(\d{1,2})$/);
                            console.log('showEventDetails: Ищем время с пробелом, результат:', timeMatch);
                        } else {
                            // Если дата в ISO формате (с T)
                            timeMatch = event.DATE_FROM.match(/T(\d{1,2}):(\d{1,2}):(\d{1,2})/);
                            console.log('showEventDetails: Ищем время с T, результат:', timeMatch);
                        }
                        
                        if (timeMatch) {
                            const hours = timeMatch[1].padStart(2, '0');
                            const minutes = timeMatch[2].padStart(2, '0');
                            timeInput.value = `${hours}:${minutes}`;
                            console.log('showEventDetails: Время установлено:', timeInput.value);
                        } else {
                            console.error('showEventDetails: Не удалось извлечь время из строки:', event.DATE_FROM);
                            timeInput.value = '09:00';
                        }
                    } catch (e) {
                        console.error('showEventDetails: Ошибка при обработке времени DATE_FROM:', e);
                        timeInput.value = '09:00';
                    }
                }
                if (durationInput) {
                    try {
                        console.log('showEventDetails: Рассчитываем длительность из DATE_FROM:', event.DATE_FROM, 'и DATE_TO:', event.DATE_TO);
                        
                        // Извлекаем время начала и окончания для расчета длительности
                        let startTimeMatch, endTimeMatch;
                        
                        if (event.DATE_FROM.includes(' ')) {
                            // Если дата в формате "2025-08-04 12:00:00"
                            startTimeMatch = event.DATE_FROM.match(/\s+(\d{1,2}):(\d{1,2}):(\d{1,2})$/);
                            console.log('showEventDetails: Время начала с пробелом:', startTimeMatch);
                        } else {
                            // Если дата в ISO формате (с T)
                            startTimeMatch = event.DATE_FROM.match(/T(\d{1,2}):(\d{1,2}):(\d{1,2})/);
                            console.log('showEventDetails: Время начала с T:', startTimeMatch);
                        }
                        
                        if (event.DATE_TO.includes(' ')) {
                            // Если дата в формате "2025-08-04 12:00:00"
                            endTimeMatch = event.DATE_TO.match(/\s+(\d{1,2}):(\d{1,2}):(\d{1,2})$/);
                            console.log('showEventDetails: Время окончания с пробелом:', endTimeMatch);
                        } else {
                            // Если дата в ISO формате (с T)
                            endTimeMatch = event.DATE_TO.match(/T(\d{1,2}):(\d{1,2}):(\d{1,2})/);
                            console.log('showEventDetails: Время окончания с T:', endTimeMatch);
                        }
                        
                        if (startTimeMatch && endTimeMatch) {
                            const startHours = parseInt(startTimeMatch[1]);
                            const startMinutes = parseInt(startTimeMatch[2]);
                            const endHours = parseInt(endTimeMatch[1]);
                            const endMinutes = parseInt(endTimeMatch[2]);
                            
                            console.log('showEventDetails: Время начала:', startHours + ':' + startMinutes, 'Время окончания:', endHours + ':' + endMinutes);
                            
                            // Рассчитываем разницу в минутах
                            const startTotalMinutes = startHours * 60 + startMinutes;
                            const endTotalMinutes = endHours * 60 + endMinutes;
                            const durationMinutes = endTotalMinutes - startTotalMinutes;
                            
                            if (durationMinutes > 0) {
                                durationInput.value = durationMinutes;
                                console.log('showEventDetails: Длительность рассчитана:', durationMinutes, 'минут');
                            } else {
                                console.error('showEventDetails: Некорректная длительность:', durationMinutes);
                                durationInput.value = '30';
                            }
                        } else {
                            console.error('showEventDetails: Не удалось извлечь время для расчета длительности');
                            durationInput.value = '30';
                        }
                    } catch (e) {
                        console.error('showEventDetails: Ошибка при расчете длительности:', e);
                        durationInput.value = '30';
                    }
                }
                if (descriptionInput) descriptionInput.value = event.DESCRIPTION || '';
                if (colorInput) {
                    const eventColor = event.EVENT_COLOR || '#3498db';
                    console.log('showEventDetails: Устанавливаем цвет события:', eventColor);
                    colorInput.value = eventColor;
                    
                    // Обновляем цветовой пикер
                    const customColorInput = document.getElementById('edit-custom-color-input');
                    const selectedColorInput = document.getElementById('edit-selected-color');
                    
                    console.log('showEventDetails: Найденные элементы цветового пикера:');
                    console.log('  - customColorInput:', customColorInput);
                    console.log('  - selectedColorInput:', selectedColorInput);
                    
                    if (customColorInput) {
                        customColorInput.value = eventColor;
                        console.log('showEventDetails: Установлен customColorInput:', customColorInput.value);
                    }
                    if (selectedColorInput) {
                        selectedColorInput.value = eventColor;
                        console.log('showEventDetails: Установлен selectedColorInput:', selectedColorInput.value);
                    }
                    
                    // Убираем активный класс со всех пресетов
                    const editModal = document.getElementById('editEventModal');
                    if (editModal) {
                        const colorPresets = editModal.querySelectorAll('.color-preset');
                        console.log('showEventDetails: Найдено пресетов цветов:', colorPresets.length);
                        
                        colorPresets.forEach(preset => {
                            preset.classList.remove('active');
                        });
                        
                        // Находим и активируем соответствующий пресет
                        const matchingPreset = editModal.querySelector(`[data-color="${eventColor}"]`);
                        console.log('showEventDetails: Найденный пресет для цвета', eventColor, ':', matchingPreset);
                        
                        if (matchingPreset) {
                            matchingPreset.classList.add('active');
                            console.log('showEventDetails: Активирован пресет цвета:', eventColor);
                        } else {
                            console.log('showEventDetails: Пресет для цвета', eventColor, 'не найден, активируем кастомный цвет');
                            // Если пресет не найден, активируем кастомный цвет
                            if (customColorInput) {
                                customColorInput.focus();
                            }
                        }
                    }
                } else {
                    console.error('showEventDetails: Поле colorInput не найдено');
                }
                
                // Устанавливаем ID события для формы
                const editForm = document.getElementById('edit-event-form');
                if (editForm) {
                    editForm.setAttribute('data-event-id', eventId);
                }
                
                // Показываем модальное окно
                const modal = document.getElementById('editEventModal');
                if (modal) {
                    modal.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                }
            } else {
                showNotification('Ошибка при загрузке события', 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка при загрузке события:', error);
            showNotification('Ошибка при загрузке события', 'error');
        });
    }

    function getBranchId() {
        // Получаем ID филиала из данных страницы или возвращаем 0
        const branchElement = document.querySelector('[data-branch-id]');
        return branchElement ? branchElement.getAttribute('data-branch-id') : 0;
    }

    // Функции для работы с модальным окном расписания
    function openScheduleModal() {
        const modal = document.getElementById('scheduleModal');
        if (modal) {
            // Устанавливаем текущую дату по умолчанию
            const today = new Date();
            // Форматируем дату в локальном формате, избегая проблем с часовыми поясами
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');
            const todayString = `${year}-${month}-${day}`;
            
            const dateInput = document.getElementById('schedule-date');
            if (dateInput) {
                dateInput.value = todayString;
            }

            // Устанавливаем текущее время по умолчанию
            const timeInput = document.getElementById('schedule-time');
            if (timeInput) {
                const now = new Date();
                const hours = now.getHours().toString().padStart(2, '0');
                const minutes = now.getMinutes().toString().padStart(2, '0');
                timeInput.value = `${hours}:${minutes}`;
            }

            // Сбрасываем форму
            document.getElementById('scheduleForm').reset();
            document.getElementById('schedule-repeat').checked = false;
            document.getElementById('repeat-fields').style.display = 'none';
            
            // Скрываем дополнительные поля
            const weeklyDays = document.getElementById('weekly-days');
            if (weeklyDays) weeklyDays.style.display = 'none';
            
            // Инициализируем поля окончания повторения
            toggleEndFields();

            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
    }

    function closeScheduleModal() {
        const modal = document.getElementById('scheduleModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    }

    function toggleRepeatFields() {
        const repeatCheckbox = document.getElementById('schedule-repeat');
        const repeatFields = document.getElementById('repeat-fields');
        
        if (repeatCheckbox.checked) {
            repeatFields.style.display = 'block';
        } else {
            repeatFields.style.display = 'none';
            
            // Скрываем дни недели при отключении повторения
            const weeklyDays = document.getElementById('weekly-days');
            if (weeklyDays) {
                weeklyDays.style.display = 'none';
            }
        }
    }
    
    // Функция для переключения отображения дней недели
    function toggleWeeklyDays() {
        const frequency = document.getElementById('schedule-frequency').value;
        const weeklyDays = document.getElementById('weekly-days');
        
        if (frequency === 'weekly') {
            weeklyDays.style.display = 'block';
        } else {
            weeklyDays.style.display = 'none';
        }
    }
    
    // Функция для переключения полей окончания повторения
    function toggleEndFields() {
        const repeatEnd = document.querySelector('input[name="repeat-end"]:checked').value;
        const repeatCountInput = document.querySelector('.repeat-count-input');
        const repeatEndDateInput = document.querySelector('.repeat-end-date-input');
        
        // Скрываем все поля
        if (repeatCountInput) repeatCountInput.style.display = 'none';
        if (repeatEndDateInput) repeatEndDateInput.style.display = 'none';
        
        // Показываем нужные поля
        if (repeatEnd === 'after') {
            if (repeatCountInput) repeatCountInput.style.display = 'inline-block';
        } else if (repeatEnd === 'date') {
            if (repeatEndDateInput) repeatEndDateInput.style.display = 'inline-block';
        }
    }
    
    // Функция для выбора предустановленного цвета
    function selectPresetColor(color) {
        document.getElementById('selected-color').value = color;
        document.getElementById('custom-color-input').value = color;
        
        // Обновляем активный класс для пресетов
        document.querySelectorAll('.color-preset').forEach(preset => {
            preset.classList.remove('active');
        });
        event.target.classList.add('active');
    }
    
    // Функция для выбора кастомного цвета
    function selectCustomColor(color) {
        document.getElementById('selected-color').value = color;
        
        // Убираем активный класс со всех пресетов
        document.querySelectorAll('.color-preset').forEach(preset => {
            preset.classList.remove('active');
        });
    }

    // Функция для выбора предустановленного цвета в форме редактирования
    function selectEditPresetColor(color) {
        document.getElementById('edit-selected-color').value = color;
        document.getElementById('edit-custom-color-input').value = color;
        
        // Обновляем активный класс для пресетов
        const editModal = document.getElementById('editEventModal');
        if (editModal) {
            editModal.querySelectorAll('.color-preset').forEach(preset => {
                preset.classList.remove('active');
            });
            event.target.classList.add('active');
        }
    }
    
    // Функция для выбора кастомного цвета в форме редактирования
    function selectEditCustomColor(color) {
        document.getElementById('edit-selected-color').value = color;
        
        // Убираем активный класс со всех пресетов
        const editModal = document.getElementById('editEventModal');
        if (editModal) {
            editModal.querySelectorAll('.color-preset').forEach(preset => {
                preset.classList.remove('active');
            });
        }
    }
    
    function initColorPicker() {
        // Устанавливаем первый цвет как активный по умолчанию
        const firstPreset = document.querySelector('.color-preset');
        if (firstPreset) {
            firstPreset.classList.add('active');
        }
        
        // Устанавливаем значение по умолчанию для скрытого поля
        const selectedColorInput = document.getElementById('selected-color');
        if (selectedColorInput) {
            selectedColorInput.value = '#3498db';
        }
    }
    
    // Функция для сохранения расписания через AJAX
    function saveSchedule(scheduleData) {
        // Показываем индикатор загрузки
        const submitBtn = document.querySelector('#scheduleForm button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Сохранение...';
        submitBtn.disabled = true;
        
        // Подготавливаем данные для отправки
        const postData = {
            action: 'addSchedule',
            title: scheduleData.title,
            date: scheduleData.date,
            time: scheduleData.time,
            repeat: scheduleData.repeat,
            frequency: scheduleData.frequency || null,
            weekdays: scheduleData.weekdays || [],
            repeatEnd: scheduleData.repeatEnd || 'never',
            repeatCount: scheduleData.repeatCount || null,
            repeatEndDate: scheduleData.repeatEndDate || null,
            eventColor: scheduleData.eventColor || '#3498db'
        };
        
        // Отправляем AJAX запрос
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams(postData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Расписание успешно создано!', 'success');
                closeScheduleModal();
                
                // Динамически добавляем события расписания в календарь
                if (data.events && Array.isArray(data.events)) {
                    data.events.forEach(event => {
                        addEventToCalendar({
                            id: event.ID,
                            title: event.TITLE,
                            description: event.DESCRIPTION,
                            dateFrom: event.DATE_FROM,
                            dateTo: event.DATE_TO
                        });
                    });
                }
            } else {
                showNotification('Ошибка: ' + (data.error || 'Неизвестная ошибка'), 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка при сохранении расписания:', error);
            showNotification('Ошибка при сохранении расписания', 'error');
        })
        .finally(() => {
            // Восстанавливаем кнопку
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        });
    }

    // Обработка отправки формы расписания
    document.addEventListener('DOMContentLoaded', function() {
        const scheduleForm = document.getElementById('scheduleForm');
        if (scheduleForm) {
            scheduleForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const scheduleData = {
                    title: formData.get('title'),
                    date: formData.get('date'),
                    time: formData.get('time'),
                    repeat: formData.get('repeat') === 'on',
                    frequency: formData.get('frequency'),
                    weekdays: formData.getAll('weekdays[]'),
                    repeatEnd: formData.get('repeat-end'),
                    repeatCount: formData.get('repeat-count'),
                    repeatEndDate: formData.get('repeat-end-date'),
                    eventColor: formData.get('event-color')
                };

                console.log('Данные расписания:', scheduleData);
                
                // Отправляем AJAX запрос для сохранения расписания
                saveSchedule(scheduleData);
            });
        }

        // Закрытие модального окна при клике вне его
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('scheduleModal');
            if (event.target === modal) {
                closeScheduleModal();
            }
        });

        // Закрытие по Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeScheduleModal();
            }
        });
    });

    // Экспорт функций для использования в других скриптах
    window.ArtmaxCalendar = {
        openEventForm: openEventForm,
        closeEventForm: closeEventForm,
        openEditEventModal: openEditEventModal,
        closeEditEventModal: closeEditEventModal,
        openScheduleModal: openScheduleModal,
        closeScheduleModal: closeScheduleModal,
        showNotification: showNotification,
        searchEvents: searchEvents,
        clearSearch: clearSearch,
        toggleWeeklyDays: toggleWeeklyDays,
        toggleEndFields: toggleEndFields,
        selectPresetColor: selectPresetColor,
        selectCustomColor: selectCustomColor,
        selectEditPresetColor: selectEditPresetColor,
        selectEditCustomColor: selectEditCustomColor,
        initColorPicker: initColorPicker,
        saveSchedule: saveSchedule,
        addEventToCalendar: addEventToCalendar,
        updateEventInCalendar: updateEventInCalendar,
        deleteEvent: deleteEvent,
        deleteEventAjax: deleteEventAjax
    };

    // Тестовая функция для проверки извлечения даты
    window.testDateExtraction = function(dateString) {
        console.log('=== ТЕСТ ИЗВЛЕЧЕНИЯ ДАТЫ ===');
        console.log('Входная строка:', dateString);
        
        let dateMatch;
        let dateSet = false;
        
        if (dateString.includes(' ')) {
            // Проверяем российский формат: "04.08.2025 12:00:00"
            dateMatch = dateString.match(/^(\d{1,2})\.(\d{1,2})\.(\d{4})/);
            if (dateMatch) {
                console.log('Найден российский формат даты:', dateMatch);
                const [, day, month, year] = dateMatch;
                const formattedDate = `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
                console.log('Преобразованная дата:', formattedDate);
                dateSet = true;
            } else {
                // Проверяем стандартный формат: "2025-08-04 12:00:00"
                dateMatch = dateString.match(/^(\d{4})-(\d{1,2})-(\d{1,2})/);
                console.log('Ищем дату с пробелом, результат:', dateMatch);
            }
        } else {
            // Если дата без пробела, проверяем ISO формат
            dateMatch = dateString.match(/^(\d{4})-(\d{1,2})-(\d{1,2})/);
            console.log('Ищем дату без пробела, результат:', dateMatch);
        }
        
        if (!dateSet && dateMatch) {
            const year = dateMatch[1];
            const month = dateMatch[2].padStart(2, '0');
            const day = dateMatch[3].padStart(2, '0');
            const result = `${year}-${month}-${day}`;
            console.log('Дата извлечена:', result);
            dateSet = true;
        }
        
        if (!dateSet) {
            console.error('Не удалось извлечь дату из строки:', dateString);
        }
        
        console.log('=== КОНЕЦ ТЕСТА ===');
    };

    // Делаем функции доступными глобально для использования в HTML
    window.closeEditEventModal = closeEditEventModal;
    window.closeEventForm = closeEventForm;
    window.openEventForm = openEventForm;
    window.openEditEventModal = openEditEventModal;
    window.openScheduleModal = openScheduleModal;
    window.closeScheduleModal = closeScheduleModal;
    window.toggleWeeklyDays = toggleWeeklyDays;
    window.toggleEndFields = toggleEndFields;
    window.selectPresetColor = selectPresetColor;
    window.selectCustomColor = selectCustomColor;
    window.selectEditPresetColor = selectEditPresetColor;
    window.selectEditCustomColor = selectEditCustomColor;
    window.previousMonth = previousMonth;
    window.nextMonth = nextMonth;
    window.goToToday = goToToday;
    window.refreshCalendarEvents = refreshCalendarEvents;
    window.deleteEventAjax = deleteEventAjax;

    /**
     * Динамически добавляет событие в календарь с анимацией
     */
    function addEventToCalendar(eventData) {
        // Логируем данные события
        console.log('addEventToCalendar: Получены данные события:', eventData);
        console.log('addEventToCalendar: Цвет события:', eventData.eventColor);
        
        // Создаем объект Date из строки времени, избегая проблем с часовыми поясами
        let dateFrom;
        if (typeof eventData.dateFrom === 'string' && eventData.dateFrom.includes(' ')) {
            // Если дата в формате "2025-08-04 12:00:00", парсим компоненты отдельно
            const [datePart, timePart] = eventData.dateFrom.split(' ');
            const [year, month, day] = datePart.split('-').map(Number);
            const [hours, minutes, seconds] = timePart.split(':').map(Number);
            // Создаем Date в локальном времени (month - 1, так как в JS месяцы начинаются с 0)
            dateFrom = new Date(year, month - 1, day, hours, minutes, seconds);
        } else {
            // Если дата в ISO формате
            dateFrom = new Date(eventData.dateFrom);
        }
        
        // Получаем ключ даты в формате YYYY-MM-DD
        let dateKey;
        if (typeof eventData.dateFrom === 'string' && eventData.dateFrom.includes(' ')) {
            // Если дата в формате "2025-08-04 12:00:00"
            dateKey = eventData.dateFrom.split(' ')[0];
        } else {
            // Если дата в ISO формате, извлекаем дату без конвертации
            dateKey = eventData.dateFrom.split('T')[0];
        }
        
        // Находим ячейку календаря для этой даты
        const calendarDay = document.querySelector(`[data-date="${dateKey}"]`);
        
        if (!calendarDay) {
            console.error('Не найдена ячейка календаря для даты:', dateKey);
            return;
        }
        
        // Создаем элемент события
        const eventElement = document.createElement('div');
        eventElement.className = 'calendar-event new-event';
        eventElement.setAttribute('data-event-id', eventData.id);
        
        // Применяем цвет события
        if (eventData.eventColor) {
            eventElement.style.borderLeft = `4px solid ${eventData.eventColor}`;
            eventElement.style.backgroundColor = `${eventData.eventColor}15`;
        }
        
        // Форматируем время, избегая проблем с часовыми поясами
        console.log('addEventToCalendar: eventData.dateFrom =', eventData.dateFrom);
        
        let timeString;
        if (typeof eventData.dateFrom === 'string') {
            // Если дата в формате "2025-08-04 12:00:00", извлекаем время напрямую
            const timeMatch = eventData.dateFrom.match(/(\d{2}):(\d{2}):(\d{2})$/);
            if (timeMatch) {
                timeString = `${timeMatch[1]}:${timeMatch[2]}`;
                console.log('addEventToCalendar: Время извлечено из пробела:', timeString);
            } else {
                // Если дата в ISO формате (с T), извлекаем время
                const isoTimeMatch = eventData.dateFrom.match(/T(\d{2}):(\d{2}):/);
                if (isoTimeMatch) {
                    timeString = `${isoTimeMatch[1]}:${isoTimeMatch[2]}`;
                    console.log('addEventToCalendar: Время извлечено из T:', timeString);
                } else {
                    // Fallback на локальное время
                    timeString = dateFrom.toLocaleTimeString('ru-RU', { 
                        hour: '2-digit', 
                        minute: '2-digit',
                        hour12: false
                    });
                    console.log('addEventToCalendar: Время извлечено через toLocaleTimeString:', timeString);
                }
            }
        } else {
            // Fallback на локальное время
            timeString = dateFrom.toLocaleTimeString('ru-RU', { 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: false
            });
            console.log('addEventToCalendar: Время извлечено через toLocaleTimeString (fallback):', timeString);
        }
        
        eventElement.innerHTML = `
            <div class="event-dot"></div>
            <span class="event-title">${eventData.title}</span>
            <span class="event-time">${timeString}</span>
        `;
        
        // Добавляем обработчик клика для просмотра деталей
        eventElement.addEventListener('click', function(e) {
            e.stopPropagation();
            showEventDetails(eventData.id);
        });
        
        // Добавляем событие в ячейку календаря
        calendarDay.appendChild(eventElement);
        
        // Анимация появления с мерцанием
        eventElement.style.opacity = '0';
        eventElement.style.transform = 'scale(0.8)';
        
        // Плавное появление
        setTimeout(() => {
            eventElement.style.transition = 'all 0.3s ease-out';
            eventElement.style.opacity = '1';
            eventElement.style.transform = 'scale(1)';
        }, 100);
        
        // Эффект мерцания в течение 2-3 секунд
        let blinkCount = 0;
        const maxBlinks = 6; // 3 секунды (6 мерцаний по 0.5 сек)
        
        const blinkInterval = setInterval(() => {
            if (blinkCount >= maxBlinks) {
                clearInterval(blinkInterval);
                eventElement.style.boxShadow = 'none';
                eventElement.classList.remove('new-event');
                return;
            }
            
            if (blinkCount % 2 === 0) {
                eventElement.style.boxShadow = '0 0 20px rgba(52, 152, 219, 0.8)';
            } else {
                eventElement.style.boxShadow = 'none';
            }
            
            blinkCount++;
        }, 500);
        
        // Убираем класс new-event через 3 секунды
        setTimeout(() => {
            eventElement.classList.remove('new-event');
        }, 3000);
    }

    /**
     * Обновляет события календаря по AJAX
     */
    function refreshCalendarEvents() {
        const branchId = getBranchId() || 1;
        
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'getEvents',
                branchId: branchId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.events) {
                updateCalendarEvents(data.events);
            }
        })
        .catch(error => {
            console.error('Ошибка при обновлении событий:', error);
        });
    }

    /**
     * Обновляет события в календаре
     */
    function updateCalendarEvents(events) {
        // Очищаем все существующие события
        const existingEvents = document.querySelectorAll('.calendar-event');
        existingEvents.forEach(event => event.remove());
        
        // Группируем события по датам
        const eventsByDate = {};
        events.forEach(event => {
            console.log('updateCalendarEvents: Обрабатываем событие:', event);
            console.log('updateCalendarEvents: event.DATE_FROM =', event.DATE_FROM);
            
            // Получаем ключ даты, избегая проблем с часовыми поясами
            let dateKey;
            if (typeof event.DATE_FROM === 'string' && event.DATE_FROM.includes(' ')) {
                // Если дата в формате "2025-08-04 12:00:00"
                dateKey = event.DATE_FROM.split(' ')[0];
                console.log('updateCalendarEvents: Дата извлечена из пробела:', dateKey);
            } else {
                // Если дата в ISO формате, извлекаем дату без конвертации
                dateKey = event.DATE_FROM.split('T')[0];
                console.log('updateCalendarEvents: Дата извлечена из T:', dateKey);
            }
            
            if (!eventsByDate[dateKey]) {
                eventsByDate[dateKey] = [];
            }
            eventsByDate[dateKey].push(event);
        });
        
        // Добавляем события в соответствующие ячейки
        Object.keys(eventsByDate).forEach(dateKey => {
            const calendarDay = document.querySelector(`[data-date="${dateKey}"]`);
            if (calendarDay) {
                eventsByDate[dateKey].forEach(event => {
                    const eventElement = createEventElement(event);
                    calendarDay.appendChild(eventElement);
                });
            }
        });
    }

    /**
     * Обновляет событие в календаре
     */
    function updateEventInCalendar(eventData) {
        const eventId = eventData.id;
        const eventElement = document.querySelector(`[data-event-id="${eventId}"]`);
        
        console.log('updateEventInCalendar: Обновляем событие ID:', eventId);
        console.log('updateEventInCalendar: Данные события:', eventData);
        
        if (eventElement) {
            // Получаем текущую дату события (старую дату)
            const currentParent = eventElement.parentElement;
            const currentDateKey = currentParent.getAttribute('data-date');
            console.log('updateEventInCalendar: Текущая дата события:', currentDateKey);
            
            // Получаем новую дату из eventData.dateFrom
            let newDateKey;
            if (typeof eventData.dateFrom === 'string' && eventData.dateFrom.includes(' ')) {
                // Если дата в формате "2025-08-04 12:00:00"
                newDateKey = eventData.dateFrom.split(' ')[0];
            } else {
                // Если дата в ISO формате, извлекаем дату без конвертации
                newDateKey = eventData.dateFrom.split('T')[0];
            }
            console.log('updateEventInCalendar: Новая дата события:', newDateKey);
            
            // Проверяем, нужно ли перемещать событие на другую дату
            if (currentDateKey !== newDateKey) {
                console.log('updateEventInCalendar: Перемещаем событие с', currentDateKey, 'на', newDateKey);
                
                // Находим новую ячейку календаря
                const newCalendarDay = document.querySelector(`[data-date="${newDateKey}"]`);
                
                if (newCalendarDay) {
                    // Анимация исчезновения со старой позиции
                    eventElement.style.transition = 'all 0.3s ease';
                    eventElement.style.transform = 'scale(0.8)';
                    eventElement.style.opacity = '0';
                    
                    setTimeout(() => {
                        // Удаляем событие со старой позиции
                        eventElement.remove();
                        
                        // Создаем новое событие на новой позиции
                        const newEventElement = createEventElement({
                            ID: eventId,
                            TITLE: eventData.title,
                            DESCRIPTION: eventData.description || '',
                            DATE_FROM: eventData.dateFrom,
                            DATE_TO: eventData.dateTo,
                            EVENT_COLOR: eventData.eventColor || '#3498db'
                        });
                        
                        // Добавляем событие в новую ячейку
                        newCalendarDay.appendChild(newEventElement);
                        
                        // Анимация появления на новой позиции
                        newEventElement.style.opacity = '0';
                        newEventElement.style.transform = 'scale(0.8)';
                        
                        setTimeout(() => {
                            newEventElement.style.transition = 'all 0.3s ease';
                            newEventElement.style.opacity = '1';
                            newEventElement.style.transform = 'scale(1)';
                            
                            // Эффект мерцания для привлечения внимания
                            let blinkCount = 0;
                            const maxBlinks = 4;
                            
                            const blinkInterval = setInterval(() => {
                                if (blinkCount >= maxBlinks) {
                                    clearInterval(blinkInterval);
                                    newEventElement.style.boxShadow = 'none';
                                    return;
                                }
                                
                                if (blinkCount % 2 === 0) {
                                    newEventElement.style.boxShadow = '0 0 20px rgba(52, 152, 219, 0.8)';
                                } else {
                                    newEventElement.style.boxShadow = 'none';
                                }
                                
                                blinkCount++;
                            }, 300);
                        }, 100);
                    }, 300);
                } else {
                    console.error('updateEventInCalendar: Не найдена ячейка календаря для новой даты:', newDateKey);
                }
            } else {
                console.log('updateEventInCalendar: Дата не изменилась, обновляем содержимое на месте');
                
                // Обновляем содержимое события на месте
                const titleElement = eventElement.querySelector('.event-title');
                const timeElement = eventElement.querySelector('.event-time');
                
                if (titleElement) titleElement.textContent = eventData.title;
                if (timeElement) {
                    console.log('updateEventInCalendar: eventData.dateFrom =', eventData.dateFrom);
                    
                    // Форматируем время, избегая проблем с часовыми поясами
                    let timeString;
                    if (typeof eventData.dateFrom === 'string') {
                        // Если дата в формате "2025-08-04 12:00:00", извлекаем время напрямую
                        const timeMatch = eventData.dateFrom.match(/(\d{2}):(\d{2}):(\d{2})$/);
                        if (timeMatch) {
                            timeString = `${timeMatch[1]}:${timeMatch[2]}`;
                            console.log('updateEventInCalendar: Время извлечено из пробела:', timeString);
                        } else {
                            // Если дата в ISO формате (с T), извлекаем время
                            const isoTimeMatch = eventData.dateFrom.match(/T(\d{2}):(\d{2}):/);
                            if (isoTimeMatch) {
                                timeString = `${isoTimeMatch[1]}:${isoTimeMatch[2]}`;
                                console.log('updateEventInCalendar: Время извлечено из T:', timeString);
                            } else {
                                // Fallback на локальное время
                                timeString = new Date(eventData.dateFrom).toLocaleTimeString('ru-RU', { 
                                    hour: '2-digit', 
                                    minute: '2-digit',
                                    hour12: false
                                });
                                console.log('updateEventInCalendar: Время извлечено через toLocaleTimeString:', timeString);
                            }
                        }
                    } else {
                        // Fallback на локальное время
                        timeString = new Date(eventData.dateFrom).toLocaleTimeString('ru-RU', { 
                            hour: '2-digit', 
                            minute: '2-digit',
                            hour12: false
                        });
                        console.log('updateEventInCalendar: Время извлечено через toLocaleTimeString (fallback):', timeString);
                    }
                    timeElement.textContent = timeString;
                }
                
                // Обновляем цвет события
                if (eventData.eventColor) {
                    eventElement.style.borderLeft = `4px solid ${eventData.eventColor}`;
                    eventElement.style.backgroundColor = `${eventData.eventColor}15`;
                }
                
                // Анимация обновления
                eventElement.style.transform = 'scale(1.05)';
                eventElement.style.boxShadow = '0 0 20px rgba(52, 152, 219, 0.6)';
                
                setTimeout(() => {
                    eventElement.style.transition = 'all 0.3s ease';
                    eventElement.style.transform = 'scale(1)';
                    eventElement.style.boxShadow = 'none';
                }, 200);
            }
        } else {
            console.error('updateEventInCalendar: Событие не найдено в календаре, ID:', eventId);
        }
    }

    /**
     * Удаляет событие из календаря
     */
    function deleteEvent(eventId) {
        const eventElement = document.querySelector(`[data-event-id="${eventId}"]`);
        
        if (eventElement) {
            // Анимация удаления
            eventElement.style.transition = 'all 0.3s ease';
            eventElement.style.transform = 'scale(0.8)';
            eventElement.style.opacity = '0';
            
            setTimeout(() => {
                if (eventElement.parentNode) {
                    eventElement.parentNode.removeChild(eventElement);
                }
            }, 300);
        }
    }

    /**
     * Удаляет событие через AJAX
     */
    function deleteEventAjax(eventId) {
        if (!confirm('Вы уверены, что хотите удалить это событие?')) {
            return;
        }

        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'deleteEvent',
                eventId: eventId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Событие удалено успешно!', 'success');
                deleteEvent(eventId);
                closeEditEventModal();
            } else {
                showNotification('Ошибка: ' + (data.error || 'Неизвестная ошибка'), 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка при удалении события:', error);
            showNotification('Ошибка при удалении события', 'error');
        });
    }

    /**
     * Создает HTML элемент события
     */
    function createEventElement(event) {
        const eventElement = document.createElement('div');
        eventElement.className = 'calendar-event';
        eventElement.setAttribute('data-event-id', event.ID);
        
        // Применяем цвет события
        if (event.EVENT_COLOR) {
            eventElement.style.borderLeft = `4px solid ${event.EVENT_COLOR}`;
            eventElement.style.backgroundColor = `${event.EVENT_COLOR}15`;
        }
        
        // Форматируем время, избегая проблем с часовыми поясами
        console.log('createEventElement: event.DATE_FROM =', event.DATE_FROM);
        
        let timeString;
        if (typeof event.DATE_FROM === 'string') {
            // Если дата в формате "2025-08-04 12:00:00", извлекаем время напрямую
            const timeMatch = event.DATE_FROM.match(/(\d{2}):(\d{2}):(\d{2})$/);
            if (timeMatch) {
                timeString = `${timeMatch[1]}:${timeMatch[2]}`;
                console.log('createEventElement: Время извлечено из пробела:', timeString);
            } else {
                // Если дата в ISO формате (с T), извлекаем время
                const isoTimeMatch = event.DATE_FROM.match(/T(\d{2}):(\d{2}):/);
                if (isoTimeMatch) {
                    timeString = `${isoTimeMatch[1]}:${isoTimeMatch[2]}`;
                    console.log('createEventElement: Время извлечено из T:', timeString);
                } else {
                    // Fallback на локальное время
                    timeString = new Date(event.DATE_FROM).toLocaleTimeString('ru-RU', { 
                        hour: '2-digit', 
                        minute: '2-digit',
                        hour12: false
                    });
                    console.log('createEventElement: Время извлечено через toLocaleTimeString:', timeString);
                }
            }
        } else {
            // Fallback на локальное время
            timeString = new Date(event.DATE_FROM).toLocaleTimeString('ru-RU', { 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: false
            });
            console.log('createEventElement: Время извлечено через toLocaleTimeString (fallback):', timeString);
        }
        
        eventElement.innerHTML = `
            <div class="event-dot"></div>
            <span class="event-title">${event.TITLE}</span>
            <span class="event-time">${timeString}</span>
        `;
        
        // Добавляем обработчик клика
        eventElement.addEventListener('click', function(e) {
            e.stopPropagation();
            showEventDetails(event.ID);
        });
        
        return eventElement;
    }

    // Функции навигации по календарю
    function previousMonth() {
        const currentMonthElement = document.querySelector('.current-month');
        if (currentMonthElement) {
            const currentText = currentMonthElement.textContent;
            const currentDate = new Date();
            
            // Парсим текущий месяц и год
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                               'July', 'August', 'September', 'October', 'November', 'December'];
            
            let currentMonth = currentDate.getMonth();
            let currentYear = currentDate.getFullYear();
            
            // Пытаемся найти месяц в тексте
            for (let i = 0; i < monthNames.length; i++) {
                if (currentText.includes(monthNames[i])) {
                    currentMonth = i;
                    break;
                }
            }
            
            // Переходим к предыдущему месяцу
            if (currentMonth === 0) {
                currentMonth = 11;
                currentYear--;
            } else {
                currentMonth--;
            }
            
            // Обновляем URL и перезагружаем страницу
            const newDate = new Date(currentYear, currentMonth, 1);
            // Форматируем дату в локальном формате, избегая проблем с часовыми поясами
            const year = newDate.getFullYear();
            const month = String(newDate.getMonth() + 1).padStart(2, '0');
            const day = String(newDate.getDate()).padStart(2, '0');
            const newDateString = `${year}-${month}-${day}`;
            window.location.href = window.location.pathname + '?date=' + newDateString;
        }
    }

    function nextMonth() {
        const currentMonthElement = document.querySelector('.current-month');
        if (currentMonthElement) {
            const currentText = currentMonthElement.textContent;
            const currentDate = new Date();
            
            // Парсим текущий месяц и год
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                               'July', 'August', 'September', 'October', 'November', 'December'];
            
            let currentMonth = currentDate.getMonth();
            let currentYear = currentDate.getFullYear();
            
            // Пытаемся найти месяц в тексте
            for (let i = 0; i < monthNames.length; i++) {
                if (currentText.includes(monthNames[i])) {
                    currentMonth = i;
                    break;
                }
            }
            
            // Переходим к следующему месяцу
            if (currentMonth === 11) {
                currentMonth = 0;
                currentYear++;
            } else {
                currentMonth++;
            }
            
            // Обновляем URL и перезагружаем страницу
            const newDate = new Date(currentYear, currentMonth, 1);
            // Форматируем дату в локальном формате, избегая проблем с часовыми поясами
            const year = newDate.getFullYear();
            const month = String(newDate.getMonth() + 1).padStart(2, '0');
            const day = String(newDate.getDate()).padStart(2, '0');
            const newDateString = `${year}-${month}-${day}`;
            window.location.href = window.location.pathname + '?date=' + newDateString;
        }
    }

    function goToToday() {
        const today = new Date();
        // Форматируем дату в локальном формате, избегая проблем с часовыми поясами
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');
        const todayString = `${year}-${month}-${day}`;
        window.location.href = window.location.pathname + '?date=' + todayString;
    }

})();