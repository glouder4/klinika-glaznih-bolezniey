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
        const dateFromInput = document.getElementById('event-date-from');
        const dateToInput = document.getElementById('event-date-to');

        if (dateFromInput && dateToInput) {
            dateFromInput.addEventListener('change', function() {
                const dateFrom = new Date(this.value);
                if (dateFrom && !dateToInput.value) {
                    // Устанавливаем дату окончания на час позже
                    const dateTo = new Date(dateFrom.getTime() + 60 * 60 * 1000);
                    dateToInput.value = dateTo.toISOString().slice(0, 16);
                }
            });
        }
    }

    function initFormValidation() {
        const form = document.getElementById('add-event-form');
        if (form) {
            form.addEventListener('submit', validateAndSubmitForm);
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
            const dateFromInput = document.getElementById('event-date-from');
            if (dateFromInput) {
                dateFromInput.value = date + 'T09:00';
            }
            
            const dateToInput = document.getElementById('event-date-to');
            if (dateToInput) {
                dateToInput.value = date + 'T10:00';
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
        
        const title = document.getElementById('event-title');
        const dateFrom = document.getElementById('event-date-from');
        const dateTo = document.getElementById('event-date-to');

        let isValid = true;
        let errorMessage = '';

        // Проверка названия
        if (!title.value.trim()) {
            isValid = false;
            errorMessage += 'Название события обязательно для заполнения\n';
            highlightField(title, true);
        } else {
            highlightField(title, false);
        }

        // Проверка дат
        if (!dateFrom.value) {
            isValid = false;
            errorMessage += 'Дата начала обязательна для заполнения\n';
            highlightField(dateFrom, true);
        } else {
            highlightField(dateFrom, false);
        }

        if (!dateTo.value) {
            isValid = false;
            errorMessage += 'Дата окончания обязательна для заполнения\n';
            highlightField(dateTo, true);
        } else {
            highlightField(dateTo, false);
        }

        // Проверка логики дат
        if (dateFrom.value && dateTo.value) {
            const fromDate = new Date(dateFrom.value);
            const toDate = new Date(dateTo.value);

            if (fromDate >= toDate) {
                isValid = false;
                errorMessage += 'Дата окончания должна быть позже даты начала\n';
                highlightField(dateFrom, true);
                highlightField(dateTo, true);
            }
        }

        if (!isValid) {
            showNotification(errorMessage, 'error');
            return;
        }

        // Отправляем форму
        submitEventForm();
    }

    function submitEventForm() {
        const form = document.getElementById('add-event-form');
        const formData = new FormData(form);
        
        // Показываем индикатор загрузки
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Добавление...';
        submitBtn.disabled = true;
        
        // Конвертируем время в UTC с учетом часового пояса филиала
        const branchId = getBranchId();
        const dateFrom = formData.get('dateFrom');
        const dateTo = formData.get('dateTo');
        
        // Отправляем время в локальном формате, сервер сам конвертирует в UTC
        BX.ajax.runComponentAction('artmax:calendar', 'addEvent', {
            mode: 'class',
            data: {
                title: formData.get('title'),
                description: formData.get('description'),
                dateFrom: dateFrom,
                dateTo: dateTo,
                branchId: branchId,
                eventColor: getSelectedEventColor()
            }
        }).then(function(response) {
            if (response.data.success) {
                showNotification('Событие добавлено успешно!', 'success');
                closeEventForm();
                form.reset();
                
                // Динамически добавляем событие в календарь
                addEventToCalendar({
                    id: response.data.eventId,
                    title: formData.get('title'),
                    description: formData.get('description'),
                    dateFrom: formData.get('dateFrom'),
                    dateTo: formData.get('dateTo')
                });
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

    function highlightField(field, isError) {
        if (isError) {
            field.style.borderColor = '#e74c3c';
            field.style.boxShadow = '0 0 0 3px rgba(231,76,60,0.1)';
        } else {
            field.style.borderColor = '';
            field.style.boxShadow = '';
        }
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

    function getSelectedEventColor() {
        // Получаем выбранный цвет события
        const selectedColorInput = document.getElementById('selected-color');
        if (selectedColorInput) {
            return selectedColorInput.value;
        }
        
        // Если не найден, возвращаем цвет по умолчанию
        return '#3498db';
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