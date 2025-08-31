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
        });

        // Закрытие по Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEventForm();
            }
        });
    }

    function initDateAutoFill() {
        const dateInput = document.getElementById('event-date');
        const timeSelect = document.getElementById('event-time');
        const durationSelect = document.getElementById('event-duration');

        if (dateInput && timeSelect) {
            // Устанавливаем текущую дату по умолчанию
            const today = new Date().toISOString().split('T')[0];
            dateInput.value = today;
            
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
        
        // Создаем объект Date для начала события
        const startDateTime = new Date(dateValue + 'T' + timeValue);
        
        // Создаем объект Date для окончания события (добавляем длительность)
        const endDateTime = new Date(startDateTime.getTime() + parseInt(durationValue) * 60 * 1000);
        
        // Отправляем AJAX запрос для добавления события
        const postData = {
            action: 'addEvent',
            title: formData.get('title'),
            description: formData.get('description'),
            dateFrom: startDateTime.toISOString(),
            dateTo: endDateTime.toISOString(),
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

                    // Перезагружаем страницу для отображения нового события
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
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
        console.log('Показываем детали события:', eventId);
        // Здесь можно добавить модальное окно с деталями события
        alert('Детали события ID: ' + eventId);
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
            const today = new Date().toISOString().split('T')[0];
            const dateInput = document.getElementById('schedule-date');
            if (dateInput) {
                dateInput.value = today;
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
                
                // Перезагружаем страницу для отображения нового события
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
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
        openScheduleModal: openScheduleModal,
        closeScheduleModal: closeScheduleModal,
        showNotification: showNotification,
        searchEvents: searchEvents,
        clearSearch: clearSearch,
        toggleWeeklyDays: toggleWeeklyDays,
        toggleEndFields: toggleEndFields,
        selectPresetColor: selectPresetColor,
        selectCustomColor: selectCustomColor,
        initColorPicker: initColorPicker,
        saveSchedule: saveSchedule
    };

})();