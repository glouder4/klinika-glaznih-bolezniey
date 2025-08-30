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
        const navTabs = document.querySelectorAll('.nav-tab');
        navTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                navTabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');

                // Здесь можно добавить логику переключения между видами
                const viewType = this.textContent.trim();
                console.log('Переключение на вид:', viewType);
            });
        });

        // Обработка кнопки "СОЗДАТЬ"
        const createBtn = document.querySelector('.btn-create');
        if (createBtn) {
            createBtn.addEventListener('click', function() {
                const today = new Date().toISOString().split('T')[0];
                openEventForm(today);
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
        
        BX.ajax.runComponentAction('artmax:calendar', 'addEvent', {
            mode: 'class',
            data: {
                title: formData.get('title'),
                description: formData.get('description'),
                dateFrom: startDateTime.toISOString(),
                dateTo: endDateTime.toISOString(),
                duration: durationValue,
                branchId: getBranchId()
            }
        }).then(function(response) {
            if (response.data.success) {
                showNotification('Событие добавлено успешно!', 'success');
                closeEventForm();
                form.reset();

                // Перезагружаем страницу для отображения нового события
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                showNotification('Ошибка: ' + response.data.error, 'error');
            }
        }).catch(function(response) {
            showNotification('Ошибка: ' + response.errors[0].message, 'error');
        }).finally(function() {
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

    // Экспорт функций для использования в других скриптах
    window.ArtmaxCalendar = {
        openEventForm: openEventForm,
        closeEventForm: closeEventForm,
        showNotification: showNotification,
        searchEvents: searchEvents,
        clearSearch: clearSearch
    };

})();