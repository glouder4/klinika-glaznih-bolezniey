/**
 * Artmax Calendar Module - Calendar Grid JavaScript
 */

(function() {
    'use strict';

    // Универсальная функция для получения CSRF токена
    function getCSRFToken() {
        return BX.bitrix_sessid();
    }

    // Инициализация модуля
    document.addEventListener('DOMContentLoaded', function() {
        initCalendar();
        
        // Скрываем элементы управления для не-админов
        if (window.IS_ADMIN === false) {
            const adminOnlyElements = document.querySelectorAll('.admin-only');
            adminOnlyElements.forEach(el => {
                el.style.display = 'none';
            });
        }
    });

    function initCalendar() {
        // Инициализация глобальных переменных для календаря
        // Сначала пытаемся получить дату из URL параметра
        const urlParams = new URLSearchParams(window.location.search);
        const dateParam = urlParams.get('date');
        
        if (dateParam) {
            // Если есть параметр date в URL, используем его
            const urlDate = new Date(dateParam);
            if (!isNaN(urlDate.getTime())) {
                window.currentYear = urlDate.getFullYear();
                window.currentMonth = urlDate.getMonth() + 1;
                console.log('initCalendar: Используем дату из URL:', dateParam, 'year:', window.currentYear, 'month:', window.currentMonth);
            } else {
                // Если дата в URL некорректная, используем текущую дату
                const now = new Date();
                window.currentYear = now.getFullYear();
                window.currentMonth = now.getMonth() + 1;
                console.log('initCalendar: Некорректная дата в URL, используем текущую дату');
            }
        } else {
            // Если нет параметра date, используем текущую дату
            const now = new Date();
            window.currentYear = now.getFullYear();
            window.currentMonth = now.getMonth() + 1;
            console.log('initCalendar: Нет параметра date в URL, используем текущую дату');
        }
        
        // Обновляем глобальные переменные из URL (на случай, если они не были установлены правильно)
        updateGlobalDateFromURL();
        
        // Инициализация календарных ячеек
        initCalendarCells();

        // Инициализация навигации
        initNavigation();
        
        // Инициализация мультиселектора сотрудников
        initMultiselect();
        
        // Загрузка сотрудников для селекторов форм
        loadEmployeesForSelectors();
        
        // Инициализация обработчиков для модального окна настроек филиала
        initBranchModal();

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
        
        // Инициализация модального окна клиента
        initClientModal();
        
        // Обработка формы создания филиала больше не нужна,
        // так как форма теперь в отдельном компоненте branch.form
        
        // Инициализируем селектор месяца
        window.initMonthSelector();
        
        // Инициализация обработчиков SidePanel
        initSidePanelHandlers();
        
        // Инициализация обработчиков postMessage для SidePanel
        initPostMessageHandlers();
        
        // НЕ загружаем события при первой загрузке - они уже загружены сервером
        // refreshCalendarEvents() будет вызываться только при необходимости (изменения, обновления)
    }

    // Инициализация обработчиков SidePanel
    function initSidePanelHandlers() {
        if (typeof BX !== 'undefined' && BX.SidePanel) {
            // Обработчик события закрытия SidePanel
            BX.addCustomEvent('SidePanel.Slider:onClose', function(event) {
                console.log('SidePanel closed, refreshing calendar...');
                // Обновляем календарь после закрытия SidePanel
                setTimeout(() => {
                    refreshCalendarEvents();
                }, 100);
            });
            
            // Обработчик события успешного создания события
            BX.addCustomEvent('Calendar:EventCreated', function(event) {
                console.log('Event created, refreshing calendar...');
                refreshCalendarEvents();
            });
        }
    }

    // Инициализация обработчиков postMessage для SidePanel
    function initPostMessageHandlers() {
        window.addEventListener('message', function(event) {
            // Проверяем, что сообщение от нашего SidePanel
            if (event.data && typeof event.data === 'object') {
                switch (event.data.type) {
                    case 'calendar:eventCreated':
                        console.log('Event created via postMessage:', event.data);
                        // Обновляем календарь после создания события
                        setTimeout(() => {
                            refreshCalendarEvents();
                        }, 100);
                        break;
                    
                    case 'calendar:scheduleCreated':
                        console.log('Schedule created via postMessage:', event.data);
                        // Обновляем календарь после создания расписания
                        setTimeout(() => {
                            refreshCalendarEvents();
                        }, 100);
                        break;
                    
                    case 'calendar:branchCreated':
                        console.log('Branch created via postMessage:', event.data);
                        // Перезагружаем страницу для обновления переключателя филиалов
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                        break;
                    
                    case 'calendar:branchSettingsSaved':
                        console.log('Branch settings saved via postMessage:', event.data);
                        // Перезагружаем страницу для обновления названия филиала и настроек
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                        break;
                    
                    case 'calendar:contactSaved':
                        console.log('Contact saved via postMessage:', event.data);
                        // Обновляем календарь и боковую панель при необходимости
                        setTimeout(() => {
                            if (typeof refreshCalendarEvents === 'function') {
                                refreshCalendarEvents();
                            }
                            // Обновляем информацию о клиенте в боковой панели если она открыта
                            if (event.data && event.data.contactId && event.data.eventId) {
                                const currentEventId = getCurrentEventId();
                                if (currentEventId && String(currentEventId) === String(event.data.eventId)) {
                                    // Боковая панель открыта для этого события, обновляем данные
                                    if (typeof loadEventContact === 'function') {
                                        loadEventContact(event.data.contactId);
                                    }
                                }
                            }
                        }, 100);
                        break;
                    
                    case 'calendar:getCurrentEventId':
                        // Отправляем текущий eventId обратно
                        if (window.postMessage && event.source) {
                            const currentEventId = getCurrentEventId();
                            event.source.postMessage({
                                type: 'calendar:currentEventId',
                                eventId: currentEventId
                            }, event.origin);
                        }
                        break;
                    
                    case 'calendar:dealSaved':
                        console.log('Deal saved via postMessage:', event.data);
                        // Обновляем календарь
                        setTimeout(() => {
                            if (typeof refreshCalendarEvents === 'function') {
                                refreshCalendarEvents();
                            }
                            // Обновляем информацию о сделке в боковой панели если она открыта
                            if (event.data && event.data.dealId && event.data.eventId) {
                                console.log('calendar:dealSaved: Проверяем обновление deal-status');
                                // Проверяем, действительно ли боковая панель события открыта
                                const eventSidePanel = document.getElementById('eventSidePanel');
                                const isSidePanelOpen = eventSidePanel && eventSidePanel.classList.contains('open') && eventSidePanel.style.display !== 'none';
                                
                                console.log('calendar:dealSaved: eventSidePanel существует?', !!eventSidePanel);
                                console.log('calendar:dealSaved: isSidePanelOpen?', isSidePanelOpen);
                                
                                if (isSidePanelOpen) {
                                    const currentEventId = getCurrentEventId();
                                    console.log('calendar:dealSaved: currentEventId =', currentEventId, 'event.data.eventId =', event.data.eventId);
                                    
                                    if (currentEventId && String(currentEventId) === String(event.data.eventId)) {
                                        // Боковая панель открыта для этого события, просто обновляем данные о сделке
                                        // Используем данные, которые уже есть в postMessage
                                        console.log('calendar:dealSaved: Вызываем updateDealInfoInSidePanel');
                                        console.log('calendar:dealSaved: dealTitle =', event.data.dealTitle);
                                        
                                        // Проверяем наличие элемента перед обновлением
                                        const dealStatusElement = document.getElementById('deal-status');
                                        console.log('calendar:dealSaved: deal-status элемент найден?', !!dealStatusElement);
                                        
                                        if (typeof updateDealInfoInSidePanel === 'function') {
                                            updateDealInfoInSidePanel({
                                                title: event.data.dealTitle || 'Сделка #' + event.data.dealId
                                            });
                                        } else {
                                            console.error('calendar:dealSaved: функция updateDealInfoInSidePanel не найдена!');
                                            // Fallback - обновляем напрямую
                                            if (dealStatusElement) {
                                                dealStatusElement.textContent = event.data.dealTitle || 'Сделка #' + event.data.dealId;
                                                dealStatusElement.style.color = '#28a745';
                                                console.log('calendar:dealSaved: deal-status обновлен напрямую');
                                            }
                                        }
                                    } else {
                                        console.log('calendar:dealSaved: ID события не совпадают, пропускаем обновление');
                                    }
                                } else {
                                    console.log('calendar:dealSaved: Боковая панель не открыта, пропускаем обновление');
                                }
                            } else {
                                console.log('calendar:dealSaved: Недостаточно данных для обновления');
                            }
                        }, 100);
                        break;
                    
                    case 'calendar:eventUpdated':
                        console.log('Event updated via postMessage:', event.data);
                        // Обновляем календарь после обновления события
                        setTimeout(() => {
                            if (typeof refreshCalendarEvents === 'function') {
                                refreshCalendarEvents();
                            }
                        }, 100);
                        break;
                    
                    case 'calendar:eventDeleted':
                        console.log('Event deleted via postMessage:', event.data);
                        // Обновляем календарь после удаления события
                        setTimeout(() => {
                            if (typeof refreshCalendarEvents === 'function') {
                                refreshCalendarEvents();
                            }
                            // Закрываем боковую панель если она открыта для удаленного события
                            if (typeof closeEventSidePanel === 'function') {
                                closeEventSidePanel();
                            }
                        }, 100);
                        break;
                    
                    case 'calendar:employeeAssigned':
                        console.log('Employee assigned via postMessage:', event.data);
                        setTimeout(() => {
                            if (typeof refreshCalendarEvents === 'function') {
                                refreshCalendarEvents();
                            }
                            if (event.data && event.data.employeeId && event.data.eventId) {
                                const currentEventId = getCurrentEventId();
                                if (currentEventId && String(currentEventId) === String(event.data.eventId)) {
                                    // Обновляем карточку врача в боковой панели
                                    updateEmployeeCard(event.data.employeeId, event.data.employeeName);
                                }
                            }
                        }, 100);
                        break;
                    
                    case 'calendar:eventMoved':
                        console.log('Event moved via postMessage:', event.data);
                        setTimeout(() => {
                            if (typeof refreshCalendarEvents === 'function') {
                                refreshCalendarEvents();
                            }
                            // Закрываем боковую панель события, если она открыта для перенесенного события
                            if (event.data && event.data.eventId) {
                                const currentEventId = getCurrentEventId();
                                if (currentEventId && String(currentEventId) === String(event.data.eventId)) {
                                    if (typeof closeEventSidePanel === 'function') {
                                        closeEventSidePanel();
                                    }
                                }
                            }
                        }, 100);
                        break;
                        
                    case 'calendar:closePanel':
                        console.log('Close panel via postMessage');
                        // Закрываем SidePanel
                        if (typeof BX !== 'undefined' && BX.SidePanel) {
                            BX.SidePanel.Instance.close();
                        }
                        break;
                }
            }
        });
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

            // Обработчики для событий теперь не нужны - клик только по стрелочке

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
            
            // Убираем автоматическое закрытие бокового окна при клике вне его
            // Боковое окно теперь закрывается только по кнопке крестика
        });

        // Закрытие по Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEventForm();
                closeEditEventModal();
                closeEventSidePanel();
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
            
            const employeeSelect = document.getElementById('event-employee');
            if (employeeSelect) {
                employeeSelect.addEventListener('change', () => clearFieldError('employee-group'));
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
            
            const editEmployeeSelect = document.getElementById('edit-event-employee');
            if (editEmployeeSelect) {
                editEmployeeSelect.addEventListener('change', () => clearFieldError('edit-employee-group'));
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
        // Получаем ID текущего филиала
        const branchId = document.querySelector('.artmax-calendar').getAttribute('data-branch-id') || '1';
        
        // Формируем URL для SidePanel с компонентом event.form
        const url = `/local/components/artmax/event.form/page.php?BRANCH_ID=${branchId}&DATE=${date}&IFRAME=Y&IFRAME_TYPE=SIDE_SLIDER`;
        
        // Открываем SidePanel
        if (typeof BX !== 'undefined' && BX.SidePanel) {
            BX.SidePanel.Instance.open(url, {
                title: 'Создание события',
                width: 600,
                cacheable: false,
                events: {
                    onClose: function() {
                        // Обновляем календарь после закрытия SidePanel
                        refreshCalendarEvents();
                    }
                }
            });
        } else {
            // Fallback для случаев, когда SidePanel недоступен
            window.open(url, '_blank', 'width=600,height=800,scrollbars=yes,resizable=yes');
        }
    }

    function closeEventForm() {
        // Эта функция больше не используется, так как форма теперь открывается в SidePanel
        // Оставляем для совместимости с существующим кодом
        console.log('closeEventForm() called - no longer needed with SidePanel');
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
        const employee = document.getElementById('event-employee');

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

        // Проверка врача
        if (!employee.value) {
            isValid = false;
            showFieldError('employee-group', 'Выберите ответственного сотрудника.');
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
        const employee = document.getElementById('edit-event-employee');

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

        // Проверка врача
        if (!employee.value) {
            isValid = false;
            showFieldError('edit-employee-group', 'Выберите ответственного сотрудника.');
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
            eventColor: formData.get('event-color') || '#3498db',
            employee_id: formData.get('employee_id') || null
        };
        
        // Логируем данные, которые отправляем
        console.log('submitEventForm: Отправляем данные:', postData);
        console.log('submitEventForm: Цвет события:', postData.eventColor);
        
        // Добавляем CSRF токен
        const csrfToken = getCSRFToken();
        postData.sessid = csrfToken;
        
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
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
                        eventColor: formData.get('event-color') || '#3498db',
                        contactName: '',
                        contactPhone: ''
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
            showNotification('Ошибка: некорректные дата или время. Возможно время уже занято.', 'error');
            submitBtn.textContent = 'Сохранить';
            submitBtn.disabled = false;
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
        
        // Добавляем CSRF токен
        const csrfToken = getCSRFToken();
        postData.sessid = csrfToken;
        
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
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

                    // Получаем данные контакта из текущего события
                    const contactName = window.currentEvent?.contactName || '';
                    const contactPhone = window.currentEvent?.contactPhone || '';

                    // Обновляем событие в календаре
                    updateEventInCalendar({
                        id: eventId,
                        title: formData.get('title'),
                        description: formData.get('description'),
                        dateFrom: formatLocalDateTime(startDateTime),
                        dateTo: formatLocalDateTime(endDateTime),
                        eventColor: eventColor,
                        contactName: contactName,
                        contactPhone: contactPhone
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

    window.showEventSidePanel = function(eventId) {
        console.log('showEventSidePanel: Показываем боковое окно для события:', eventId);
        
        // Сохраняем ID текущего события
        window.currentEventId = eventId;
        
        // Находим элемент события
        const eventElement = document.querySelector(`[data-event-id="${eventId}"]`);
        if (!eventElement) {
            console.error('Элемент события не найден:', eventId);
            return;
        }
        
        // Получаем позицию события
        const eventRect = eventElement.getBoundingClientRect();
        const sidePanel = document.getElementById('eventSidePanel');
        
        if (!sidePanel) {
            console.error('Боковое окно не найдено');
            return;
        }
        
        // Показываем боковое окно сначала невидимым для вычисления размеров
        sidePanel.style.display = 'block';
        sidePanel.style.visibility = 'hidden';
        sidePanel.classList.add('open');
        
        // Вычисляем позицию бокового окна
        const panelWidth = 400;
        const panelHeight = window.innerHeight * 0.8; // 80% высоты экрана
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;
        
        let left = eventRect.right + 10; // Справа от события
        let top = Math.max(20, eventRect.top - 50); // Поднимаем выше события
        
        // Если не помещается справа, показываем слева
        if (left + panelWidth > viewportWidth) {
            left = eventRect.left - panelWidth - 10;
        }
        
        // Если не помещается снизу, корректируем по вертикали
        if (top + panelHeight > viewportHeight) {
            top = viewportHeight - panelHeight - 20;
        }
        
        // Если не помещается сверху, показываем от верха экрана
        if (top < 20) {
            top = 20;
        }
        
        // Устанавливаем позицию
        sidePanel.style.left = left + 'px';
        sidePanel.style.top = top + 'px';
        sidePanel.style.height = panelHeight + 'px';
        
        // Делаем видимым
        sidePanel.style.visibility = 'visible';
        
        // Показываем прелоадер
        showSidePanelPreloader();
        
        // Получаем данные события и заполняем боковое окно
        const csrfToken = getCSRFToken();
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
        .then(data => {
            if (data.success && data.event) {
                const event = data.event;
                
                // Обновляем заголовок бокового окна
                const titleElement = document.getElementById('sidePanelTitle');
                if (titleElement) {
                    titleElement.textContent = event.TITLE || 'Детали записи';
                }
                
                // Применяем цвет события к шапке
                const eventColor = event.EVENT_COLOR || '#3498db';
                const sidePanelHeader = document.querySelector('.side-panel-header');
                if (sidePanelHeader) {
                    sidePanelHeader.style.background = `linear-gradient(135deg, ${eventColor}, ${eventColor}dd)`;
                }
                
                // Скрываем/показываем кнопки управления в зависимости от прав пользователя
                const actionsPanel = document.querySelector('.side-panel-actions');
                if (actionsPanel && window.IS_ADMIN !== undefined) {
                    actionsPanel.style.display = window.IS_ADMIN ? 'flex' : 'none';
                }
                
                // Подсчитываем количество запросов, которые будут выполнены
                let loadingCount = 2; // Подтверждение и визит всегда загружаются
                
                // Загружаем данные контакта, если есть CONTACT_ENTITY_ID
                console.log('showEventSidePanel: CONTACT_ENTITY_ID =', event.CONTACT_ENTITY_ID);
                if (event.CONTACT_ENTITY_ID) {
                    loadingCount++; // Увеличиваем счетчик только если будет запрос
                    console.log('showEventSidePanel: Загружаем контакт с ID:', event.CONTACT_ENTITY_ID);
                    loadEventContact(event.CONTACT_ENTITY_ID);
                } else {
                    console.log('showEventSidePanel: Нет CONTACT_ENTITY_ID, сбрасываем клиента');
                    // Сбрасываем информацию о клиенте, если контакта нет
                    resetClientInfoInSidePanel();
                }
                
                // Загружаем данные сделки, если есть DEAL_ENTITY_ID
                console.log('showEventSidePanel: DEAL_ENTITY_ID =', event.DEAL_ENTITY_ID);
                if (event.DEAL_ENTITY_ID) {
                    loadingCount++; // Увеличиваем счетчик только если будет запрос
                    console.log('showEventSidePanel: Загружаем сделку с ID:', event.DEAL_ENTITY_ID);
                    loadEventDeal(event.DEAL_ENTITY_ID);
                } else {
                    console.log('showEventSidePanel: Нет DEAL_ENTITY_ID, сбрасываем сделку');
                    // Сбрасываем информацию о сделке, если сделки нет
                    resetDealInfoInSidePanel();
                }
                
                // Загружаем данные врача, если есть EMPLOYEE_ID
                console.log('showEventSidePanel: EMPLOYEE_ID =', event.EMPLOYEE_ID);
                if (event.EMPLOYEE_ID) {
                    loadingCount++; // Увеличиваем счетчик только если будет запрос
                    console.log('showEventSidePanel: Загружаем врача с ID:', event.EMPLOYEE_ID);
                    loadEventEmployee(event.EMPLOYEE_ID);
                } else {
                    console.log('showEventSidePanel: Нет EMPLOYEE_ID, сбрасываем врача');
                    // Сбрасываем информацию о враче, если врача нет
                    resetEmployeeInfoInSidePanel();
                }
                
                // Инициализируем счетчик загрузки
                window.sidePanelLoadingCount = loadingCount;
                window.sidePanelLoadingComplete = 0;
                
                console.log('showEventSidePanel: Ожидается загрузка', loadingCount, 'компонентов');
                
                // Загружаем и отображаем статус подтверждения
                loadEventConfirmationStatus(eventId);
                
                // Загружаем и отображаем статус визита
                loadEventVisitStatus(eventId);
                
                // Отображаем заметку (данные уже есть в event)
                updateNoteDisplay(event.NOTE || '');
                
                // Обновляем кнопку в зависимости от статуса события
                console.log('showEventSidePanel: Статус события:', event.STATUS);
                updateCancelButtonByStatus(event.STATUS);
            } else {
                showNotification('Ошибка при загрузке события', 'error');
                // Сбрасываем счетчик загрузки при ошибке основного запроса
                window.sidePanelLoadingCount = 0;
                window.sidePanelLoadingComplete = 0;
                hideSidePanelPreloader();
            }
        })
        .catch(error => {
            console.error('Ошибка при загрузке события:', error);
            showNotification('Ошибка при загрузке события', 'error');
            // Сбрасываем счетчик загрузки при ошибке основного запроса
            window.sidePanelLoadingCount = 0;
            window.sidePanelLoadingComplete = 0;
            hideSidePanelPreloader();
        });
    }

    function updateSidePanelPosition(eventElement) {
        if (!eventElement) return;
        
        const sidePanel = document.getElementById('eventSidePanel');
        if (!sidePanel || !sidePanel.classList.contains('open')) return;
        
        // Получаем позицию события
        const eventRect = eventElement.getBoundingClientRect();
        
        // Вычисляем позицию бокового окна
        const panelWidth = 400;
        const panelHeight = window.innerHeight * 0.8; // 80% высоты экрана
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;
        
        let left = eventRect.right + 10; // Справа от события
        let top = Math.max(20, eventRect.top - 50); // Поднимаем выше события
        
        // Если не помещается справа, показываем слева
        if (left + panelWidth > viewportWidth) {
            left = eventRect.left - panelWidth - 10;
        }
        
        // Если не помещается снизу, корректируем по вертикали
        if (top + panelHeight > viewportHeight) {
            top = viewportHeight - panelHeight - 20;
        }
        
        // Если не помещается сверху, показываем от верха экрана
        if (top < 20) {
            top = 20;
        }
        
        // Применяем позицию
        sidePanel.style.left = left + 'px';
        sidePanel.style.top = top + 'px';
        sidePanel.style.height = panelHeight + 'px';
    }

    function closeEventSidePanel() {
        const sidePanel = document.getElementById('eventSidePanel');
        if (sidePanel) {
            // Скрываем прелоадер при закрытии
            hideSidePanelPreloader();
            
            // Сбрасываем счетчики загрузки
            window.sidePanelLoadingCount = 0;
            window.sidePanelLoadingComplete = 0;
            
            sidePanel.classList.remove('open');
            setTimeout(() => {
                sidePanel.style.display = 'none';
                sidePanel.style.visibility = 'hidden';
                sidePanel.style.left = '';
                sidePanel.style.top = '';
                sidePanel.style.height = '';
                document.body.style.overflow = 'auto';
                
                // Сбрасываем цвет шапки
                const sidePanelHeader = document.querySelector('.side-panel-header');
                if (sidePanelHeader) {
                    sidePanelHeader.style.background = '';
                }
                
                // Очищаем ID текущего события
                window.currentEventId = null;
            }, 300);
        }
    }

    function openEditEventModalFromSidePanel() {
        if (window.currentEventId) {
            closeEventSidePanel();
            openEditEventModal(window.currentEventId);
        }
    }

    function deleteEventFromSidePanel() {
        if (window.currentEventId) {
            if (confirm('Вы уверены, что хотите удалить это событие?')) {
                deleteEventAjax(window.currentEventId);
                closeEventSidePanel();
            }
        }
    }

    function openClientModal() {
        // Получаем ID текущего события (если есть)
        let eventId = getCurrentEventId();
        
        const url = `/local/components/artmax/client.form/page.php?IFRAME=Y&IFRAME_TYPE=SIDE_SLIDER${eventId ? '&EVENT_ID=' + eventId : ''}`;
        
        if (typeof BX !== 'undefined' && BX.SidePanel) {
            BX.SidePanel.Instance.open(url, {
                title: 'Добавить или выбрать клиента',
                width: 600,
                cacheable: false,
                events: {
                    onClose: function() {
                        // Обновляем календарь при необходимости
                        if (typeof refreshCalendarEvents === 'function') {
                            refreshCalendarEvents();
                        }
                    }
                }
            });
        } else {
            window.open(url, '_blank', 'width=600,height=800,scrollbars=yes,resizable=yes');
        }
    }

    window.closeClientModal = function() {
        const modal = document.getElementById('clientModal');
        if (modal) {
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
                
                // Сбрасываем состояние модального окна
                resetClientModal();
            }, 300);
        }
    };
    
    // Функция сброса состояния модального окна
    window.resetClientModal = function() {
        // Очищаем поля
        const contactInput = document.getElementById('contact-input');
        const phoneInput = document.getElementById('phone-input');
        const emailInput = document.getElementById('email-input');
        const companyInput = document.getElementById('company-input');
        const contactIdInput = document.getElementById('contact-id');
        
        if (contactInput) contactInput.value = '';
        if (phoneInput) phoneInput.value = '';
        if (emailInput) emailInput.value = '';
        if (companyInput) companyInput.value = '';
        if (contactIdInput) contactIdInput.value = '';
        
        // Скрываем дополнительные поля и кнопки
        hideContactDetailsFields();
        
        // Скрываем выпадающий список
        hideContactDropdown();
        
        // Скрываем форму создания контакта и возвращаемся к поиску
        hideCreateContactForm();
    };

    function openEditEventModal(eventId) {
        // Открываем форму редактирования в SidePanel
        const url = `/local/components/artmax/event.edit/page.php?EVENT_ID=${eventId}&IFRAME=Y&IFRAME_TYPE=SIDE_SLIDER`;
        
        if (typeof BX !== 'undefined' && BX.SidePanel) {
            BX.SidePanel.Instance.open(url, {
                title: 'Редактировать событие',
                width: 600,
                cacheable: false,
                events: {
                    onClose: function() {
                        // Обновляем календарь при закрытии
                        if (typeof refreshCalendarEvents === 'function') {
                            refreshCalendarEvents();
                        }
                    }
                }
            });
        } else {
            window.open(url, '_blank', 'width=600,height=800,scrollbars=yes,resizable=yes');
        }
    }

    function getBranchId() {
        // Получаем ID филиала из данных страницы или возвращаем 0
        const branchElement = document.querySelector('[data-branch-id]');
        return branchElement ? branchElement.getAttribute('data-branch-id') : 0;
    }

    // Функции для работы с формой филиала в SidePanel
    function openAddBranchModal() {
        // Формируем URL для SidePanel с компонентом branch.form
        const url = `/local/components/artmax/branch.form/page.php?IFRAME=Y&IFRAME_TYPE=SIDE_SLIDER`;
        
        // Открываем SidePanel
        if (typeof BX !== 'undefined' && BX.SidePanel) {
            BX.SidePanel.Instance.open(url, {
                title: 'Создание филиала',
                width: 600,
                cacheable: false,
                events: {
                    onClose: function() {
                        // Обновляем страницу для обновления переключателя филиалов
                        // Bitrix автоматически обновит переключатель через postMessage
                        if (window.location) {
                            // Можно сделать мягкое обновление через AJAX
                        }
                    }
                }
            });
        } else {
            // Fallback для случаев, когда SidePanel недоступен
            window.open(url, '_blank', 'width=600,height=800,scrollbars=yes,resizable=yes');
        }
    }
    
    // Экспортируем функцию в глобальную область видимости для использования в onclick и других местах
    window.openAddBranchModal = openAddBranchModal;

    // Функция closeAddBranchModal больше не нужна, так как форма теперь в SidePanel
    // Оставлена для обратной совместимости
    function closeAddBranchModal() {
        // Если SidePanel открыт, закрываем его
        if (typeof BX !== 'undefined' && BX.SidePanel && BX.SidePanel.Instance) {
            BX.SidePanel.Instance.close();
        }
    }


    // Функции для работы с формой расписания в SidePanel
    function openScheduleModal() {
        // Получаем ID текущего филиала
        const branchId = document.querySelector('.artmax-calendar').getAttribute('data-branch-id') || '1';
        
        // Формируем URL для SidePanel с компонентом schedule.form
        const url = `/local/components/artmax/schedule.form/page.php?BRANCH_ID=${branchId}&DATE=${new Date().toISOString().split('T')[0]}&IFRAME=Y&IFRAME_TYPE=SIDE_SLIDER`;
        
        // Открываем SidePanel
        if (typeof BX !== 'undefined' && BX.SidePanel) {
            BX.SidePanel.Instance.open(url, {
                title: 'Создание расписания',
                width: 600,
                cacheable: false,
                events: {
                    onClose: function() {
                        // Обновляем календарь после закрытия SidePanel
                        if (typeof refreshCalendarEvents === 'function') {
                            refreshCalendarEvents();
                        }
                    }
                }
            });
        } else {
            // Fallback для случаев, когда SidePanel недоступен
            window.open(url, '_blank', 'width=600,height=800,scrollbars=yes,resizable=yes');
        }
    }

    // Функция closeScheduleModal больше не нужна, так как форма теперь в SidePanel
    // Оставлена для обратной совместимости
    function closeScheduleModal() {
        // Если SidePanel открыт, закрываем его
        if (typeof BX !== 'undefined' && BX.SidePanel && BX.SidePanel.Instance) {
            BX.SidePanel.Instance.close();
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
    window.toggleEndFields = function() {
        const repeatEnd = document.querySelector('input[name="repeat-end"]:checked');

        if (!repeatEnd) {
            return;
        }

        const repeatEndValue = repeatEnd.value;
        const repeatCountInput = document.querySelector('.repeat-count-input');
        const repeatEndDateInput = document.querySelector('.repeat-end-date-input');
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
    }
    
    // Функция для выбора предустановленного цвета
    function selectPresetColor(color) {
        console.log('selectPresetColor вызвана с цветом:', color);
        document.getElementById('selected-color').value = color;
        document.getElementById('custom-color-input').value = color;
        
        // Обновляем активный класс для пресетов
        document.querySelectorAll('.color-preset').forEach(preset => {
            preset.classList.remove('active');
        });
        event.target.classList.add('active');
        
        console.log('selected-color установлен в:', document.getElementById('selected-color').value);
    }
    
    // Функция для выбора кастомного цвета
    function selectCustomColor(color) {
        console.log('selectCustomColor вызвана с цветом:', color);
        document.getElementById('selected-color').value = color;
        
        // Убираем активный класс со всех пресетов
        document.querySelectorAll('.color-preset').forEach(preset => {
            preset.classList.remove('active');
        });
        
        console.log('selected-color установлен в:', document.getElementById('selected-color').value);
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
        console.log('initColorPicker вызвана');
        // Устанавливаем первый цвет как активный по умолчанию
        const firstPreset = document.querySelector('.color-preset');
        if (firstPreset) {
            firstPreset.classList.add('active');
            console.log('Первый пресет установлен как активный:', firstPreset.dataset.color);
        }
        
        // Устанавливаем значение по умолчанию для скрытого поля
        const selectedColorInput = document.getElementById('selected-color');
        if (selectedColorInput) {
            selectedColorInput.value = '#3498db';
            console.log('selected-color установлен в значение по умолчанию:', selectedColorInput.value);
        } else {
            console.error('Элемент selected-color не найден!');
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
            employee_id: scheduleData.employee_id,
            branch_id: scheduleData.branch_id,
            repeat: scheduleData.repeat,
            frequency: scheduleData.frequency || null,
            weekdays: scheduleData.weekdays || [],
            repeatEnd: scheduleData.repeatEnd || 'after',
            repeatCount: scheduleData.repeatCount || null,
            repeatEndDate: scheduleData.repeatEndDate || null,
            includeEndDate: scheduleData.includeEndDate || false,
            excludeWeekends: scheduleData.excludeWeekends || false,
            excludeHolidays: scheduleData.excludeHolidays || false,
            eventColor: scheduleData.eventColor || '#3498db'
        };
        
        console.log('Отправляемые данные:', postData);
        console.log('Цвет события в postData:', postData.eventColor);
        
        // Отправляем AJAX запрос
        const csrfToken = getCSRFToken();
        postData.sessid = csrfToken;
        
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
            console.log('Ответ сервера:', data);
            if (data.success) {
                const eventsCount = data.eventsCreated || 1;
                const message = eventsCount > 1 
                    ? `Расписание успешно создано! Создано ${eventsCount} событий.`
                    : 'Расписание успешно создано!';
                showNotification(message, 'success');
                closeScheduleModal();
                
                // Динамически добавляем события расписания в календарь с анимацией
                if (data.events && Array.isArray(data.events)) {
                    console.log('Добавляем события расписания в календарь:', data.events);
                    data.events.forEach(event => {
                        console.log('Добавляем событие расписания:', event);
                        console.log('Цвет события:', event.EVENT_COLOR);
                        
                        // Конвертируем данные события в формат, понятный для addEventToCalendar
                        const eventData = {
                            id: event.ID,
                            title: event.TITLE,
                            description: event.DESCRIPTION,
                            dateFrom: event.DATE_FROM,
                            dateTo: event.DATE_TO,
                            eventColor: event.EVENT_COLOR || scheduleData.eventColor || '#3498db',
                            contactEntityId: event.CONTACT_ENTITY_ID,
                            dealEntityId: event.DEAL_ENTITY_ID,
                            contactName: event.CONTACT_NAME || '',
                            contactPhone: event.CONTACT_PHONE || ''
                        };
                        
                        addEventToCalendar(eventData);
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
                
                // Валидация формы расписания
                const title = document.getElementById('schedule-title');
                const date = document.getElementById('schedule-date');
                const time = document.getElementById('schedule-time');
                const employee = document.getElementById('schedule-employee');
                
                let isValid = true;
                
                // Проверка названия
                if (!title.value.trim()) {
                    isValid = false;
                    showFieldError('schedule-title', 'Заполните название расписания.');
                }
                
                // Проверка даты
                if (!date.value) {
                    isValid = false;
                    showFieldError('schedule-date', 'Выберите дату.');
                }
                
                // Проверка времени
                if (!time.value) {
                    isValid = false;
                    showFieldError('schedule-time', 'Выберите время.');
                }
                
                // Проверка врача
                if (!employee.value) {
                    isValid = false;
                    showFieldError('schedule-employee', 'Выберите ответственного сотрудника.');
                }
                
                // Проверка выбора окончания повторения (обязательно)
                const repeatEnd = document.querySelector('input[name="repeat-end"]:checked');
                if (!repeatEnd) {
                    isValid = false;
                    showFieldError('repeat-end-group', 'Выберите способ окончания повторения.');
                } else {
                    // Дополнительные проверки в зависимости от выбранного типа окончания
                    if (repeatEnd.value === 'after') {
                        const repeatCount = document.querySelector('input[name="repeat-count"]');
                        if (!repeatCount.value || parseInt(repeatCount.value) < 1) {
                            isValid = false;
                            showFieldError('repeat-count', 'Укажите количество повторений (минимум 1).');
                        }
                    } else if (repeatEnd.value === 'date') {
                        const repeatEndDate = document.querySelector('input[name="repeat-end-date"]');
                        if (!repeatEndDate.value) {
                            isValid = false;
                            showFieldError('repeat-end-date', 'Выберите дату окончания повторения.');
                        }
                    }
                }
                
                if (!isValid) {
                    return;
                }
                
                const formData = new FormData(this);
                // Проверяем значение скрытого поля перед отправкой
                const selectedColorInput = document.getElementById('schedule-selected-color');
                console.log('Значение schedule-selected-color перед отправкой:', selectedColorInput ? selectedColorInput.value : 'ЭЛЕМЕНТ НЕ НАЙДЕН');
                
                // Получаем цвет напрямую из элемента, так как FormData может не работать с скрытыми полями
                const eventColor = selectedColorInput ? selectedColorInput.value : '#3498db';
                
                const scheduleData = {
                    title: formData.get('title'),
                    date: formData.get('date'),
                    time: formData.get('time'),
                    employee_id: formData.get('employee_id'),
                    branch_id: formData.get('branch_id'),
                    repeat: formData.get('repeat') === 'on',
                    frequency: formData.get('frequency'),
                    weekdays: formData.getAll('weekdays[]'),
                    repeatEnd: formData.get('repeat-end'),
                    repeatCount: formData.get('repeat-count'),
                    repeatEndDate: formData.get('repeat-end-date'),
                    includeEndDate: formData.get('include-end-date') === 'on',
                    excludeWeekends: formData.get('exclude_weekends') === 'on' || false,
                    excludeHolidays: formData.get('exclude_holidays') === 'on' || false,
                    eventColor: eventColor
                };

                console.log('Данные расписания:', scheduleData);
                console.log('Цвет события из формы:', scheduleData.eventColor);
                
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

        // Инициализация полей окончания повторения
        window.toggleEndFields();
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
        toggleEndFields: window.toggleEndFields,
        selectPresetColor: selectPresetColor,
        selectCustomColor: selectCustomColor,
        selectEditPresetColor: selectEditPresetColor,
        selectEditCustomColor: selectEditCustomColor,
        initColorPicker: initColorPicker,
        saveSchedule: saveSchedule,
        addEventToCalendar: addEventToCalendar,
        updateEventInCalendar: updateEventInCalendar,
        deleteEvent: deleteEvent,
        deleteEventAjax: deleteEventAjax,
        showEventSidePanel: showEventSidePanel,
        closeEventSidePanel: closeEventSidePanel,
        openEditEventModalFromSidePanel: openEditEventModalFromSidePanel,
        deleteEventFromSidePanel: deleteEventFromSidePanel
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

    // Добавляем обработчики для обновления позиции бокового окна
    window.addEventListener('scroll', function() {
        if (window.currentEventId) {
            const sidePanel = document.getElementById('eventSidePanel');
            if (sidePanel && sidePanel.classList.contains('open')) {
                // Обновляем позицию при прокрутке
                const eventElement = document.querySelector(`[data-event-id="${window.currentEventId}"]`);
                if (eventElement) {
                    const eventRect = eventElement.getBoundingClientRect();
                    const panelWidth = 400;
                    const panelHeight = window.innerHeight * 0.8;
                    const viewportWidth = window.innerWidth;
                    const viewportHeight = window.innerHeight;
                    
                    let left = eventRect.right + 10;
                    let top = Math.max(20, eventRect.top - 50); // Поднимаем выше события
                    
                    if (left + panelWidth > viewportWidth) {
                        left = eventRect.left - panelWidth - 10;
                    }
                    
                    if (top + panelHeight > viewportHeight) {
                        top = viewportHeight - panelHeight - 20;
                    }
                    
                    if (top < 20) {
                        top = 20;
                    }
                    
                    sidePanel.style.left = left + 'px';
                    sidePanel.style.top = top + 'px';
                }
            }
        }
    });

    window.addEventListener('resize', function() {
        if (window.currentEventId) {
            const sidePanel = document.getElementById('eventSidePanel');
            if (sidePanel && sidePanel.classList.contains('open')) {
                // Обновляем позицию бокового окна при изменении размера окна
                // Боковое окно больше не закрывается автоматически
                const eventElement = document.querySelector(`[data-event-id="${window.currentEventId}"]`);
                if (eventElement) {
                    updateSidePanelPosition(eventElement);
                }
            }
        }
    });

    // Обработчик клика вне модального окна выбора клиента
    document.addEventListener('click', function(e) {
        const clientModal = document.getElementById('clientModal');
        if (clientModal && clientModal.classList.contains('show')) {
            if (e.target === clientModal) {
                closeClientModal();
            }
        }
        
        const dealModal = document.getElementById('dealModal');
        if (dealModal && dealModal.classList.contains('show')) {
            if (e.target === dealModal) {
                closeDealModal();
            }
        }
    });

    // Обработчик нажатия Escape для закрытия модального окна
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const clientModal = document.getElementById('clientModal');
            if (clientModal && clientModal.classList.contains('show')) {
                closeClientModal();
            }
            
            const dealModal = document.getElementById('dealModal');
            if (dealModal && dealModal.classList.contains('show')) {
                closeDealModal();
            }
        }
    });

    // Обработчик клика для кнопки добавления контакта
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('add-contact-btn') || e.target.closest('.add-contact-btn')) {
            e.preventDefault();
            e.stopPropagation();
            openClientModal();
        }
    });

    // Инициализация обработчиков для модального окна клиента
    function initClientModal() {
        const contactInput = document.getElementById('contact-input');
        const companyInput = document.getElementById('company-input');
        const contactDropdown = document.getElementById('contact-search-dropdown');
        
        if (contactInput) {
            let searchTimeout;
            
                
            contactInput.addEventListener('input', function() {
                const query = this.value.trim();
                
                // Очищаем предыдущий таймер
                clearTimeout(searchTimeout);
                
                if (query.length > 0) {
                    updateSearchText(query);
                    showContactDropdown();
                    
                    // Запускаем поиск с задержкой 300мс
                    if (query.length >= 2) {
                        searchTimeout = setTimeout(() => {
                            searchContactsInBitrix24(query);
                        }, 300);
                    }
                } else {
                    hideContactDropdown();
                }
            });
            
            // Обработчик фокуса
            contactInput.addEventListener('focus', function() {
                const query = this.value.trim();
                if (query.length > 0) {
                    updateSearchText(query);
                    showContactDropdown();
                }
            });
            
            // Обработчик клика на предложение поиска
            const searchSuggestion = contactDropdown?.querySelector('.search-suggestion');
            if (searchSuggestion) {
                searchSuggestion.addEventListener('click', function() {
                    const query = contactInput.value.trim();
                    if (query) {
                        // Здесь будет логика поиска по введенному тексту
                        console.log('Поиск по запросу:', query);
                        hideContactDropdown();
                    }
                });
            }
            
            // Обработчик клика на кнопку создания нового контакта
            const createBtn = contactDropdown?.querySelector('.create-new-contact-btn');
            if (createBtn) {
                createBtn.addEventListener('click', function() {
                    const query = contactInput.value.trim();
                    console.log('Создание нового контакта:', query);
                    hideContactDropdown();
                });
            }
        }
        
        if (companyInput) {
            let searchTimeout;
            companyInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const query = this.value.trim();
                
                if (query.length >= 2) {
                    searchTimeout = setTimeout(() => {
                        searchClients(query, 'company');
                    }, 300);
                } else {
                    clearSearchResults();
                }
            });
        }
        
        // Обработчик клика вне выпадающего окошка
        document.addEventListener('click', function(e) {
            if (!contactInput?.contains(e.target) && !contactDropdown?.contains(e.target)) {
                hideContactDropdown();
            }
        });

        // Обработчик клика вне выпадающего меню подтверждения
        document.addEventListener('click', function(e) {
            const confirmationDropdown = document.getElementById('confirmation-dropdown');
            const confirmationBtn = document.getElementById('confirmation-select-btn');
            
            if (confirmationDropdown && confirmationBtn) {
                if (!confirmationDropdown.contains(e.target) && !confirmationBtn.contains(e.target)) {
                    confirmationDropdown.classList.remove('show');
                    // Удаляем класс dropdown-open с родительского action-card
                    const actionCard = confirmationDropdown.closest('.action-card');
                    if (actionCard) {
                        actionCard.classList.remove('dropdown-open');
                    }
                }
            }
        });
    }
    
    // Функция показа выпадающего окошка
    function showContactDropdown() {
        const dropdown = document.getElementById('contact-search-dropdown');
        if (dropdown) {
            dropdown.style.display = 'block';
        }
    }
    
    // Функция скрытия выпадающего окошка
    function hideContactDropdown() {
        const dropdown = document.getElementById('contact-search-dropdown');
        if (dropdown) {
            dropdown.style.display = 'none';
        }
    }
    
    // Функция обновления текста поиска
    function updateSearchText(query) {
        const searchTextElement = document.querySelector('.search-text');
        if (searchTextElement) {
            searchTextElement.textContent = `«${query}»`;
        }
    }
    
    // Функция поиска контактов в Bitrix 24
    function searchContactsInBitrix24(query) {
        console.log('Поиск контактов в Bitrix 24:', query);
        
        // Показываем индикатор загрузки
        showSearchLoading();
        
        // Используем только стандартный сервис поиска
        searchContactsViaStandardService(query);
    }
    
    // Функция поиска сделок в Bitrix 24
    function searchDealsInBitrix24(query) {
        console.log('Поиск сделок в Bitrix 24:', query);
        
        // Показываем индикатор загрузки
        showDealSearchLoading();
        
        // Используем только стандартный сервис поиска
        searchDealsViaStandardService(query);
    }
    

    

    
    // Поиск контактов через штатный Bitrix UI Entity Selector
    function searchContactsViaStandardService(query) {
        console.log('Используем штатный Bitrix UI Entity Selector для поиска контактов');
        
        // Получаем CSRF токен
        const csrfToken = getCSRFToken();
        
        // Используем штатный Bitrix UI Entity Selector
        fetch('/bitrix/services/main/ajax.php?context=BOOKING&action=ui.entityselector.doSearch', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
            },
            body: JSON.stringify({
                dialog: {
                    id: "ui-selector-contact-search",
                    context: "BOOKING",
                    entities: [
                        {
                            id: "contact",
                            options: {},
                            searchable: true,
                            dynamicLoad: true,
                            dynamicSearch: true,
                            filters: [],
                            substituteEntityId: null
                        }
                    ],
                    preselectedItems: [],
                    recentItemsLimit: null,
                    clearUnavailableItems: false
                },
                searchQuery: {
                    queryWords: [query],
                    query: query,
                    dynamicSearchEntities: []
                }
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data && data.status === 'success' && data.data) {
                const processedContacts = processBitrixEntitySelectorContacts(data.data);
                updateSearchResults(processedContacts);
            } else if (data && data.status === 'error') {
                console.error('Ошибка поиска контактов:', data.message);
                showSearchError(data.message || 'Ошибка поиска');
            } else {
                updateSearchResults([]);
            }
        })
        .catch(error => {
            console.error('Ошибка AJAX запроса:', error);
            showSearchError('Ошибка соединения с сервером');
        });
    }
    
    // Поиск сделок через штатный Bitrix UI Entity Selector
    function searchDealsViaStandardService(query) {
        console.log('Используем штатный Bitrix UI Entity Selector для поиска сделок');
        
        // Получаем CSRF токен
        const csrfToken = getCSRFToken();
        
        // Используем штатный Bitrix UI Entity Selector
        fetch('/bitrix/services/main/ajax.php?context=BOOKING&action=ui.entityselector.doSearch', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
            },
            body: JSON.stringify({
                dialog: {
                    id: "ui-selector-deal-search",
                    context: "BOOKING",
                    entities: [
                        {
                            id: "deal",
                            options: {},
                            searchable: true,
                            dynamicLoad: true,
                            dynamicSearch: true,
                            filters: [],
                            substituteEntityId: null
                        }
                    ],
                    preselectedItems: [],
                    recentItemsLimit: null,
                    clearUnavailableItems: false
                },
                searchQuery: {
                    queryWords: [query],
                    query: query,
                    dynamicSearchEntities: []
                }
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data && data.status === 'success' && data.data) {
                const processedDeals = processBitrixEntitySelectorDeals(data.data);
                updateDealSearchResults(processedDeals);
            } else if (data && data.status === 'error') {
                console.error('Ошибка поиска сделок:', data.message);
                showDealSearchError(data.message || 'Ошибка поиска');
            } else {
                updateDealSearchResults([]);
            }
        })
        .catch(error => {
            console.error('Ошибка AJAX запроса:', error);
            showDealSearchError('Ошибка соединения с сервером');
        });
    }
    
    // Обработка результатов поиска контактов от Bitrix UI Entity Selector
    function processBitrixEntitySelectorContacts(data) {
        console.log('Обрабатываем данные контактов от Bitrix UI Entity Selector:', data);
        
        if (!data || !data.dialog || !data.dialog.items || !Array.isArray(data.dialog.items)) {
            return [];
        }
        
        return data.dialog.items.map(item => {
            console.log('Обрабатываем контакт:', item);
            
            // Извлекаем телефоны и email из multiFields
            let phones = [];
            let emails = [];
            
            if (item.customData && item.customData.entityInfo && item.customData.entityInfo.advancedInfo && item.customData.entityInfo.advancedInfo.multiFields) {
                const multiFields = item.customData.entityInfo.advancedInfo.multiFields;
                
                multiFields.forEach(field => {
                    if (field.TYPE_ID === 'PHONE' && field.VALUE) {
                        phones.push(field.VALUE);
                    } else if (field.TYPE_ID === 'EMAIL' && field.VALUE) {
                        emails.push(field.VALUE);
                    }
                });
            }
            
            const processedContact = {
                id: item.id,
                name: item.title || 'Контакт #' + item.id,
                firstName: '',
                lastName: '',
                secondName: '',
                phone: phones.join(', '),
                email: emails.join(', '),
                company: item.subtitle || '',
                post: '',
                address: ''
            };
            
            console.log('Обработанный контакт:', processedContact);
            return processedContact;
        });
    }
    
    // Обработка результатов стандартного сервиса crm.api.entity.search (fallback)
    function processStandardServiceContacts(data) {
        console.log('Обрабатываем данные от стандартного сервиса:', data);
        
        if (!data || !data.items) {
            return [];
        }
        
        return data.items.map(item => {
            console.log('Обрабатываем элемент:', item);
            
            // Формируем полное имя
            const fullName = item.title || 'Контакт #' + item.id;
            
            // Собираем телефоны из attributes.phone
            let phones = [];
            if (item.attributes && item.attributes.phone) {
                phones = item.attributes.phone.map(phone => phone.value);
                console.log('Найдены телефоны:', phones);
            }
            
            // Собираем email из attributes.email
            let emails = [];
            if (item.attributes && item.attributes.email) {
                emails = item.attributes.email.map(email => email.value);
                console.log('Найдены email:', emails);
            }
            
            // Убираем дубликаты
            phones = [...new Set(phones.filter(phone => phone && phone.trim()))];
            emails = [...new Set(emails.filter(email => email && email.trim()))];
            
            const processedContact = {
                id: item.id,
                name: fullName,
                firstName: '',
                lastName: '',
                secondName: '',
                phone: phones.join(', '),
                email: emails.join(', '),
                company: '',
                post: '',
                address: ''
            };
            
            console.log('Обработанный контакт:', processedContact);
            return processedContact;
        });
    }
    
    // Обработка результатов поиска сделок от Bitrix UI Entity Selector
    function processBitrixEntitySelectorDeals(data) {
        console.log('Обрабатываем данные сделок от Bitrix UI Entity Selector:', data);
        
        if (!data || !data.dialog || !data.dialog.items || !Array.isArray(data.dialog.items)) {
            return [];
        }
        
        return data.dialog.items.map(item => {
            console.log('Обрабатываем сделку:', item);
            
            const processedDeal = {
                id: item.id,
                title: item.title || 'Сделка #' + item.id,
                subtitle: item.subtitle || '',
                amount: '', // В UI Entity Selector нет поля amount
                stage: '', // В UI Entity Selector нет поля stage
                company: item.subtitle || '', // Используем subtitle как компанию
                currency: 'RUB'
            };
            
            console.log('Обработанная сделка:', processedDeal);
            return processedDeal;
        });
    }
    
    // Обработка результатов поиска сделок (fallback)
    function processStandardServiceDeals(data) {
        console.log('Обрабатываем данные сделок:', data);
        
        if (!data || !Array.isArray(data)) {
            return [];
        }
        
        return data.map(item => {
            console.log('Обрабатываем сделку:', item);
            
            const processedDeal = {
                id: item.id,
                title: item.title || 'Сделка #' + item.id,
                amount: item.amount || '',
                stage: item.stage || '',
                company: item.company || '',
                currency: item.currency || 'RUB'
            };
            
            console.log('Обработанная сделка:', processedDeal);
            return processedDeal;
        });
    }
    

    
    // Функция показа индикатора загрузки
    function showSearchLoading() {
        const dropdown = document.getElementById('contact-search-dropdown');
        if (dropdown) {
            dropdown.innerHTML = `
                <div class="search-loading">
                    <div class="loading-spinner"></div>
                    <span>Поиск контактов...</span>
                </div>
                <button class="create-new-contact-btn">
                    <span class="plus-icon">+</span>
                    создать новый контакт
                </button>
            `;
            
            // Добавляем обработчик для кнопки создания
            const createBtn = dropdown.querySelector('.create-new-contact-btn');
            if (createBtn) {
                createBtn.addEventListener('click', function() {
                    const contactInput = document.getElementById('contact-input');
                    const query = contactInput.value.trim();
                    console.log('Создание нового контакта:', query);
                    hideContactDropdown();
                });
            }
        }
    }
    
    // Функция обновления результатов поиска
    function updateSearchResults(contacts) {
        const dropdown = document.getElementById('contact-search-dropdown');
        if (!dropdown) return;
        
        if (contacts.length === 0) {
            dropdown.innerHTML = `
                <div class="search-no-results">
                    <span>Контакты не найдены</span>
                </div>
                <button class="create-new-contact-btn" onclick="showCreateContactForm()">
                    <span class="plus-icon">+</span>
                    создать новый контакт
                </button>
            `;
        } else {
            let resultsHtml = '';
            
            contacts.forEach(contact => {
                resultsHtml += `
                    <div class="search-contact-item" data-contact-id="${contact.id}">
                        <div class="contact-info">
                            <div class="contact-name">${contact.name}</div>
                            <div class="contact-details">
                                ${contact.phone ? `<div class="contact-phone">📞 ${contact.phone}</div>` : ''}
                                ${contact.email ? `<div class="contact-email">✉️ ${contact.email}</div>` : ''}
                                ${contact.company ? `<div class="contact-company">🏢 ${contact.company}</div>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            dropdown.innerHTML = resultsHtml + `
                <button class="create-new-contact-btn" onclick="showCreateContactForm()">
                    <span class="plus-icon">+</span>
                    создать новый контакт
                </button>
            `;
            
            // Добавляем обработчики клика для контактов
            const contactItems = dropdown.querySelectorAll('.search-contact-item');
            console.log('Добавляем обработчики для', contactItems.length, 'контактов');
            contactItems.forEach(item => {
                item.addEventListener('click', function() {
                    console.log('Клик по контакту, элемент:', this);
                    const contactId = this.getAttribute('data-contact-id');
                    console.log('Contact ID:', contactId);
                    const contact = contacts.find(c => c.id == contactId);
                    console.log('Найденный контакт:', contact);
                    if (contact) {
                        console.log('Вызываем selectContact для:', contact);
                        selectContact(contact);
                    } else {
                        console.error('Контакт не найден для ID:', contactId);
                    }
                });
            });
            
            // Добавляем обработчик для кнопки создания
            const createBtn = dropdown.querySelector('.create-new-contact-btn');
            if (createBtn) {
                createBtn.addEventListener('click', function() {
                    const contactInput = document.getElementById('contact-input');
                    const query = contactInput.value.trim();
                    console.log('Создание нового контакта:', query);
                    hideContactDropdown();
                });
            }
        }
    }
    
    // Функции для работы с результатами поиска сделок
    function showDealSearchLoading() {
        const dropdown = document.getElementById('deal-search-dropdown');
        if (dropdown) {
            dropdown.innerHTML = `
                <div class="search-loading">
                    <div class="loading-spinner"></div>
                    <span>Поиск сделок...</span>
                </div>
                <button class="create-new-deal-btn">
                    <span class="plus-icon">+</span>
                    создать новую сделку
                </button>
            `;
            
            // Добавляем обработчик для кнопки создания
            const createBtn = dropdown.querySelector('.create-new-deal-btn');
            if (createBtn) {
                createBtn.addEventListener('click', function() {
                    const dealInput = document.getElementById('deal-input');
                    const query = dealInput.value.trim();
                    console.log('Создание новой сделки:', query);
                    hideDealDropdown();
                });
            }
        }
    }
    
    // Функция обновления результатов поиска сделок
    function updateDealSearchResults(deals) {
        const dropdown = document.getElementById('deal-search-dropdown');
        if (!dropdown) return;
        
        if (deals.length === 0) {
            dropdown.innerHTML = `
                <div class="search-no-results">
                    <span>Сделки не найдены</span>
                </div>
                <button class="create-new-deal-btn">
                    <span class="plus-icon">+</span>
                    создать новую сделку
                </button>
            `;
        } else {
            let resultsHtml = '';
            
            deals.forEach(deal => {
                resultsHtml += `
                    <div class="search-deal-item" data-deal-id="${deal.id}">
                        <div class="deal-info">
                            <div class="deal-title">${deal.title}</div>
                            <div class="deal-details">
                                ${deal.subtitle ? `<div class="deal-company">🏢 ${deal.subtitle}</div>` : ''}
                                ${deal.amount ? `<div class="deal-amount">💰 ${deal.amount} ${deal.currency}</div>` : ''}
                                ${deal.stage ? `<div class="deal-stage">📊 ${deal.stage}</div>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            dropdown.innerHTML = resultsHtml + `
                <button class="create-new-deal-btn">
                    <span class="plus-icon">+</span>
                    создать новую сделку
                </button>
            `;
            
            // Добавляем обработчики клика для сделок
            const dealItems = dropdown.querySelectorAll('.search-deal-item');
            dealItems.forEach(item => {
                item.addEventListener('click', function() {
                    const dealId = this.getAttribute('data-deal-id');
                    const deal = deals.find(d => d.id == dealId);
                    if (deal) {
                        selectDeal(deal);
                    }
                });
            });
            
            // Добавляем обработчик для кнопки создания
            const createBtn = dropdown.querySelector('.create-new-deal-btn');
            if (createBtn) {
                createBtn.addEventListener('click', function() {
                    const dealInput = document.getElementById('deal-input');
                    const query = dealInput.value.trim();
                    console.log('Создание новой сделки:', query);
                    hideDealDropdown();
                });
            }
        }
    }
    
    // Функция выбора сделки и заполнения полей
    function selectDeal(deal) {
        console.log('Выбрана сделка:', deal);
        
        // Сохраняем ID сделки в скрытом поле
        const dealIdInput = document.getElementById('deal-id');
        if (dealIdInput) {
            dealIdInput.value = deal.id;
        }
        
        // Заполняем поле сделки
        const dealInput = document.getElementById('deal-input');
        if (dealInput) {
            dealInput.value = deal.title;
        }
        
        // Скрываем выпадающее меню
        hideDealDropdown();
        
        // Показываем кнопки действий
        const modalFooter = document.querySelector('.deal-modal-footer');
        if (modalFooter) {
            modalFooter.style.display = 'block';
        }
    }
    
    // Функция показа выпадающего окошка для сделок
    function showDealDropdown() {
        const dropdown = document.getElementById('deal-search-dropdown');
        if (dropdown) {
            dropdown.style.display = 'block';
        }
    }
    
    // Функция скрытия выпадающего окошка для сделок
    function hideDealDropdown() {
        const dropdown = document.getElementById('deal-search-dropdown');
        if (dropdown) {
            dropdown.style.display = 'none';
        }
    }
    
    // Функция показа ошибки поиска сделок
    function showDealSearchError(errorMessage) {
        const dropdown = document.getElementById('deal-search-dropdown');
        if (dropdown) {
            dropdown.innerHTML = `
                <div class="search-error">
                    <span>❌ ${errorMessage}</span>
                </div>
            `;
        }
    }
    
    // Функция выбора контакта и заполнения полей
    function selectContact(contact) {
        console.log('Выбран контакт:', contact);
        
        // Сохраняем ID контакта в скрытом поле
        const contactIdInput = document.getElementById('contact-id');
        if (contactIdInput) {
            contactIdInput.value = contact.id;
        }
        
        // Заполняем поле контакта
        const contactInput = document.getElementById('contact-input');
        if (contactInput) {
            contactInput.value = contact.name;
        }
        
        // Заполняем поле телефона
        const phoneInput = document.getElementById('phone-input');
        if (phoneInput && contact.phone) {
            phoneInput.value = contact.phone;
        }
        
        // Заполняем поле email
        const emailInput = document.getElementById('email-input');
        if (emailInput && contact.email) {
            emailInput.value = contact.email;
        }
        
        // Заполняем поле компании
        const companyInput = document.getElementById('company-input');
        if (companyInput && contact.company) {
            companyInput.value = contact.company;
        }
        
        // Показываем дополнительные поля
        showContactDetailsFields();
        
        // Скрываем выпадающий список
        hideContactDropdown();
        
        // Показываем уведомление о выборе контакта
        showNotification(`Выбран контакт: ${contact.name}`, 'success');
        
        // Логируем заполненные данные для отладки
        console.log('Заполненные поля:', {
            contact: contactInput ? contactInput.value : '',
            phone: phoneInput ? phoneInput.value : '',
            email: emailInput ? emailInput.value : '',
            company: companyInput ? companyInput.value : ''
        });
    }
    
    // Функция показа дополнительных полей и кнопок
    function showContactDetailsFields() {
        console.log('showContactDetailsFields вызвана');
        
        // Показываем дополнительные поля с анимацией
        const detailFields = document.querySelectorAll('.contact-details-field');
        console.log('Найдено дополнительных полей:', detailFields.length);
        detailFields.forEach((field, index) => {
            setTimeout(() => {
                console.log('Показываем поле:', field);
                field.style.display = 'block';
                field.classList.add('show');
            }, index * 100); // Задержка для последовательного появления
        });
        
        // Показываем кнопки в футере с анимацией
        const footer = document.querySelector('.client-modal-footer');
        console.log('Найден футер:', footer);
        if (footer) {
            const delay = detailFields.length * 100 + 100;
            console.log('Показываем футер через', delay, 'мс');
            setTimeout(() => {
                console.log('Показываем футер сейчас');
                footer.style.display = 'flex';
                footer.classList.add('show');
            }, delay);
        } else {
            console.error('Футер не найден!');
        }
        
        // Обновляем инструкцию
        const instruction = document.querySelector('.modal-instruction');
        if (instruction) {
            instruction.textContent = 'Вы можете отредактировать данные контакта или сохранить их';
        }
    }
    
    // Функция скрытия дополнительных полей и кнопок
    function hideContactDetailsFields() {
        // Скрываем дополнительные поля
        const detailFields = document.querySelectorAll('.contact-details-field');
        detailFields.forEach(field => {
            field.classList.remove('show');
            setTimeout(() => {
                field.style.display = 'none';
            }, 300);
        });
        
        // Скрываем кнопки в футере
        const footer = document.querySelector('.client-modal-footer');
        if (footer) {
            footer.classList.remove('show');
            setTimeout(() => {
                footer.style.display = 'none';
            }, 300);
        }
        
        // Возвращаем исходную инструкцию
        const instruction = document.querySelector('.modal-instruction');
        if (instruction) {
            instruction.textContent = 'Чтобы выбрать клиента из CRM, начните вводить имя, телефон, e-mail или название компании';
        }
    }
    
    // Функция сохранения данных клиента (будет вынесена в глобальную область)
    window.saveClientData = function() {
        const contactInput = document.getElementById('contact-input');
        const phoneInput = document.getElementById('phone-input');
        const emailInput = document.getElementById('email-input');
        const companyInput = document.getElementById('company-input');
        const contactIdInput = document.getElementById('contact-id');
        
        const clientData = {
            id: contactIdInput ? contactIdInput.value : '',
            contact: contactInput ? contactInput.value.trim() : '',
            phone: phoneInput ? phoneInput.value.trim() : '',
            email: emailInput ? emailInput.value.trim() : '',
            company: companyInput ? companyInput.value.trim() : ''
        };
        
        // Проверяем, что ID контакта указан
        if (!clientData.id) {
            showNotification('Не выбран контакт из списка', 'error');
            return;
        }
        
        // Проверяем, что хотя бы одно поле заполнено
        if (!clientData.contact && !clientData.phone && !clientData.email && !clientData.company) {
            showNotification('Заполните хотя бы одно поле', 'error');
            return;
        }
        
        console.log('Сохранение данных клиента:', clientData);
        
        // Получаем ID текущего события (если есть)
        const currentEventId = getCurrentEventId();
        if (!currentEventId) {
            showNotification('Не удалось определить событие для сохранения контакта', 'error');
            return;
        }
        
        // Отправляем AJAX запрос для сохранения данных
        const csrfToken = getCSRFToken();
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
            },
            body: new URLSearchParams({
                action: 'saveEventContact',
                eventId: currentEventId,
                contactData: JSON.stringify(clientData),
                sessid: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Контакт успешно сохранен', 'success');
                closeClientModal();
                
                // Обновляем информацию о клиенте в боковом окне
                updateClientInfoInSidePanel(clientData);
            } else {
                showNotification('Ошибка сохранения: ' + (data.error || 'Неизвестная ошибка'), 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка AJAX запроса:', error);
            showNotification('Ошибка соединения с сервером', 'error');
        });
    };
    
    // Функция получения ID текущего события
    window.getCurrentEventId = function() {
        // Пытаемся получить ID события из различных источников
        // 1. Из глобальной переменной (если есть)
        if (typeof currentEventId !== 'undefined' && currentEventId) {
            return currentEventId;
        }
        
        // 2. Из URL параметров
        const urlParams = new URLSearchParams(window.location.search);
        const eventIdFromUrl = urlParams.get('eventId');
        if (eventIdFromUrl) {
            return eventIdFromUrl;
        }
        
        // 3. Из атрибутов модального окна
        const modal = document.getElementById('clientModal');
        if (modal && modal.getAttribute('data-event-id')) {
            return modal.getAttribute('data-event-id');
        }
        
        // 4. Если ничего не найдено, возвращаем null
        return null;
    };
    
    // Функция показа ошибки поиска
    function showSearchError(errorMessage) {
        const dropdown = document.getElementById('contact-search-dropdown');
        if (dropdown) {
            dropdown.innerHTML = `
                <div class="search-error">
                    <span>❌ ${errorMessage}</span>
                </div>
                <button class="create-new-contact-btn">
                    <span class="plus-icon">+</span>
                    создать новый контакт
                </button>
            `;
            
            // Добавляем обработчик для кнопки создания
            const createBtn = dropdown.querySelector('.create-new-contact-btn');
            if (createBtn) {
                createBtn.addEventListener('click', function() {
                    const contactInput = document.getElementById('contact-input');
                    const query = contactInput.value.trim();
                    console.log('Создание нового контакта:', query);
                    hideContactDropdown();
                });
            }
        }
    }

    // Функция поиска клиентов
    function searchClients(query, type) {
        console.log('Поиск клиентов:', query, 'тип:', type);
        
        // AJAX запрос к серверу для поиска клиентов
        const csrfToken = getCSRFToken();
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
            },
            body: new URLSearchParams({
                action: 'searchClients',
                query: query,
                type: type,
                sessid: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSearchResults(data.clients);
            } else {
                console.error('Ошибка поиска клиентов:', data.error);
                showSearchResults([]);
            }
        })
        .catch(error => {
            console.error('Ошибка AJAX запроса:', error);
            showSearchResults([]);
        });
    }

    // Функция отображения результатов поиска
    function showSearchResults(clients) {
        // Создаем контейнер для результатов, если его нет
        let resultsContainer = document.getElementById('client-search-results');
        if (!resultsContainer) {
            resultsContainer = document.createElement('div');
            resultsContainer.id = 'client-search-results';
            resultsContainer.className = 'client-search-results';
            
            const modalBody = document.querySelector('.client-modal-body');
            modalBody.appendChild(resultsContainer);
        }
        
        // Очищаем предыдущие результаты
        resultsContainer.innerHTML = '';
        
        if (clients.length === 0) {
            resultsContainer.innerHTML = '<div class="no-results">Клиенты не найдены</div>';
            return;
        }
        
        // Создаем элементы для каждого клиента
        clients.forEach(client => {
            const clientElement = document.createElement('div');
            clientElement.className = 'client-search-item';
            clientElement.innerHTML = `
                <div class="client-info">
                    <div class="client-name">${client.name}</div>
                    <div class="client-details">
                        ${client.phone ? `<span class="client-phone">${client.phone}</span>` : ''}
                        ${client.email ? `<span class="client-email">${client.email}</span>` : ''}
                        ${client.company ? `<span class="client-company">${client.company}</span>` : ''}
                    </div>
                </div>
                <button class="select-client-btn" data-client-id="${client.id}">Выбрать</button>
            `;
            
            // Добавляем обработчик клика для выбора клиента
            const selectBtn = clientElement.querySelector('.select-client-btn');
            selectBtn.addEventListener('click', function() {
                selectClient(client);
            });
            
            resultsContainer.appendChild(clientElement);
        });
    }

    // Функция очистки результатов поиска
    function clearSearchResults() {
        const resultsContainer = document.getElementById('client-search-results');
        if (resultsContainer) {
            resultsContainer.innerHTML = '';
        }
    }

    // Функция выбора клиента
    function selectClient(client) {
        console.log('Выбран клиент:', client);
        
        // Обновляем информацию о клиенте в боковом окне
        updateClientInfoInSidePanel(client);
        
        // Закрываем модальное окно
        closeClientModal();
        
        // Очищаем поля ввода
        const contactInput = document.getElementById('contact-input');
        const companyInput = document.getElementById('company-input');
        if (contactInput) contactInput.value = '';
        if (companyInput) companyInput.value = '';
        
        // Очищаем результаты поиска
        clearSearchResults();
    }

    function updateClientInfoInSidePanel(client) {
        const clientNameElement = document.querySelector('.client-name');
        const clientPlaceholderElement = document.querySelector('.client-placeholder');
        
        if (clientNameElement && clientPlaceholderElement) {
            clientNameElement.textContent = client.name || client.contact || 'Неизвестный клиент';
            
            // Формируем строку с контактной информацией
            const contactInfo = [];
            if (client.phone) contactInfo.push(`📞 ${client.phone}`);
            if (client.email) contactInfo.push(`✉️ ${client.email}`);
            if (client.company) contactInfo.push(`🏢 ${client.company}`);
            
            clientPlaceholderElement.textContent = contactInfo.length > 0 
                ? contactInfo.join(' • ') 
                : 'Контактная информация не указана';
        }
        
        // Обновляем заголовок события в календаре
        if (window.currentEventId) {
            updateEventTitleInCalendar(window.currentEventId, client.name || client.contact || '', client.phone || '');
        }
    }

    function loadEventContact(contactId) {
        console.log('loadEventContact: Загружаем контакт с ID:', contactId);
        
        const csrfToken = getCSRFToken();
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
            },
            body: new URLSearchParams({
                action: 'getEventContacts',
                eventId: window.currentEventId,
                sessid: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.contact) {
                console.log('loadEventContact: Получены данные контакта:', data.contact);
                updateClientInfoInSidePanel(data.contact);
            } else {
                console.log('loadEventContact: Контакт не найден или ошибка:', data.error);
                resetClientInfoInSidePanel();
            }
            
            // Увеличиваем счетчик завершенных загрузок
            window.sidePanelLoadingComplete++;
            checkSidePanelLoadingComplete();
        })
        .catch(error => {
            console.error('loadEventContact: Ошибка при загрузке контакта:', error);
            resetClientInfoInSidePanel();
            
            // Увеличиваем счетчик завершенных загрузок даже при ошибке
            window.sidePanelLoadingComplete++;
            checkSidePanelLoadingComplete();
        });
    }

    function resetClientInfoInSidePanel() {
        const clientNameElement = document.querySelector('.client-name');
        const clientPlaceholderElement = document.querySelector('.client-placeholder');
        
        if (clientNameElement && clientPlaceholderElement) {
            clientNameElement.textContent = 'Нет клиента';
            clientPlaceholderElement.textContent = 'Добавьте информацию о клиенте';
        }
    }

    function loadEventDeal(dealId) {
        console.log('loadEventDeal: Загружаем сделку с ID:', dealId);
        
        const csrfToken = getCSRFToken();
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
            },
            body: new URLSearchParams({
                action: 'getEventDeals',
                eventId: window.currentEventId,
                sessid: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.deal) {
                console.log('loadEventDeal: Получены данные сделки:', data.deal);
                updateDealInfoInSidePanel(data.deal);
            } else {
                console.log('loadEventDeal: Сделка не найдена или ошибка:', data.error);
                resetDealInfoInSidePanel();
            }
            
            // Увеличиваем счетчик завершенных загрузок
            window.sidePanelLoadingComplete++;
            checkSidePanelLoadingComplete();
        })
        .catch(error => {
            console.error('loadEventDeal: Ошибка при загрузке сделки:', error);
            resetDealInfoInSidePanel();
            
            // Увеличиваем счетчик завершенных загрузок даже при ошибке
            window.sidePanelLoadingComplete++;
            checkSidePanelLoadingComplete();
        });
    }

    function resetDealInfoInSidePanel() {
        const dealStatusElement = document.getElementById('deal-status');
        
        if (dealStatusElement) {
            dealStatusElement.textContent = 'Нет сделки';
            dealStatusElement.style.color = '#6c757d';
        }
        
        // Убираем класс --confirmed с иконки в deal-card
        const dealCard = document.getElementById('deal-card');
        if (dealCard) {
            const iconElement = dealCard.querySelector('.card-icon > .booking-actions-popup-item-icon');
            if (iconElement) {
                iconElement.classList.remove('--confirmed');
                console.log('resetDealInfoInSidePanel: Убран класс --confirmed с иконки сделки');
            }
        }
        
        // Сбрасываем иконку сделки только для текущего события
        if (window.currentEventId) {
            const eventElement = document.querySelector(`[data-event-id="${window.currentEventId}"]`);
            if (eventElement) {
                const dealIcon = eventElement.querySelector('.deal-icon');
                if (dealIcon) {
                    dealIcon.classList.remove('active');
                }
            }
        }
    }

    // Функция создания новой сделки
    function createNewDeal() {
        const eventId = getCurrentEventId();
        if (!eventId) {
            showNotification('Ошибка: не удалось определить событие', 'error');
            return;
        }
        
        // Проверяем, есть ли привязанный контакт
        checkEventContactAndCreateDeal(eventId);
    }

    // Функция проверки контакта и создания сделки
    function checkEventContactAndCreateDeal(eventId) {
        const csrfToken = getCSRFToken();
        
        // Получаем данные события для проверки контакта
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
        .then(data => {
            if (data.success && data.event) {
                const event = data.event;
                
                if (!event.CONTACT_ENTITY_ID) {
                    showNotification('Сначала нужно привязать контакт к событию, а затем можно будет создать сделку', 'warning');
                    return;
                }
                
                // Показываем подтверждение создания сделки
                showDealCreationConfirmation(eventId, event.CONTACT_ENTITY_ID);
            } else {
                showNotification('Ошибка при получении данных события', 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка при проверке контакта:', error);
            showNotification('Ошибка соединения с сервером', 'error');
        });
    }

    // Функция показа подтверждения создания сделки
    function showDealCreationConfirmation(eventId, contactId) {
        if (confirm('Вы действительно хотите создать новую сделку для события?')) {
            createDealForEvent(eventId, contactId);
        }
    }

    // Функция создания сделки для события
    function createDealForEvent(eventId, contactId) {
        const csrfToken = getCSRFToken();
        
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
            },
            body: new URLSearchParams({
                action: 'createDealForEvent',
                eventId: eventId,
                contactId: contactId,
                sessid: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Сделка успешно создана и привязана к событию', 'success');
                
                // Обновляем отображение в календаре
                updateEventDealIcon(eventId, data.dealId);
                
                // Обновляем информацию о сделке в боковом окне
                updateDealInfoInSidePanel({
                    id: data.dealId,
                    title: 'Сделка создана'
                });
            } else {
                showNotification('Ошибка создания сделки: ' + (data.error || 'Неизвестная ошибка'), 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка при создании сделки:', error);
            showNotification('Ошибка соединения с сервером', 'error');
        });
    }

    // Делаем функцию глобальной
    window.createNewDeal = createNewDeal;

    // Функция открытия деталей сделки
    function openDealDetails() {
        const eventId = getCurrentEventId();
        if (!eventId) {
            showNotification('Ошибка: не удалось определить событие', 'error');
            return;
        }

        // Получаем ID сделки из статуса
        const dealStatusElement = document.getElementById('deal-status');
        if (!dealStatusElement) {
            showNotification('Ошибка: не удалось найти информацию о сделке', 'error');
            return;
        }

        // Проверяем, есть ли сделка
        if (dealStatusElement.textContent === 'Не добавлена' || dealStatusElement.textContent === 'Нет сделки') {
            showNotification('Сначала нужно добавить сделку к событию', 'warning');
            return;
        }

        // Закрываем боковое окно события
        closeEventSidePanel();

        // Получаем ID сделки из данных события
        getDealIdFromEvent(eventId);
    }

    // Функция получения ID сделки из события
    function getDealIdFromEvent(eventId) {
        const csrfToken = getCSRFToken();
        
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
        .then(data => {
            if (data.success && data.event && data.event.DEAL_ENTITY_ID) {
                const dealId = data.event.DEAL_ENTITY_ID;
                openDealInSidePanel(dealId);
            } else {
                showNotification('Сделка не найдена', 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка при получении данных сделки:', error);
            showNotification('Ошибка соединения с сервером', 'error');
        });
    }

    // Функция открытия сделки в боковом окне
    function openDealInSidePanel(dealId) {
        const dealUrl = `/crm/deal/details/${dealId}/?IFRAME=Y&IFRAME_TYPE=SIDE_SLIDER`;
        
        // Открываем штатное Bitrix окно в боковом слайдере
        if (typeof BX !== 'undefined' && BX.SidePanel) {
            BX.SidePanel.Instance.open(dealUrl);
        } else {
            // Fallback для случаев, когда BX.SidePanel недоступен
            window.open(dealUrl, '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');
        }
    }

    // Делаем функцию глобальной
    window.openDealDetails = openDealDetails;

    // Функция показа формы создания контакта
    function showCreateContactForm() {
        const createForm = document.getElementById('create-contact-form');
        const searchGroup = document.getElementById('contact-search-group');
        const backToSearch = document.getElementById('back-to-search');
        const searchDropdown = document.getElementById('contact-search-dropdown');
        
        // Скрываем группу поиска
        if (searchGroup) {
            searchGroup.style.display = 'none';
        }
        
        // Показываем кнопку "Назад"
        if (backToSearch) {
            backToSearch.style.display = 'block';
        }
        
        // Показываем форму создания
        if (createForm) {
            createForm.style.display = 'block';
        }
        
        // Скрываем выпадающий список
        if (searchDropdown) {
            searchDropdown.style.display = 'none';
        }
        
        // Очищаем поля формы
        clearCreateContactForm();
    }
    
    // Функция скрытия формы создания контакта
    function hideCreateContactForm() {
        const createForm = document.getElementById('create-contact-form');
        const searchGroup = document.getElementById('contact-search-group');
        const backToSearch = document.getElementById('back-to-search');
        
        // Скрываем форму создания
        if (createForm) {
            createForm.style.display = 'none';
        }
        
        // Показываем группу поиска
        if (searchGroup) {
            searchGroup.style.display = 'block';
        }
        
        // Скрываем кнопку "Назад"
        if (backToSearch) {
            backToSearch.style.display = 'none';
        }
        
        // Очищаем поля формы
        clearCreateContactForm();
    }
    
    // Функция очистки формы создания контакта
    function clearCreateContactForm() {
        const fields = [
            'new-contact-name',
            'new-contact-lastname', 
            'new-contact-phone',
            'new-contact-email'
        ];
        
        fields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.value = '';
            }
        });
    }

    // Функция выбора контакта по ID (для автоматического выбора после создания)
    function selectContactById(contactId) {
        // Получаем данные контакта и заполняем форму
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'getContactData',
                contactId: contactId,
                sessid: getCSRFToken()
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.contact) {
                // Заполняем форму данными контакта
                document.getElementById('contact-id').value = data.contact.id;
                document.getElementById('contact-input').value = data.contact.name;
                document.getElementById('phone-input').value = data.contact.phone;
                document.getElementById('email-input').value = data.contact.email;
                
                // Показываем поля деталей контакта
                const contactDetailsFields = document.querySelectorAll('.contact-details-field');
                contactDetailsFields.forEach(field => {
                    field.style.display = 'block';
                });
                
                // Скрываем выпадающий список
                const dropdown = document.getElementById('contact-search-dropdown');
                if (dropdown) {
                    dropdown.style.display = 'none';
                }
            }
        })
        .catch(error => {
            console.error('Ошибка получения данных контакта:', error);
        });
    }

    // Функция создания контакта
    function createContact() {
        const name = document.getElementById('new-contact-name').value.trim();
        const lastname = document.getElementById('new-contact-lastname').value.trim();
        const phone = document.getElementById('new-contact-phone').value.trim();
        const email = document.getElementById('new-contact-email').value.trim();
        
        // Проверяем обязательные поля
        if (!name) {
            showNotification('Поле "Имя" обязательно для заполнения', 'error');
            return;
        }
        
        // Формируем данные контакта
        const contactData = {
            name: name,
            lastname: lastname,
            phone: phone,
            email: email
        };
        
        // Отправляем запрос на создание контакта
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'createContact',
                contactData: JSON.stringify(contactData),
                sessid: getCSRFToken()
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Контакт успешно создан', 'success');
                
                // Получаем ID текущего события
                const eventId = getCurrentEventId();
                if (eventId) {
                    // Сохраняем контакт к событию
                    saveContactToEvent(eventId, data.contactId, data.contact);
                }
                
                // Закрываем модальное окно
                closeClientModal();
            } else {
                showNotification('Ошибка создания контакта: ' + (data.error || 'Неизвестная ошибка'), 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка при создании контакта:', error);
            showNotification('Ошибка соединения с сервером', 'error');
        });
    }

    // Функция сохранения контакта к событию
    function saveContactToEvent(eventId, contactId, contactData) {
        const csrfToken = getCSRFToken();
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
            },
            body: new URLSearchParams({
                action: 'saveEventContact',
                eventId: eventId,
                contactId: contactId,
                contactData: JSON.stringify(contactData),
                sessid: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Обновляем иконку контакта в календаре
                updateEventContactIcon(eventId, contactId);
                // Обновляем информацию о контакте в боковой панели
                updateContactInfoInSidePanel(contactData);
            } else {
                console.error('Ошибка сохранения контакта:', data.error);
            }
        })
        .catch(error => {
            console.error('Ошибка при сохранении контакта:', error);
        });
    }

    // Функция обновления иконки контакта в календаре
    function updateEventContactIcon(eventId, contactId) {
        const eventElement = document.querySelector(`[data-event-id="${eventId}"]`);
        if (eventElement) {
            const contactIcon = eventElement.querySelector('.contact-icon');
            if (contactIcon) {
                contactIcon.classList.remove('inactive');
                contactIcon.classList.add('active');
            }
        }
    }
    
    // Функция обновления заголовка события в календаре
    function updateEventTitleInCalendar(eventId, contactName, contactPhone) {
        const eventElement = document.querySelector(`[data-event-id="${eventId}"]`);
        if (eventElement) {
            const titleElement = eventElement.querySelector('.event-title');
            if (titleElement) {
                // Получаем оригинальное название события (до первого " - ")
                let originalTitle = titleElement.textContent.split(' - ')[0];
                
                // Формируем новый заголовок: Название - Имя - Телефон
                let newTitle = originalTitle;
                if (contactName) {
                    newTitle += ' - ' + contactName;
                }
                if (contactPhone) {
                    newTitle += ' - ' + contactPhone;
                }
                
                titleElement.textContent = newTitle;
                console.log('updateEventTitleInCalendar: Обновлен заголовок события', eventId, 'на', newTitle);
            }
        }
    }

    // Функция обновления информации о контакте в боковой панели
    function updateContactInfoInSidePanel(contactData) {
        // Обновляем client-section используя существующую функцию
        updateClientInfoInSidePanel(contactData);
    }

    // Функция открытия деталей контакта
    function openContactDetails() {
        const eventId = getCurrentEventId();
        console.log('openContactDetails: EventId =', eventId);
        
        if (!eventId) {
            showNotification('Ошибка: не удалось определить событие', 'error');
            return;
        }
        
        // Проверяем наличие контакта по содержимому client-name
        const clientNameElement = document.querySelector('.client-name');
        console.log('openContactDetails: Client name =', clientNameElement ? clientNameElement.textContent : 'not found');
        
        if (!clientNameElement) {
            showNotification('Ошибка: не удалось найти информацию о клиенте', 'error');
            return;
        }
        
        if (clientNameElement.textContent === 'Нет клиента') {
            showNotification('Сначала нужно добавить контакт к событию', 'warning');
            return;
        }
        
        // Закрываем боковое окно события
        closeEventSidePanel();
        
        // Получаем ID контакта из данных события
        getContactIdFromEvent(eventId);
    }
    
    // Функция получения ID контакта из события
    function getContactIdFromEvent(eventId) {
        console.log('getContactIdFromEvent: Запрашиваем данные события', eventId);
        
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'getEventData',
                eventId: eventId,
                sessid: getCSRFToken()
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log('getContactIdFromEvent: Получены данные события:', data);
            
            if (data.success && data.event && data.event.CONTACT_ENTITY_ID) {
                console.log('getContactIdFromEvent: Найден контакт с ID:', data.event.CONTACT_ENTITY_ID);
                openContactInSidePanel(data.event.CONTACT_ENTITY_ID);
            } else {
                console.log('getContactIdFromEvent: Контакт не найден в данных события');
                showNotification('Контакт не найден', 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка получения данных события:', error);
            showNotification('Ошибка соединения с сервером', 'error');
        });
    }
    
    // Функция открытия контакта в боковой панели Bitrix
    function openContactInSidePanel(contactId) {
        const contactUrl = `/crm/contact/details/${contactId}/?IFRAME=Y&IFRAME_TYPE=SIDE_SLIDER`;
        
        if (typeof BX !== 'undefined' && BX.SidePanel) {
            BX.SidePanel.Instance.open(contactUrl, {
                title: 'Детали контакта',
                width: 800,
                allowChangeHistory: false
            });
        } else {
            // Fallback для случаев, когда BX.SidePanel недоступен
            window.open(contactUrl, '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');
        }
    }

    // Функции для работы с модальным окном заметок
    function openNoteModal() {
        const modal = document.getElementById('noteModal');
        if (modal) {
            modal.style.display = 'flex';
            setTimeout(() => {
                modal.classList.add('show');
            }, 10);
            
            // Фокусируемся на textarea
            const textarea = document.getElementById('note-text');
            if (textarea) {
                setTimeout(() => {
                    textarea.focus();
                }, 300);
            }
        }
    }

    function closeNoteModal() {
        const modal = document.getElementById('noteModal');
        if (modal) {
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
                // Очищаем textarea
                const textarea = document.getElementById('note-text');
                if (textarea) {
                    textarea.value = '';
                }
            }, 300);
        }
    }

    function saveNote() {
        const textarea = document.getElementById('note-text');
        const noteText = textarea ? textarea.value.trim() : '';
        
        if (!noteText) {
            showNotification('Введите текст заметки', 'warning');
            return;
        }
        
        const eventId = getCurrentEventId();
        if (!eventId) {
            showNotification('Ошибка: не удалось определить событие', 'error');
            return;
        }
        
        // Отправляем AJAX запрос для сохранения заметки
        const csrfToken = getCSRFToken();
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
            },
            body: new URLSearchParams({
                action: 'saveEventNote',
                eventId: eventId,
                noteText: noteText,
                sessid: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Заметка успешно сохранена', 'success');
                closeNoteModal();
                // Обновляем отображение заметки
                updateNoteDisplay(noteText);
            } else {
                showNotification('Ошибка сохранения заметки: ' + (data.error || 'Неизвестная ошибка'), 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка при сохранении заметки:', error);
            showNotification('Ошибка соединения с сервером', 'error');
        });
    }

    function editNote() {
        const noteTextElement = document.getElementById('note-text-display');
        const currentNote = noteTextElement ? noteTextElement.textContent : '';
        
        // Заполняем модальное окно текущим текстом заметки
        const textarea = document.getElementById('note-text');
        if (textarea) {
            textarea.value = currentNote;
        }
        
        // Открываем модальное окно для редактирования
        openNoteModal();
    }

    function updateNoteDisplay(noteText) {
        const addNoteBtn = document.getElementById('add-note-btn');
        const noteDisplay = document.getElementById('note-display');
        const noteTextDisplay = document.getElementById('note-text-display');
        
        if (addNoteBtn && noteDisplay && noteTextDisplay) {
            if (noteText && noteText.trim()) {
                // Показываем заметку, скрываем кнопку
                addNoteBtn.style.display = 'none';
                noteDisplay.style.display = 'flex';
                noteTextDisplay.textContent = noteText;
            } else {
                // Скрываем заметку, показываем кнопку
                addNoteBtn.style.display = 'block';
                noteDisplay.style.display = 'none';
            }
        }
    }

    function loadEventNote(eventId) {
        console.log('loadEventNote: Загружаем заметку для события:', eventId);
        
        const csrfToken = getCSRFToken();
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
            },
            body: new URLSearchParams({
                action: 'getEventData',
                eventId: eventId,
                sessid: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.event) {
                console.log('loadEventNote: Получены данные события:', data.event);
                const noteText = data.event.NOTE || '';
                updateNoteDisplay(noteText);
            } else {
                console.log('loadEventNote: Ошибка загрузки данных события:', data.error);
                updateNoteDisplay('');
            }
        })
        .catch(error => {
            console.error('loadEventNote: Ошибка при загрузке заметки:', error);
            updateNoteDisplay('');
        });
    }

    // Функции для работы с модальным окном настроек филиала (перенесено в SidePanel)
    function openBranchModal() {
        // Получаем ID филиала из текущего календаря
        const branchId = document.querySelector('.artmax-calendar')?.getAttribute('data-branch-id') || 
                         document.querySelector('input[name="branch_id"]')?.value ||
                         '1';
        
        const url = `/local/components/artmax/branch.settings/page.php?BRANCH_ID=${branchId}&IFRAME=Y&IFRAME_TYPE=SIDE_SLIDER`;
        
        if (typeof BX !== 'undefined' && BX.SidePanel) {
            BX.SidePanel.Instance.open(url, {
                title: 'Настройки филиала',
                width: 600,
                cacheable: false,
                events: {
                    onClose: function() {
                        // Обновляем страницу при необходимости
                        if (window.location) {
                            // Можно сделать мягкое обновление через AJAX
                        }
                    }
                }
            });
        } else {
            window.open(url, '_blank', 'width=600,height=800,scrollbars=yes,resizable=yes');
        }
    }

    function closeBranchModal() {
        // Если SidePanel открыт, закрываем его
        if (typeof BX !== 'undefined' && BX.SidePanel && BX.SidePanel.Instance) {
            BX.SidePanel.Instance.close();
        }
    }

    // Функции для работы с мультиселектором сотрудников
    let selectedEmployees = [];
    let allEmployees = [];

    // Загрузка сотрудников в селекторы форм
    function loadEmployeesForSelectors() {
        const csrfToken = getCSRFToken();
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
            },
            body: new URLSearchParams({
                action: 'getEmployees',
                sessid: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.employees) {
                // Сохраняем сотрудников для повторного использования
                window.allEmployees = data.employees;
                
                // Заполняем селектор в форме добавления события
                populateEmployeeSelector('event-employee', data.employees);
                // Заполняем селектор в форме редактирования события
                populateEmployeeSelector('edit-event-employee', data.employees);
            }
        })
        .catch(error => {
            console.error('Ошибка при загрузке сотрудников для селекторов:', error);
        });
        
        // Загружаем сотрудников текущего филиала для формы расписания
        loadBranchEmployeesForSchedule();
    }

    // Загрузка сотрудников текущего филиала для формы расписания
    function loadBranchEmployeesForSchedule() {
        const branchId = getBranchId() || 1;
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
                console.log('Загружены сотрудники филиала для расписания:', data.employees);
                // Заполняем селектор в форме создания расписания только сотрудниками текущего филиала
                populateEmployeeSelector('schedule-employee', data.employees);
            } else {
                console.error('Ошибка загрузки сотрудников филиала:', data.error);
                // В случае ошибки используем всех сотрудников как fallback
                if (window.allEmployees) {
                    populateEmployeeSelector('schedule-employee', window.allEmployees);
                }
            }
        })
        .catch(error => {
            console.error('Ошибка при загрузке сотрудников филиала для расписания:', error);
            // В случае ошибки используем всех сотрудников как fallback
            if (window.allEmployees) {
                populateEmployeeSelector('schedule-employee', window.allEmployees);
            }
        });
    }

    // Заполнение селектора сотрудников
    function populateEmployeeSelector(selectorId, employees) {
        const selector = document.getElementById(selectorId);
        if (!selector) return;

        // Очищаем селектор, оставляя только первую опцию
        selector.innerHTML = '<option value="">Выберите сотрудника</option>';
        
        // Добавляем сотрудников
        employees.forEach(employee => {
            const option = document.createElement('option');
            option.value = employee.ID;
            option.textContent = `${employee.NAME} ${employee.LAST_NAME}`.trim() || employee.LOGIN;
            selector.appendChild(option);
        });
    }

    // Установка выбранного сотрудника в селекторе
    function setSelectedEmployee(selectorId, employeeId) {
        const selector = document.getElementById(selectorId);
        if (selector) {
            selector.value = employeeId || '';
        }
    }

    // Функции для работы с SidePanel врача
    window.openEmployeeModal = function() {
        let eventId = getCurrentEventId();
        if (!eventId) {
            showNotification('Ошибка: не удалось определить событие', 'error');
            return;
        }
        
        const url = `/local/components/artmax/employee.form/page.php?EVENT_ID=${eventId}&IFRAME=Y&IFRAME_TYPE=SIDE_SLIDER`;
        if (typeof BX !== 'undefined' && BX.SidePanel) {
            BX.SidePanel.Instance.open(url, {
                width: 600,
                cacheable: false,
                allowChangeHistory: false,
                events: {
                    onClose: function() {
                        // Можно добавить логику при закрытии, если нужно
                    }
                }
            });
        } else {
            // Fallback для старых версий
            window.open(url, '_blank', 'width=600,height=400');
        }
    }

    function loadEmployeesForEmployeeModal() {
        // Проверяем, есть ли уже загруженные сотрудники
        if (window.allEmployees && window.allEmployees.length > 0) {
            populateEmployeeModalSelector(window.allEmployees);
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
                action: 'getEmployees',
                sessid: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.employees) {
                // Сохраняем сотрудников для повторного использования
                window.allEmployees = data.employees;
                populateEmployeeModalSelector(data.employees);
            }
        })
        .catch(error => {
            console.error('Ошибка при загрузке сотрудников для модального окна:', error);
        });
    }

    function populateEmployeeModalSelector(employees) {
        const selector = document.getElementById('employee-select');
        if (!selector) return;

        // Очищаем селектор, оставляя только первую опцию
        selector.innerHTML = '<option value="">Выберите врача</option>';
        
        // Добавляем сотрудников
        employees.forEach(employee => {
            const option = document.createElement('option');
            option.value = employee.ID;
            option.textContent = `${employee.NAME} ${employee.LAST_NAME}`.trim() || employee.LOGIN;
            selector.appendChild(option);
        });
        
        // Если есть текущий врач события, устанавливаем его как выбранного
        if (window.currentEventEmployee) {
            selector.value = window.currentEventEmployee.ID;
        }
    }

    window.saveEmployee = function() {
        const employeeId = document.getElementById('employee-select').value;
        if (!employeeId) {
            showNotification('Выберите врача', 'error');
            return;
        }

        if (!window.currentEventId) {
            showNotification('Ошибка: не найдено событие', 'error');
            return;
        }

        // Сначала получаем данные о текущем событии для проверки времени
        const csrfToken = getCSRFToken();
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
            },
            body: new URLSearchParams({
                action: 'getEvent',
                eventId: window.currentEventId,
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
                    excludeEventId: window.currentEventId,
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
                    eventId: window.currentEventId,
                    employee_id: employeeId,
                    sessid: csrfToken
                })
            });
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Врач назначен', 'success');
                closeEmployeeModal();
                // Обновляем отображение в боковой панели
                updateEmployeeCard(employeeId);
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
    }

    function updateEmployeeCard(employeeId, employeeName) {
        const statusElement = document.getElementById('employee-status');
        if (statusElement) {
            if (employeeName) {
                // Если имя передано напрямую, используем его
                statusElement.textContent = employeeName;
            } else {
                // Иначе загружаем данные о враче через AJAX
                const csrfToken = getCSRFToken();
                fetch('/local/components/artmax/calendar/ajax.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-Bitrix-Csrf-Token': csrfToken
                    },
                    body: new URLSearchParams({
                        action: 'getEmployees',
                        sessid: csrfToken
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.employees) {
                        const employee = data.employees.find(emp => emp.ID === employeeId);
                        if (employee) {
                            statusElement.textContent = `${employee.NAME} ${employee.LAST_NAME}`.trim() || employee.LOGIN;
                        }
                    }
                })
                .catch(error => {
                    console.error('Ошибка при получении данных врача:', error);
                });
            }
        }
    }

    window.openEmployeeDetails = function() {
        // Пока что просто открываем модальное окно для выбора врача
        openEmployeeModal();
    }

    // Загрузка данных врача для боковой панели
    function loadEventEmployee(employeeId) {
        console.log('loadEventEmployee: Загружаем врача с ID:', employeeId);
        const csrfToken = getCSRFToken();
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
            },
            body: new URLSearchParams({
                action: 'getEmployees',
                sessid: csrfToken
            })
        })
        .then(response => {
            console.log('loadEventEmployee: Получен ответ от сервера');
            return response.json();
        })
        .then(data => {
            console.log('loadEventEmployee: Данные распарсены:', data);
            try {
                if (data.success && data.employees) {
                    console.log('loadEventEmployee: Всего врачей:', data.employees.length);
                    const employee = data.employees.find(emp => String(emp.ID) === String(employeeId));
                    if (employee) {
                        console.log('loadEventEmployee: Врач найден:', employee);
                        updateEmployeeCardInSidePanel(employee);
                    } else {
                        console.log('loadEventEmployee: Врач с ID', employeeId, 'не найден в списке');
                        resetEmployeeInfoInSidePanel();
                    }
                } else {
                    console.log('loadEventEmployee: Ошибка в данных или нет врачей');
                    resetEmployeeInfoInSidePanel();
                }
            } catch (error) {
                console.error('loadEventEmployee: Ошибка при обработке данных врача:', error);
                resetEmployeeInfoInSidePanel();
            }
            
            // Увеличиваем счетчик завершенных загрузок
            window.sidePanelLoadingComplete++;
            checkSidePanelLoadingComplete();
        })
        .catch(error => {
            console.error('loadEventEmployee: ОШИБКА при загрузке данных врача:', error);
            resetEmployeeInfoInSidePanel();
            
            // Увеличиваем счетчик завершенных загрузок даже при ошибке
            window.sidePanelLoadingComplete++;
            checkSidePanelLoadingComplete();
        });
    }

    // Обновление карточки врача в боковой панели
    function updateEmployeeCardInSidePanel(employee) {
        const statusElement = document.getElementById('employee-status');
        if (statusElement) {
            const employeeName = `${employee.NAME} ${employee.LAST_NAME}`.trim() || employee.LOGIN;
            statusElement.textContent = employeeName;
        }
        
        // Сохраняем данные врача для использования в модальном окне
        window.currentEventEmployee = employee;
    }

    // Сброс информации о враче в боковой панели
    function resetEmployeeInfoInSidePanel() {
        const statusElement = document.getElementById('employee-status');
        if (statusElement) {
            statusElement.textContent = 'Не назначен';
        }
        
        // Сбрасываем данные врача
        window.currentEventEmployee = null;
    }

    // Загрузка выбранных сотрудников филиала
    function loadBranchEmployees(branchId) {
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
                branch_id: branchId,
                sessid: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.employees) {
                // Очищаем текущий список выбранных сотрудников
                selectedEmployees = [];
                
                // Добавляем сотрудников филиала в выбранные
                data.employees.forEach(employee => {
                    selectedEmployees.push(employee);
                });
                
                // Обновляем отображение
                updateSelectedEmployeesDisplay();
                
                // Обновляем чекбоксы в выпадающем списке (если он открыт)
                setTimeout(() => {
                    updateEmployeeCheckboxes();
                }, 100);
            }
        })
        .catch(error => {
            console.error('Ошибка при загрузке сотрудников филиала:', error);
        });
    }

    // Обновление чекбоксов сотрудников
    function updateEmployeeCheckboxes() {
        const checkboxes = document.querySelectorAll('.multiselect-option input[type="checkbox"]');
        checkboxes.forEach(checkbox => {
            const employeeId = String(checkbox.value); // Преобразуем в строку
            const isSelected = selectedEmployees.some(emp => emp.ID === employeeId);
            checkbox.checked = isSelected;
            console.log('updateEmployeeCheckboxes: Сотрудник', employeeId, 'выбран:', isSelected);
        });
    }

    function loadEmployees() {
        const csrfToken = getCSRFToken();
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
            },
            body: new URLSearchParams({
                action: 'getEmployees',
                sessid: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.employees) {
                allEmployees = data.employees;
                renderEmployeeOptions(allEmployees);
            } else {
                console.error('Ошибка загрузки сотрудников:', data.error);
            }
        })
        .catch(error => {
            console.error('Ошибка при загрузке сотрудников:', error);
        });
    }

    function searchEmployees(query) {
        const csrfToken = getCSRFToken();
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
            },
            body: new URLSearchParams({
                action: 'searchEmployees',
                query: query,
                sessid: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.employees) {
                renderEmployeeOptions(data.employees);
            } else {
                console.error('Ошибка поиска сотрудников:', data.error);
                // В случае ошибки показываем всех сотрудников
                renderEmployeeOptions(allEmployees);
            }
        })
        .catch(error => {
            console.error('Ошибка при поиске сотрудников:', error);
            // В случае ошибки показываем всех сотрудников
            renderEmployeeOptions(allEmployees);
        });
    }

    function renderEmployeeOptions(employees) {
        const optionsContainer = document.getElementById('multiselect-options');
        if (!optionsContainer) return;

        optionsContainer.innerHTML = '';
        
        employees.forEach(employee => {
            const option = document.createElement('div');
            option.className = 'multiselect-option';
            option.innerHTML = `
                <input type="checkbox" id="emp-${employee.ID}" value="${employee.ID}" 
                       ${selectedEmployees.some(emp => emp.ID === employee.ID) ? 'checked' : ''}>
                <label for="emp-${employee.ID}">${employee.NAME} ${employee.LAST_NAME}</label>
            `;
            
            option.addEventListener('click', (e) => {
                if (e.target.type !== 'checkbox') {
                    const checkbox = option.querySelector('input[type="checkbox"]');
                    checkbox.checked = !checkbox.checked;
                    toggleEmployee(employee, checkbox.checked);
                }
            });
            
            const checkbox = option.querySelector('input[type="checkbox"]');
            checkbox.addEventListener('change', (e) => {
                toggleEmployee(employee, e.target.checked);
            });
            
            optionsContainer.appendChild(option);
        });
    }

    function toggleEmployee(employee, isSelected) {
        if (isSelected) {
            if (!selectedEmployees.some(emp => emp.ID === employee.ID)) {
                selectedEmployees.push(employee);
            }
        } else {
            selectedEmployees = selectedEmployees.filter(emp => emp.ID !== employee.ID);
        }
        updateSelectedEmployeesDisplay();
    }

    function updateSelectedEmployeesDisplay() {
        console.log('updateSelectedEmployeesDisplay: Обновляем отображение, selectedEmployees:', selectedEmployees);
        
        const container = document.getElementById('selected-employees');
        const input = document.getElementById('multiselect-input');
        const placeholder = input ? input.querySelector('.placeholder') : null;
        
        console.log('updateSelectedEmployeesDisplay: container:', container);
        console.log('updateSelectedEmployeesDisplay: placeholder:', placeholder);
        
        if (!container || !placeholder) {
            console.log('updateSelectedEmployeesDisplay: Контейнер или placeholder не найден!');
            return;
        }

        // Очищаем контейнер
        container.innerHTML = '';
        console.log('updateSelectedEmployeesDisplay: Контейнер очищен');
        
        if (selectedEmployees.length > 0) {
            placeholder.textContent = `Выбрано: ${selectedEmployees.length}`;
            console.log('updateSelectedEmployeesDisplay: Создаем теги для', selectedEmployees.length, 'сотрудников');
            
            selectedEmployees.forEach(employee => {
                const tag = document.createElement('div');
                tag.className = 'selected-employee';
                tag.innerHTML = `
                    ${employee.NAME} ${employee.LAST_NAME}
                    <button type="button" class="remove-employee" onclick="removeEmployee(${employee.ID})">×</button>
                `;
                container.appendChild(tag);
                console.log('updateSelectedEmployeesDisplay: Добавлен тег для сотрудника', employee.ID, employee.NAME, employee.LAST_NAME);
            });
        } else {
            placeholder.textContent = 'Выберите сотрудников';
            console.log('updateSelectedEmployeesDisplay: Нет выбранных сотрудников, показываем placeholder');
        }
    }

    function removeEmployee(employeeId) {
        console.log('removeEmployee: Удаляем сотрудника с ID:', employeeId, 'тип:', typeof employeeId);
        console.log('removeEmployee: До удаления selectedEmployees:', selectedEmployees);
        
        // Преобразуем employeeId в строку для корректного сравнения
        const stringEmployeeId = String(employeeId);
        console.log('removeEmployee: Преобразованное ID в строку:', stringEmployeeId);
        
        // Детальная отладка каждого элемента
        selectedEmployees.forEach((emp, index) => {
            console.log(`removeEmployee: Элемент ${index}: ID=${emp.ID} (тип: ${typeof emp.ID}), сравниваем с ${stringEmployeeId}: ${emp.ID !== stringEmployeeId}`);
        });
        
        // Удаляем сотрудника из массива
        const beforeLength = selectedEmployees.length;
        selectedEmployees = selectedEmployees.filter(emp => {
            const shouldKeep = emp.ID !== stringEmployeeId;
            console.log(`removeEmployee: Сотрудник ${emp.ID} ${shouldKeep ? 'остается' : 'удаляется'}`);
            return shouldKeep;
        });
        
        console.log('removeEmployee: После удаления selectedEmployees:', selectedEmployees);
        console.log('removeEmployee: Было элементов:', beforeLength, 'Стало:', selectedEmployees.length);
        
        // Обновляем визуальное отображение
        updateSelectedEmployeesDisplay();
        
        // Обновляем чекбокс в выпадающем списке
        const checkbox = document.getElementById(`emp-${employeeId}`);
        if (checkbox) {
            checkbox.checked = false;
        }
        
        // Также обновляем все чекбоксы для синхронизации
        updateEmployeeCheckboxes();
    }

    // Обработчики для мультиселектора
    function initMultiselect() {
        const input = document.getElementById('multiselect-input');
        const dropdown = document.getElementById('multiselect-dropdown');
        const searchInput = document.getElementById('employee-search');
        
        if (!input || !dropdown) return;

        // Открытие/закрытие dropdown
        input.addEventListener('click', () => {
            const isActive = input.classList.contains('active');
            if (isActive) {
                input.classList.remove('active');
                dropdown.classList.remove('show');
                setTimeout(() => {
                    dropdown.style.display = 'none';
                }, 300);
            } else {
                input.classList.add('active');
                dropdown.style.display = 'block';
                setTimeout(() => {
                    dropdown.classList.add('show');
                }, 10);
                if (searchInput) {
                    searchInput.focus();
                }
            }
        });

        // Поиск сотрудников
        if (searchInput) {
            let searchTimeout;
            
            // Предотвращаем отправку формы поиска в стандартный Bitrix UI Filter
            searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    e.stopPropagation();
                }
            });
            
            searchInput.addEventListener('input', (e) => {
                const query = e.target.value.trim();
                
                // Очищаем предыдущий таймаут
                if (searchTimeout) {
                    clearTimeout(searchTimeout);
                }
                
                // Если запрос пустой, показываем всех сотрудников
                if (!query) {
                    renderEmployeeOptions(allEmployees);
                    return;
                }
                
                // Делаем поиск с задержкой для оптимизации
                searchTimeout = setTimeout(() => {
                    searchEmployees(query);
                }, 300);
            });
        }

        // Закрытие при клике вне элемента
        document.addEventListener('click', (e) => {
            if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                input.classList.remove('active');
                dropdown.classList.remove('show');
                setTimeout(() => {
                    dropdown.style.display = 'none';
                }, 300);
            }
        });
    }

    // Инициализация обработчиков для модального окна настроек филиала
    function initBranchModal() {
        // Кнопка открытия модального окна
        const branchBtn = document.getElementById('branch-settings-btn');
        if (branchBtn) {
            branchBtn.addEventListener('click', openBranchModal);
        }

        // Кнопка закрытия модального окна
        const closeBtn = document.getElementById('close-branch-modal');
        if (closeBtn) {
            closeBtn.addEventListener('click', closeBranchModal);
        }

        // Кнопка отмены
        const cancelBtn = document.getElementById('cancel-branch-modal');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', closeBranchModal);
        }

        // Обработка отправки формы
        const form = document.getElementById('branch-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                saveBranchSettings();
            });
        }

        // Закрытие модального окна при клике вне его
        const modal = document.getElementById('branchModal');
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeBranchModal();
                }
            });
        }
    }

    function saveBranchSettings() {
        const form = document.getElementById('branch-form');
        const formData = new FormData(form);
        
        // Добавляем action для AJAX обработчика
        formData.append('action', 'saveBranchSettings');
        
        // Добавляем выбранных сотрудников
        const employeeIds = selectedEmployees.map(emp => emp.ID);
        formData.append('employee_ids', JSON.stringify(employeeIds));

        const csrfToken = getCSRFToken();
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Настройки филиала сохранены', 'success');
                
                // Обновляем заголовок страницы, если изменилось название филиала
                const branchNameInput = document.getElementById('branch-name');
                if (branchNameInput && branchNameInput.value) {
                    // Обновляем заголовок в шапке (если есть)
                    const pageTitle = document.querySelector('h1');
                    if (pageTitle) {
                        pageTitle.textContent = 'Календарь - ' + branchNameInput.value;
                    }
                    
                    // Обновляем title страницы
                    document.title = 'Календарь - ' + branchNameInput.value;
                    
                    // Принудительно обновляем страницу для обновления переключателя филиалов
                    showNotification('Название филиала обновлено. Страница будет перезагружена...', 'info');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    closeTimezoneModal();
                }
            } else {
                showNotification('Ошибка сохранения: ' + (data.error || 'Неизвестная ошибка'), 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка при сохранении настроек:', error);
            showNotification('Ошибка соединения с сервером', 'error');
        });
    }

    // Делаем функции глобальными
    window.showCreateContactForm = showCreateContactForm;
    window.hideCreateContactForm = hideCreateContactForm;
    window.createContact = createContact;
    window.openContactDetails = openContactDetails;
    window.openNoteModal = openNoteModal;
    window.closeNoteModal = closeNoteModal;
    window.saveNote = saveNote;
    window.editNote = editNote;
    window.openBranchModal = openBranchModal;
    window.closeBranchModal = closeBranchModal;
    window.removeEmployee = removeEmployee;

    // Функция обновления иконки сделки в календаре
    function updateEventDealIcon(eventId, dealId) {
        const eventElement = document.querySelector(`[data-event-id="${eventId}"]`);
        if (eventElement) {
            const dealIcon = eventElement.querySelector('.deal-icon');
            if (dealIcon) {
                dealIcon.classList.add('active');
            }
        }
    }

    // Функции для работы с модальным окном сделок (перенесено в SidePanel)
    function openDealModal() {
        // Получаем ID текущего события (если есть)
        let eventId = getCurrentEventId();
        
        if (!eventId) {
            showNotification('Ошибка: не удалось определить событие', 'error');
            return;
        }
        
        // Проверяем, привязан ли контакт к событию
        checkEventContactAndOpenDealModal(eventId);
    }
    
    // Функция проверки контакта и открытия формы выбора сделки
    function checkEventContactAndOpenDealModal(eventId) {
        const csrfToken = getCSRFToken();
        
        // Получаем данные события для проверки контакта
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
        .then(data => {
            if (data.success && data.event) {
                const event = data.event;
                
                if (!event.CONTACT_ENTITY_ID) {
                    showNotification('Сначала нужно привязать контакт к событию, а затем можно будет выбрать сделку', 'warning');
                    return;
                }
                
                // Контакт привязан, открываем форму выбора сделки
                const url = `/local/components/artmax/deal.form/page.php?IFRAME=Y&IFRAME_TYPE=SIDE_SLIDER&EVENT_ID=${eventId}`;
                
                if (typeof BX !== 'undefined' && BX.SidePanel) {
                    BX.SidePanel.Instance.open(url, {
                        title: 'Добавить или выбрать сделку',
                        width: 600,
                        cacheable: false,
                        events: {
                            onClose: function() {
                                // Обновляем календарь при необходимости
                                if (typeof refreshCalendarEvents === 'function') {
                                    refreshCalendarEvents();
                                }
                            }
                        }
                    });
                } else {
                    window.open(url, '_blank', 'width=600,height=800,scrollbars=yes,resizable=yes');
                }
            } else {
                showNotification('Ошибка при получении данных события', 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка при проверке контакта:', error);
            showNotification('Ошибка соединения с сервером', 'error');
        });
    }

    window.closeDealModal = function() {
        const modal = document.getElementById('dealModal');
        if (modal) {
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
            document.body.style.overflow = 'auto';
        }
    }

    // Инициализация обработчиков для модального окна сделок
    function initDealModal() {
        const dealInput = document.getElementById('deal-input');
        const dealDropdown = document.getElementById('deal-search-dropdown');
        
        if (dealInput) {
            let searchTimeout;
            
            dealInput.addEventListener('input', function() {
                const query = this.value.trim();
                
                // Очищаем предыдущий таймер
                clearTimeout(searchTimeout);
                
                if (query.length > 0) {
                    updateDealSearchText(query);
                    showDealDropdown();
                    
                    // Запускаем поиск с задержкой 300мс
                    if (query.length >= 2) {
                        searchTimeout = setTimeout(() => {
                            searchDealsInBitrix24(query);
                        }, 300);
                    }
                } else {
                    hideDealDropdown();
                }
            });
            
            // Обработчик фокуса
            dealInput.addEventListener('focus', function() {
                const query = this.value.trim();
                if (query.length > 0) {
                    showDealDropdown();
                }
            });
        }
    }

    // Функция обновления текста поиска сделок
    function updateDealSearchText(query) {
        const searchTextElement = document.querySelector('#deal-search-dropdown .search-text');
        if (searchTextElement) {
            searchTextElement.textContent = `«${query}»`;
        }
    }

    // Функция сохранения данных сделки
    window.saveDealData = function() {
        const dealInput = document.getElementById('deal-input');
        const dealIdInput = document.getElementById('deal-id');
        
        if (!dealInput || !dealInput.value.trim()) {
            showNotification('Введите название сделки', 'error');
            return;
        }
        
        const dealData = {
            id: dealIdInput.value || null,
            title: dealInput.value.trim()
        };
        
        // Получаем ID текущего события из бокового окна
        const eventId = getCurrentEventId();
        if (!eventId) {
            showNotification('Ошибка: не удалось определить событие', 'error');
            return;
        }
        
        // Отправляем данные на сервер
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'saveEventDeal',
                eventId: eventId,
                dealData: JSON.stringify(dealData)
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Сделка успешно сохранена', 'success');
                closeDealModal();
                
                // Обновляем информацию о сделке в боковом окне
                updateDealInfoInSidePanel(dealData);
            } else {
                showNotification('Ошибка сохранения: ' + (data.error || 'Неизвестная ошибка'), 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка при сохранении сделки:', error);
            showNotification('Ошибка соединения с сервером', 'error');
        });
    }

    // Функция обновления информации о сделке в боковом окне
    function updateDealInfoInSidePanel(deal) {
        console.log('updateDealInfoInSidePanel: Вызвана с данными:', deal);
        
        const dealStatus = document.getElementById('deal-status');
        console.log('updateDealInfoInSidePanel: deal-status элемент найден?', !!dealStatus);
        
        if (dealStatus) {
            const oldText = dealStatus.textContent;
            dealStatus.textContent = deal.title;
            dealStatus.style.color = '#28a745';
            console.log('updateDealInfoInSidePanel: Обновлен deal-status с "' + oldText + '" на "' + deal.title + '"');
        } else {
            console.error('updateDealInfoInSidePanel: Элемент deal-status не найден!');
        }
        
        // Добавляем класс --confirmed к иконке в deal-card
        const dealCard = document.getElementById('deal-card');
        if (dealCard) {
            const iconElement = dealCard.querySelector('.card-icon > .booking-actions-popup-item-icon');
            if (iconElement) {
                iconElement.classList.add('--confirmed');
                console.log('updateDealInfoInSidePanel: Добавлен класс --confirmed к иконке сделки');
            }
        }
        
        // Обновляем иконку сделки только для текущего события
        if (window.currentEventId) {
            const eventElement = document.querySelector(`[data-event-id="${window.currentEventId}"]`);
            if (eventElement) {
                const dealIcon = eventElement.querySelector('.deal-icon');
                if (dealIcon) {
                    dealIcon.classList.add('active');
                    console.log('updateDealInfoInSidePanel: Обновлена иконка сделки в календаре');
                }
            }
        }
    }

    // Делаем функции доступными глобально для использования в HTML
    window.closeEditEventModal = closeEditEventModal;
    window.closeEventForm = closeEventForm;
    window.openEventForm = openEventForm;
    window.openClientModal = openClientModal;
    window.closeClientModal = closeClientModal;
    window.openDealModal = openDealModal;
    window.closeDealModal = closeDealModal;
    window.openEditEventModal = openEditEventModal;
    window.openScheduleModal = openScheduleModal;
    window.closeScheduleModal = closeScheduleModal;
    window.openAddBranchModal = openAddBranchModal;
    window.closeAddBranchModal = closeAddBranchModal;
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
    window.clearAllEvents = clearAllEvents;
    window.showEventSidePanel = showEventSidePanel;
    window.closeEventSidePanel = closeEventSidePanel;
    window.openEditEventModalFromSidePanel = openEditEventModalFromSidePanel;
    window.deleteEventFromSidePanel = deleteEventFromSidePanel;

    // Функции для работы с выпадающим меню подтверждения
    function toggleConfirmationDropdown() {
        const dropdown = document.getElementById('confirmation-dropdown');
        if (!dropdown) return;
        
        // Закрываем все другие выпадающие меню
        closeAllDropdowns();
        
        // Переключаем текущее меню
        dropdown.classList.toggle('show');
        
        // Управляем z-index родительского action-card
        const actionCard = dropdown.closest('.action-card');
        if (actionCard) {
            if (dropdown.classList.contains('show')) {
                actionCard.classList.add('dropdown-open');
            } else {
                actionCard.classList.remove('dropdown-open');
            }
        }
    }

    function closeAllDropdowns() {
        const dropdowns = document.querySelectorAll('.confirmation-dropdown, .visit-dropdown');
        dropdowns.forEach(dropdown => {
            dropdown.classList.remove('show');
            // Удаляем класс dropdown-open с родительского action-card
            const actionCard = dropdown.closest('.action-card');
            if (actionCard) {
                actionCard.classList.remove('dropdown-open');
            }
        });
    }

    function setConfirmationStatus(status) {
        const statusElement = document.getElementById('confirmation-status');
        const dropdown = document.getElementById('confirmation-dropdown');
        
        if (!statusElement || !dropdown) return;
        
        // Находим action-card для подтверждения
        const actionCard = dropdown.closest('.action-card');
        const iconElement = actionCard ? actionCard.querySelector('.card-icon > .booking-actions-popup-item-icon') : null;
        
        // Обновляем текст статуса
        if (status === 'confirmed') {
            statusElement.textContent = 'Подтверждено';
            statusElement.classList.add('confirmed');
            // Добавляем класс --confirmed к иконке
            if (iconElement) {
                iconElement.classList.add('--confirmed');
            }
        } else if (status === 'not_confirmed') {
            statusElement.textContent = 'Не подтверждено';
            statusElement.classList.remove('confirmed');
            // Удаляем класс --confirmed с иконки
            if (iconElement) {
                iconElement.classList.remove('--confirmed');
            }
        } else {
            // pending
            statusElement.textContent = 'Ожидается подтверждение';
            statusElement.classList.remove('confirmed');
            // Удаляем класс --confirmed с иконки
            if (iconElement) {
                iconElement.classList.remove('--confirmed');
            }
        }
        
        // Закрываем выпадающее меню
        dropdown.classList.remove('show');
        
        // Удаляем класс dropdown-open с родительского action-card
        if (actionCard) {
            actionCard.classList.remove('dropdown-open');
        }
        
        // Отправляем AJAX запрос для сохранения статуса
        updateEventConfirmationStatus(status);
    }

    function updateEventConfirmationStatus(status) {
        if (!window.currentEventId) {
            console.error('ID события не найден');
            return;
        }
        
        console.log('Отправляем запрос обновления статуса подтверждения:', {
            eventId: window.currentEventId,
            status: status
        });
        
        const params = new URLSearchParams();
        params.append('action', 'update_confirmation_status');
        params.append('event_id', window.currentEventId);
        params.append('confirmation_status', status);
        params.append('sessid', BX.bitrix_sessid());
        
        // Логируем данные
        console.log('Отправляемые параметры:', params.toString());
        
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: params
        })
        .then(response => {
            console.log('Ответ сервера:', response);
            return response.json();
        })
        .then(data => {
            console.log('Данные ответа:', data);
            if (data.success) {
                console.log('Статус подтверждения обновлен:', status);
                updateConfirmationStatusDisplay(status);
            } else {
                console.error('Ошибка при обновлении статуса:', data.message);
                // Возвращаем предыдущий статус в случае ошибки
                revertConfirmationStatus();
            }
        })
        .catch(error => {
            console.error('Ошибка AJAX запроса:', error);
            revertConfirmationStatus();
        });
    }

    function revertConfirmationStatus() {
        const statusElement = document.getElementById('confirmation-status');
        if (statusElement) {
            statusElement.textContent = 'Ожидается подтверждение';
            statusElement.classList.remove('confirmed');
        }
    }

    function loadEventConfirmationStatus(eventId) {
        console.log('loadEventConfirmationStatus: Загружаем статус подтверждения для события:', eventId);
        const params = new URLSearchParams();
        params.append('action', 'get_confirmation_status');
        params.append('event_id', eventId);
        params.append('sessid', BX.bitrix_sessid());
        
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: params
        })
        .then(response => {
            console.log('loadEventConfirmationStatus: Получен ответ от сервера');
            return response.json();
        })
        .then(data => {
            console.log('loadEventConfirmationStatus: Данные распарсены:', data);
            if (data.success) {
                updateConfirmationStatusDisplay(data.confirmation_status);
            } else {
                console.error('loadEventConfirmationStatus: Ошибка при загрузке статуса подтверждения:', data.message);
                // Устанавливаем статус по умолчанию
                updateConfirmationStatusDisplay('pending');
            }
            // Увеличиваем счетчик завершенных загрузок
            window.sidePanelLoadingComplete++;
            // Проверяем завершение загрузки
            checkSidePanelLoadingComplete();
        })
        .catch(error => {
            console.error('loadEventConfirmationStatus: ОШИБКА AJAX запроса при загрузке статуса подтверждения:', error);
            // Устанавливаем статус по умолчанию
            updateConfirmationStatusDisplay('pending');
            // Увеличиваем счетчик завершенных загрузок даже при ошибке
            window.sidePanelLoadingComplete++;
            // Проверяем завершение загрузки даже при ошибке
            checkSidePanelLoadingComplete();
        });
    }

    function updateConfirmationStatusDisplay(status) {
        const statusElement = document.getElementById('confirmation-status');
        const confirmationDropdown = document.getElementById('confirmation-dropdown');
        
        if (!statusElement) return;
        
        // Находим action-card для подтверждения и иконку внутри него
        const actionCard = confirmationDropdown ? confirmationDropdown.closest('.action-card') : null;
        const iconElement = actionCard ? actionCard.querySelector('.card-icon > .booking-actions-popup-item-icon') : null;
        
        switch (status) {
            case 'confirmed':
                statusElement.textContent = 'Подтверждено';
                statusElement.classList.add('confirmed');
                // Добавляем класс --confirmed к иконке
                if (iconElement) {
                    iconElement.classList.add('--confirmed');
                }
                break;
            case 'not_confirmed':
                statusElement.textContent = 'Не подтверждено';
                statusElement.classList.remove('confirmed');
                // Удаляем класс --confirmed с иконки
                if (iconElement) {
                    iconElement.classList.remove('--confirmed');
                }
                break;
            case 'pending':
            default:
                statusElement.textContent = 'Ожидается подтверждение';
                statusElement.classList.remove('confirmed');
                // Удаляем класс --confirmed с иконки
                if (iconElement) {
                    iconElement.classList.remove('--confirmed');
                }
                break;
        }
        
        // Обновляем иконку в календаре
        updateEventIconInCalendar('confirmation', status);
    }

    // Функции для выпадающего меню визита
    function toggleVisitDropdown() {
        const dropdown = document.getElementById('visit-dropdown');
        const actionCard = dropdown.closest('.action-card');
        
        // Закрываем все другие выпадающие меню
        closeAllDropdowns();
        
        if (dropdown.classList.contains('show')) {
            dropdown.classList.remove('show');
            actionCard.classList.remove('dropdown-open');
        } else {
            dropdown.classList.add('show');
            actionCard.classList.add('dropdown-open');
        }
    }

    function setVisitStatus(status) {
        const statusElement = document.getElementById('visit-status');
        const dropdown = document.getElementById('visit-dropdown');
        const actionCard = dropdown.closest('.action-card');
        
        // Находим иконку внутри action-card для визита
        const iconElement = actionCard ? actionCard.querySelector('.card-icon > .booking-actions-popup-item-icon') : null;
        
        // Обновляем текст статуса
        switch (status) {
            case 'not_specified':
                statusElement.textContent = 'Не указано';
                statusElement.classList.remove('came', 'did-not-come');
                // Удаляем класс --confirmed с иконки (если был)
                if (iconElement) {
                    iconElement.classList.remove('--confirmed');
                }
                break;
            case 'client_came':
                statusElement.textContent = 'Клиент пришел';
                statusElement.classList.remove('did-not-come');
                statusElement.classList.add('came');
                // Добавляем класс --confirmed к иконке
                if (iconElement) {
                    iconElement.classList.add('--confirmed');
                }
                break;
            case 'client_did_not_come':
                statusElement.textContent = 'Клиент не пришел';
                statusElement.classList.remove('came');
                statusElement.classList.add('did-not-come');
                // Удаляем класс --confirmed с иконки
                if (iconElement) {
                    iconElement.classList.remove('--confirmed');
                }
                break;
        }
        
        // Скрываем выпадающее меню
        dropdown.classList.remove('show');
        actionCard.classList.remove('dropdown-open');
        
        // Отправляем AJAX запрос для обновления статуса
        updateEventVisitStatus(status);
    }

    function updateEventVisitStatus(status) {
        if (!window.currentEventId) {
            console.error('ID события не найден');
            return;
        }
        
        console.log('Отправляем запрос обновления статуса визита:', {
            eventId: window.currentEventId,
            status: status
        });
        
        const params = new URLSearchParams();
        params.append('action', 'update_visit_status');
        params.append('event_id', window.currentEventId);
        params.append('visit_status', status);
        params.append('sessid', BX.bitrix_sessid());
        
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: params
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Статус визита обновлен:', status);
                // Обновляем отображение статуса визита (включая класс --confirmed)
                updateVisitStatusDisplay(status);
            } else {
                console.error('Ошибка при обновлении статуса визита:', data.message);
            }
        })
        .catch(error => {
            console.error('Ошибка AJAX запроса:', error);
        });
    }

    function loadEventVisitStatus(eventId) {
        console.log('loadEventVisitStatus: Загружаем статус визита для события:', eventId);
        const params = new URLSearchParams();
        params.append('action', 'get_visit_status');
        params.append('event_id', eventId);
        params.append('sessid', BX.bitrix_sessid());
        
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: params
        })
        .then(response => {
            console.log('loadEventVisitStatus: Получен ответ от сервера');
            return response.json();
        })
        .then(data => {
            console.log('loadEventVisitStatus: Данные распарсены:', data);
            if (data.success) {
                updateVisitStatusDisplay(data.visit_status || 'not_specified');
            } else {
                console.error('loadEventVisitStatus: Ошибка при загрузке статуса визита:', data.message);
                updateVisitStatusDisplay('not_specified');
            }
            // Увеличиваем счетчик завершенных загрузок
            window.sidePanelLoadingComplete++;
            // Проверяем завершение загрузки
            checkSidePanelLoadingComplete();
        })
        .catch(error => {
            console.error('loadEventVisitStatus: ОШИБКА AJAX запроса:', error);
            updateVisitStatusDisplay('not_specified');
            // Увеличиваем счетчик завершенных загрузок даже при ошибке
            window.sidePanelLoadingComplete++;
            // Проверяем завершение загрузки даже при ошибке
            checkSidePanelLoadingComplete();
        });
    }

    function updateVisitStatusDisplay(status) {
        const statusElement = document.getElementById('visit-status');
        const visitDropdown = document.getElementById('visit-dropdown');
        
        if (!statusElement) return;
        
        // Находим action-card для визита и иконку внутри него
        const actionCard = visitDropdown ? visitDropdown.closest('.action-card') : null;
        const iconElement = actionCard ? actionCard.querySelector('.card-icon > .booking-actions-popup-item-icon') : null;
        
        switch (status) {
            case 'not_specified':
                statusElement.textContent = 'Не указано';
                statusElement.classList.remove('came', 'did-not-come');
                // Удаляем класс --confirmed с иконки (если был)
                if (iconElement) {
                    iconElement.classList.remove('--confirmed');
                }
                break;
            case 'client_came':
                statusElement.textContent = 'Клиент пришел';
                statusElement.classList.remove('did-not-come');
                statusElement.classList.add('came');
                // Добавляем класс --confirmed к иконке
                if (iconElement) {
                    iconElement.classList.add('--confirmed');
                }
                break;
            case 'client_did_not_come':
                statusElement.textContent = 'Клиент не пришел';
                statusElement.classList.remove('came');
                statusElement.classList.add('did-not-come');
                // Удаляем класс --confirmed с иконки
                if (iconElement) {
                    iconElement.classList.remove('--confirmed');
                }
                break;
        }
        
        // Обновляем иконку в календаре
        updateEventIconInCalendar('visit', status);
    }

    // Вспомогательные функции для определения классов иконок
    function getConfirmationIconClass(status) {
        if (status === 'confirmed') {
            return 'active';
        } else if (status === 'not_confirmed') {
            return 'inactive';
        }
        return '';
    }

    function getVisitIconClass(status) {
        if (status === 'client_came') {
            return 'active';
        } else if (status === 'client_did_not_come') {
            return 'inactive';
        }
        return '';
    }

    // Функция для обновления иконок событий в календаре
    function updateEventIconInCalendar(type, status) {
        if (!window.currentEventId) return;
        
        const eventElement = document.querySelector(`[data-event-id="${window.currentEventId}"]`);
        if (!eventElement) return;
        
        const iconElement = eventElement.querySelector(`.event-icon.${type}-icon`);
        if (!iconElement) return;
        
        // Удаляем все классы состояний
        iconElement.classList.remove('active', 'inactive');
        
        // Добавляем соответствующий класс в зависимости от типа и статуса
        if (type === 'confirmation') {
            if (status === 'confirmed') {
                iconElement.classList.add('active');
            } else if (status === 'not_confirmed') {
                iconElement.classList.add('inactive');
            }
        } else if (type === 'visit') {
            if (status === 'client_came') {
                iconElement.classList.add('active');
            } else if (status === 'client_did_not_come') {
                iconElement.classList.add('inactive');
            }
        }
    }

    // Функции для управления прелоадером боковой панели
    function showSidePanelPreloader() {
        const preloader = document.getElementById('sidePanelPreloader');
        if (preloader) {
            preloader.classList.remove('hidden');
        }
    }

    function hideSidePanelPreloader() {
        const preloader = document.getElementById('sidePanelPreloader');
        if (preloader) {
            preloader.classList.add('hidden');
        }
    }

    // Функция для проверки завершения всех загрузок
    function checkSidePanelLoadingComplete() {
        console.log(`Загрузка завершена: ${window.sidePanelLoadingComplete}/${window.sidePanelLoadingCount}`);
        
        if (window.sidePanelLoadingComplete >= window.sidePanelLoadingCount) {
            console.log('Все загрузки завершены, скрываем прелоадер');
            hideSidePanelPreloader();
        }
    }

    // Экспортируем функции в глобальную область
    window.toggleConfirmationDropdown = toggleConfirmationDropdown;
    window.setConfirmationStatus = setConfirmationStatus;
    window.toggleVisitDropdown = toggleVisitDropdown;
    window.setVisitStatus = setVisitStatus;

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
            // Проверяем, если дата в российском формате "01.09.2025 09:00:00"
            if (eventData.dateFrom.match(/^\d{1,2}\.\d{1,2}\.\d{4}\s+\d{1,2}:\d{1,2}:\d{1,2}$/)) {
                const [datePart, timePart] = eventData.dateFrom.split(' ');
                const [day, month, year] = datePart.split('.').map(Number);
                const [hours, minutes, seconds] = timePart.split(':').map(Number);
                // Создаем Date в локальном времени (month - 1, так как в JS месяцы начинаются с 0)
                dateFrom = new Date(year, month - 1, day, hours, minutes, seconds);
                console.log('addEventToCalendar: Российский формат даты обработан:', eventData.dateFrom, '->', dateFrom);
            } else if (eventData.dateFrom.match(/^\d{4}-\d{2}-\d{2}\s+\d{1,2}:\d{1,2}:\d{1,2}$/)) {
                // Если дата в формате "2025-08-04 12:00:00", парсим компоненты отдельно
                const [datePart, timePart] = eventData.dateFrom.split(' ');
                const [year, month, day] = datePart.split('-').map(Number);
                const [hours, minutes, seconds] = timePart.split(':').map(Number);
                // Создаем Date в локальном времени (month - 1, так как в JS месяцы начинаются с 0)
                dateFrom = new Date(year, month - 1, day, hours, minutes, seconds);
                console.log('addEventToCalendar: Стандартный формат даты обработан:', eventData.dateFrom, '->', dateFrom);
            } else {
                // Если дата в ISO формате
                dateFrom = new Date(eventData.dateFrom);
                console.log('addEventToCalendar: ISO формат даты обработан:', eventData.dateFrom, '->', dateFrom);
            }
        } else {
            // Если дата в ISO формате
            dateFrom = new Date(eventData.dateFrom);
            console.log('addEventToCalendar: ISO формат даты (fallback):', eventData.dateFrom, '->', dateFrom);
        }
        
        // Получаем ключ даты в формате YYYY-MM-DD
        let dateKey;
        if (typeof eventData.dateFrom === 'string' && eventData.dateFrom.includes(' ')) {
            // Проверяем, если дата в российском формате "01.09.2025 09:00:00"
            if (eventData.dateFrom.match(/^\d{1,2}\.\d{1,2}\.\d{4}\s+\d{1,2}:\d{1,2}:\d{1,2}$/)) {
                const [datePart] = eventData.dateFrom.split(' ');
                const [day, month, year] = datePart.split('.');
                dateKey = `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
                console.log('addEventToCalendar: Российский формат dateKey:', eventData.dateFrom, '->', dateKey);
            } else if (eventData.dateFrom.match(/^\d{4}-\d{2}-\d{2}\s+\d{1,2}:\d{1,2}:\d{1,2}$/)) {
                // Если дата в формате "2025-08-04 12:00:00"
                dateKey = eventData.dateFrom.split(' ')[0];
                console.log('addEventToCalendar: Стандартный формат dateKey:', eventData.dateFrom, '->', dateKey);
            } else {
                // Если дата в ISO формате, извлекаем дату без конвертации
                dateKey = eventData.dateFrom.split('T')[0];
                console.log('addEventToCalendar: ISO формат dateKey:', eventData.dateFrom, '->', dateKey);
            }
        } else {
            // Если дата в ISO формате, извлекаем дату без конвертации
            dateKey = eventData.dateFrom.split('T')[0];
            console.log('addEventToCalendar: ISO формат dateKey (fallback):', eventData.dateFrom, '->', dateKey);
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
            // Проверяем, если дата в российском формате "01.09.2025 09:00:00"
            if (eventData.dateFrom.match(/^\d{1,2}\.\d{1,2}\.\d{4}\s+\d{1,2}:\d{1,2}:\d{1,2}$/)) {
                const timeMatch = eventData.dateFrom.match(/(\d{1,2}):(\d{1,2}):(\d{1,2})$/);
                if (timeMatch) {
                    timeString = `${timeMatch[1].padStart(2, '0')}:${timeMatch[2].padStart(2, '0')}`;
                    console.log('addEventToCalendar: Время извлечено из российского формата:', timeString);
                }
            } else if (eventData.dateFrom.match(/^\d{4}-\d{2}-\d{2}\s+\d{1,2}:\d{1,2}:\d{1,2}$/)) {
                // Если дата в формате "2025-08-04 12:00:00", извлекаем время напрямую
                const timeMatch = eventData.dateFrom.match(/(\d{2}):(\d{2}):(\d{2})$/);
                if (timeMatch) {
                    timeString = `${timeMatch[1]}:${timeMatch[2]}`;
                    console.log('addEventToCalendar: Время извлечено из стандартного формата:', timeString);
                }
            } else {
                // Если дата в ISO формате (с T), извлекаем время
                const isoTimeMatch = eventData.dateFrom.match(/T(\d{2}):(\d{2}):/);
                if (isoTimeMatch) {
                    timeString = `${isoTimeMatch[1]}:${isoTimeMatch[2]}`;
                    console.log('addEventToCalendar: Время извлечено из ISO формата:', timeString);
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
        
        // Получаем время окончания
        let endTimeString;
        if (typeof eventData.dateTo === 'string') {
            // Проверяем, если дата в российском формате "01.09.2025 09:00:00"
            if (eventData.dateTo.match(/^\d{1,2}\.\d{1,2}\.\d{4}\s+\d{1,2}:\d{1,2}:\d{1,2}$/)) {
                const endTimeMatch = eventData.dateTo.match(/(\d{1,2}):(\d{1,2}):(\d{1,2})$/);
                if (endTimeMatch) {
                    endTimeString = `${endTimeMatch[1].padStart(2, '0')}:${endTimeMatch[2].padStart(2, '0')}`;
                }
            } else if (eventData.dateTo.match(/^\d{4}-\d{2}-\d{2}\s+\d{1,2}:\d{1,2}:\d{1,2}$/)) {
                // Если дата в формате "2025-08-04 12:00:00", извлекаем время напрямую
                const endTimeMatch = eventData.dateTo.match(/(\d{2}):(\d{2}):(\d{2})$/);
                if (endTimeMatch) {
                    endTimeString = `${endTimeMatch[1]}:${endTimeMatch[2]}`;
                }
            } else {
                // Если дата в ISO формате (с T), извлекаем время
                const isoEndTimeMatch = eventData.dateTo.match(/T(\d{2}):(\d{2}):/);
                if (isoEndTimeMatch) {
                    endTimeString = `${isoEndTimeMatch[1]}:${isoEndTimeMatch[2]}`;
                } else {
                    // Fallback на локальное время
                    endTimeString = new Date(eventData.dateTo).toLocaleTimeString('ru-RU', { 
                        hour: '2-digit', 
                        minute: '2-digit',
                        hour12: false
                    });
                }
            }
        } else {
            // Fallback на локальное время
            endTimeString = new Date(eventData.dateTo).toLocaleTimeString('ru-RU', { 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: false
            });
        }

        // Формируем заголовок: Название - Имя - Телефон
        let eventTitle = eventData.title || '';
        if (eventData.contactName) {
            eventTitle += ' - ' + eventData.contactName;
        }
        if (eventData.contactPhone) {
            eventTitle += ' - ' + eventData.contactPhone;
        }
        
        eventElement.innerHTML = `
            <div class="event-content">
                <div class="event-title">${eventTitle}</div>
                <div class="event-time"><span>${timeString} – ${endTimeString}</span><div class="event-icons">
                        <span class="event-icon contact-icon ${eventData.contactEntityId ? 'active' : ''}" title="Контакт">👤</span>
                        <span class="event-icon deal-icon ${getDealIconClass(eventData.dealEntityId)}" title="Сделка">💼</span>
                        <span class="event-icon visit-icon ${getVisitIconClass(eventData.visitStatus)}" title="Визит">🏥</span>
                        <span class="event-icon confirmation-icon ${getConfirmationIconClass(eventData.confirmationStatus)}" title="Подтверждение">✅</span>
                    </div></div>
            </div>
            <div class="event-arrow">▼</div>
        `;
        
        // Добавляем обработчик клика по событию для предотвращения всплытия
        eventElement.addEventListener('click', function(e) {
            e.stopPropagation();
        });
        
        // Добавляем обработчик клика на стрелочку для вызова бокового окна
        const arrowElement = eventElement.querySelector('.event-arrow');
        if (arrowElement) {
            arrowElement.addEventListener('click', function(e) {
                e.stopPropagation();
                showEventSidePanel(eventData.id);
            });
        }
        
        // Добавляем событие в ячейку календаря
        calendarDay.appendChild(eventElement);
        
        // Сортируем события в дне сразу после добавления
        sortEventsInDay(calendarDay);
        
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
                const color = (typeof eventData.eventColor === 'string') ? eventData.eventColor : '#3498db';
                // Конвертируем hex в rgba для эффекта свечения
                const r = parseInt(color.slice(1, 3), 16);
                const g = parseInt(color.slice(3, 5), 16);
                const b = parseInt(color.slice(5, 7), 16);
                eventElement.style.boxShadow = `0 0 20px rgba(${r}, ${g}, ${b}, 0.8)`;
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
        
        // Сначала пытаемся обновить глобальные переменные из URL
        updateGlobalDateFromURL();
        
        // Получаем текущий месяц и год из глобальных переменных
        const year = window.currentYear || new Date().getFullYear();
        const month = window.currentMonth || (new Date().getMonth() + 1);
        
        console.log('refreshCalendarEvents: Используем глобальные переменные - year =', year, 'month =', month);
        console.log('refreshCalendarEvents: window.currentYear =', window.currentYear, 'window.currentMonth =', window.currentMonth);
        
        // Формируем диапазон дат для календарной сетки (включая дни предыдущего и следующего месяца)
        // ВНИМАНИЕ: month в JavaScript это 1-12, но new Date() ожидает 0-11!
        const firstDay = new Date(year, month - 1, 1); // month-1 потому что new Date() ожидает 0-11
        const firstDayOfWeek = firstDay.getDay() === 0 ? 7 : firstDay.getDay(); // Преобразуем воскресенье (0) в 7
        
        // Начинаем с понедельника предыдущей недели
        const startDate = new Date(firstDay);
        startDate.setDate(startDate.getDate() - (firstDayOfWeek - 1));
        
        // Заканчиваем через 6 недель (42 дня)
        const endDate = new Date(startDate);
        endDate.setDate(endDate.getDate() + 41);
        
        const dateFrom = startDate.toISOString().split('T')[0];
        const dateTo = endDate.toISOString().split('T')[0];
        
        console.log('refreshCalendarEvents: Сформированный диапазон дат - dateFrom =', dateFrom, 'dateTo =', dateTo);
        console.log('refreshCalendarEvents: startDate =', startDate.toISOString(), 'endDate =', endDate.toISOString());
        
        const csrfToken = getCSRFToken();
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
            },
            body: new URLSearchParams({
                action: 'getEvents',
                branchId: branchId,
                dateFrom: dateFrom,
                dateTo: dateTo,
                sessid: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log('DYNAMIC LOAD: Server response:', data);
            if (data.success && data.events) {
                console.log('DYNAMIC LOAD: Events received:', data.events.length);
                updateCalendarEvents(data.events);
            } else {
                console.error('DYNAMIC LOAD: Error in response:', data);
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
            
            // Получаем ключ даты, конвертируя российский формат в стандартный
            let dateKey;
            if (typeof event.DATE_FROM === 'string' && event.DATE_FROM.includes(' ')) {
                const datePart = event.DATE_FROM.split(' ')[0];
                // Проверяем, если дата в российском формате "01.09.2025"
                if (datePart.match(/^\d{1,2}\.\d{1,2}\.\d{4}$/)) {
                    const [day, month, year] = datePart.split('.');
                    dateKey = `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
                    console.log('updateCalendarEvents: Российский формат конвертирован:', datePart, '->', dateKey);
                } else {
                    // Если дата в стандартном формате "2025-08-04"
                    dateKey = datePart;
                    console.log('updateCalendarEvents: Стандартный формат:', dateKey);
                }
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
                // Сортируем события по времени начала
                eventsByDate[dateKey].sort((a, b) => {
                    const timeA = a.DATE_FROM || '';
                    const timeB = b.DATE_FROM || '';
                    
                    // Извлекаем время из даты
                    const timeMatchA = timeA.match(/(\d{2}):(\d{2}):(\d{2})$/);
                    const timeMatchB = timeB.match(/(\d{2}):(\d{2}):(\d{2})$/);
                    
                    if (timeMatchA && timeMatchB) {
                        const timeStrA = `${timeMatchA[1]}:${timeMatchA[2]}`;
                        const timeStrB = `${timeMatchB[1]}:${timeMatchB[2]}`;
                        return timeStrA.localeCompare(timeStrB);
                    }
                    
                    return 0;
                });
                
                eventsByDate[dateKey].forEach(event => {
                    const eventElement = createEventElement(event);
                    calendarDay.appendChild(eventElement);
                });
                
                // Сортируем события в дне после добавления всех событий
                sortEventsInDay(calendarDay);
                
                eventsByDate[dateKey].forEach((event, index) => {
                    const eventElement = calendarDay.querySelectorAll('.calendar-event')[index];
                    
                    // Анимация появления
                    eventElement.style.opacity = '0';
                    eventElement.style.transform = 'scale(0.8)';
                    
                    // Плавное появление
                    setTimeout(() => {
                        eventElement.style.transition = 'all 0.3s ease-out';
                        eventElement.style.opacity = '1';
                        eventElement.style.transform = 'scale(1)';
                        
                        // Убираем временные стили после анимации и восстанавливаем оригинальный цвет
                        setTimeout(() => {
                            eventElement.style.transition = '';
                            eventElement.style.opacity = '';
                            eventElement.style.transform = '';
                            
                            // Восстанавливаем оригинальный цвет фона с 40% прозрачностью
                            const originalColor = eventElement.getAttribute('data-original-color');
                            if (originalColor) {
                                // Конвертируем hex в RGB и добавляем 40% прозрачность
                                const hex = originalColor.replace('#', '');
                                const r = parseInt(hex.substr(0, 2), 16);
                                const g = parseInt(hex.substr(2, 2), 16);
                                const b = parseInt(hex.substr(4, 2), 16);
                                eventElement.style.backgroundColor = `rgba(${r}, ${g}, ${b}, 0.4)`;
                            } else {
                                eventElement.style.backgroundColor = '';
                            }
                        }, 300);
                    }, 50);
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
            if (typeof eventData.dateFrom === 'string') {
                // Проверяем, если дата в российском формате "14.10.2025 09:00:00"
                if (eventData.dateFrom.match(/^\d{1,2}\.\d{1,2}\.\d{4}\s+\d{1,2}:\d{1,2}:\d{1,2}$/)) {
                    const [datePart] = eventData.dateFrom.split(' ');
                    const [day, month, year] = datePart.split('.');
                    newDateKey = `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
                    console.log('updateEventInCalendar: Российский формат преобразован:', eventData.dateFrom, '->', newDateKey);
                } else if (eventData.dateFrom.includes(' ')) {
                    // Если дата в формате "2025-08-04 12:00:00"
                    newDateKey = eventData.dateFrom.split(' ')[0];
                } else {
                    // Если дата в ISO формате, извлекаем дату без конвертации
                    newDateKey = eventData.dateFrom.split('T')[0];
                }
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
                        // Получаем данные о статусе и времени из существующего элемента
                        const currentStatus = eventElement.classList.contains('status-cancelled') ? 'cancelled' :
                                           eventElement.classList.contains('status-moved') ? 'moved' : 'active';
                        const isTimeChanged = eventElement.classList.contains('time-changed') ? 1 : 0;
                        
                        // Удаляем событие со старой позиции
                        eventElement.remove();
                        
                        // Создаем новое событие на новой позиции с полными данными
                        const newEventElement = createEventElement({
                            ID: eventId,
                            TITLE: eventData.title,
                            DESCRIPTION: eventData.description || '',
                            DATE_FROM: eventData.dateFrom,
                            DATE_TO: eventData.dateTo,
                            EVENT_COLOR: eventData.eventColor || '#3498db',
                            STATUS: currentStatus,
                            TIME_IS_CHANGED: isTimeChanged,
                            CONTACT_NAME: eventData.contactName || '',
                            CONTACT_PHONE: eventData.contactPhone || '',
                            CONTACT_ENTITY_ID: eventData.contactEntityId || null,
                            DEAL_ENTITY_ID: eventData.dealEntityId || null,
                            VISIT_STATUS: eventData.visitStatus || 'not_specified',
                            CONFIRMATION_STATUS: eventData.confirmationStatus || 'pending'
                        });
                        
                        // Добавляем событие в новую ячейку
                        newCalendarDay.appendChild(newEventElement);
                        
                        // Сортируем события в новом дне сразу после добавления
                        sortEventsInDay(newCalendarDay);
                        
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
                                    
                                    // Очищаем стили после анимации
                                    setTimeout(() => {
                                        newEventElement.style.transition = '';
                                        newEventElement.style.opacity = '';
                                        newEventElement.style.transform = '';
                                        newEventElement.style.boxShadow = '';
                                    }, 300);
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
                
                // Обновляем заголовок с учетом контакта
                if (titleElement) {
                    let titleText = eventData.title || '';
                    if (eventData.contactName) {
                        titleText += ' - ' + eventData.contactName;
                    }
                    if (eventData.contactPhone) {
                        titleText += ' - ' + eventData.contactPhone;
                    }
                    titleElement.textContent = titleText;
                }
                if (timeElement) {
                    
                    // Форматируем время начала, избегая проблем с часовыми поясами
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
                    
                    // Форматируем время окончания
                    let endTimeString;
                    if (typeof eventData.dateTo === 'string') {
                        // Если дата в формате "2025-08-04 12:00:00", извлекаем время напрямую
                        const endTimeMatch = eventData.dateTo.match(/(\d{2}):(\d{2}):(\d{2})$/);
                        if (endTimeMatch) {
                            endTimeString = `${endTimeMatch[1]}:${endTimeMatch[2]}`;
                        } else {
                            // Если дата в ISO формате (с T), извлекаем время
                            const isoEndTimeMatch = eventData.dateTo.match(/T(\d{2}):(\d{2}):/);
                            if (isoEndTimeMatch) {
                                endTimeString = `${isoEndTimeMatch[1]}:${isoEndTimeMatch[2]}`;
                            } else {
                                // Fallback на локальное время
                                endTimeString = new Date(eventData.dateTo).toLocaleTimeString('ru-RU', { 
                                    hour: '2-digit', 
                                    minute: '2-digit',
                                    hour12: false
                                });
                            }
                        }
                    } else {
                        // Fallback на локальное время
                        endTimeString = new Date(eventData.dateTo).toLocaleTimeString('ru-RU', { 
                            hour: '2-digit', 
                            minute: '2-digit',
                            hour12: false
                        });
                    }
                    
                    // Обновляем только span с временем, сохраняя иконки
                    let timeSpan = timeElement.querySelector('span');
                    if (!timeSpan) {
                        // Если span нет, создаем его
                        timeSpan = document.createElement('span');
                        // Сохраняем существующие иконки, если есть
                        const iconsDiv = timeElement.querySelector('.event-icons');
                        timeElement.textContent = ''; // Очищаем
                        timeElement.appendChild(timeSpan);
                        if (iconsDiv) {
                            timeElement.appendChild(iconsDiv);
                        }
                    }
                    timeSpan.textContent = `${timeString} – ${endTimeString}`;
                }
                
                // Обновляем цвет события
                if (eventData.eventColor) {
                    eventElement.style.borderLeft = `4px solid ${eventData.eventColor}`;
                    eventElement.style.backgroundColor = `${eventData.eventColor}40`;
                    // Сохраняем оригинальный цвет в data-атрибуте
                    eventElement.setAttribute('data-original-color', eventData.eventColor);
                }
                
                // Анимация обновления
                eventElement.style.transition = 'all 0.3s ease';
                eventElement.style.transform = 'scale(1.05)';
                eventElement.style.boxShadow = '0 0 20px rgba(52, 152, 219, 0.6)';
                
                setTimeout(() => {
                    eventElement.style.transform = 'scale(1)';
                    eventElement.style.boxShadow = 'none';
                    
                    // Очищаем стили после анимации
                    setTimeout(() => {
                        eventElement.style.transition = '';
                        eventElement.style.transform = '';
                        eventElement.style.boxShadow = '';
                    }, 300);
                }, 200);
                
                // Сортируем события по времени в том же дне
                sortEventsInDay(currentParent);
            }
        } else {
            console.error('updateEventInCalendar: Событие не найдено в календаре, ID:', eventId);
        }
    }

    /**
     * Сортирует события в дне по времени начала
     */
    function sortEventsInDay(dayElement) {
        if (!dayElement) return;
        
        const events = Array.from(dayElement.querySelectorAll('.calendar-event'));
        if (events.length <= 1) return;
        
        // Сортируем события по времени начала
        events.sort((a, b) => {
            const timeA = a.querySelector('.event-time')?.textContent || '';
            const timeB = b.querySelector('.event-time')?.textContent || '';
            
            // Извлекаем время начала (до "–")
            const startTimeA = timeA.split('–')[0]?.trim() || '';
            const startTimeB = timeB.split('–')[0]?.trim() || '';
            
            // Преобразуем время в минуты для правильного сравнения
            const parseTimeToMinutes = (timeStr) => {
                const [hours, minutes] = timeStr.split(':').map(Number);
                return (hours || 0) * 60 + (minutes || 0);
            };
            
            const minutesA = parseTimeToMinutes(startTimeA);
            const minutesB = parseTimeToMinutes(startTimeB);
            
            // Сортируем по возрастанию времени (раньше время идет первым)
            return minutesA - minutesB;
        });
        // Переставляем события в отсортированном порядке
        events.forEach(event => {
            dayElement.appendChild(event);
        });
    }

    /**
     * Сортирует события по времени во всех днях календаря
     */
    function sortAllEventsInCalendar() {
        const dayElements = document.querySelectorAll('.calendar-day[data-date]');
        
        dayElements.forEach(dayElement => {
            const events = dayElement.querySelectorAll('.calendar-event');
            if (events.length > 1) {
                sortEventsInDay(dayElement);
            }
        });
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
     * Очищает все события из календаря
     */
    function clearAllEvents() {
        if (!confirm('⚠️ ВНИМАНИЕ! Вы уверены, что хотите удалить ВСЕ события из календаря?\n\nЭто действие нельзя отменить!')) {
            return;
        }

        if (!confirm('Последнее предупреждение!\n\nВы действительно хотите удалить ВСЕ события?')) {
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
                action: 'clearAllEvents',
                sessid: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(`Успешно удалено ${data.deletedCount} событий!`, 'success');
                // Очищаем календарь
                document.querySelectorAll('.calendar-event').forEach(event => {
                    event.remove();
                });
                // Закрываем все открытые панели
                closeEventSidePanel();
                closeEditEventModal();
            } else {
                showNotification('Ошибка: ' + (data.error || 'Неизвестная ошибка'), 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка при очистке событий:', error);
            showNotification('Ошибка при очистке событий', 'error');
        });
    }

    /**
     * Получает CSS класс для иконки визита
     */
    function getVisitIconClass(visitStatus) {
        switch (visitStatus) {
            case 'client_came':
                return 'active came';
            case 'client_did_not_come':
                return 'active did-not-come';
            case 'not_specified':
            default:
                return '';
        }
    }

    /**
     * Получает CSS класс для иконки подтверждения
     */
    function getConfirmationIconClass(confirmationStatus) {
        switch (confirmationStatus) {
            case 'confirmed':
                return 'active confirmed';
            case 'not_confirmed':
                return 'active not-confirmed';
            case 'pending':
            default:
                return '';
        }
    }

    /**
     * Получает CSS класс для иконки сделки
     */
    function getDealIconClass(dealEntityId) {
        return dealEntityId ? 'active' : '';
    }

    /**
     * Создает HTML элемент события
     */
    function createEventElement(event) {
        const eventElement = document.createElement('div');
        eventElement.className = 'calendar-event';
        
        // Добавляем класс статуса
        if (event.STATUS) {
            eventElement.classList.add(`status-${event.STATUS}`);
        } else {
            eventElement.classList.add('status-active');
        }
        
        // Добавляем класс для перенесенных записей
        if (event.TIME_IS_CHANGED == 1 || event.TIME_IS_CHANGED === '1') {
            eventElement.classList.add('time-changed');
        }
            
        eventElement.setAttribute('data-event-id', event.ID);
        
        // Применяем цвет события
        if (event.EVENT_COLOR) {
            eventElement.style.borderLeft = `4px solid ${event.EVENT_COLOR}`;
            // Конвертируем hex в RGB и добавляем 40% прозрачность
            const hex = event.EVENT_COLOR.replace('#', '');
            const r = parseInt(hex.substr(0, 2), 16);
            const g = parseInt(hex.substr(2, 2), 16);
            const b = parseInt(hex.substr(4, 2), 16);
            eventElement.style.backgroundColor = `rgba(${r}, ${g}, ${b}, 0.4)`;
            // Сохраняем оригинальный цвет БЕЗ прозрачности в data-атрибуте
            eventElement.setAttribute('data-original-color', event.EVENT_COLOR);
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
        
        // Получаем время окончания
        let endTimeString;
        if (typeof event.DATE_TO === 'string') {
            // Если дата в формате "2025-08-04 12:00:00", извлекаем время напрямую
            const endTimeMatch = event.DATE_TO.match(/(\d{2}):(\d{2}):(\d{2})$/);
            if (endTimeMatch) {
                endTimeString = `${endTimeMatch[1]}:${endTimeMatch[2]}`;
            } else {
                // Если дата в ISO формате (с T), извлекаем время
                const isoEndTimeMatch = event.DATE_TO.match(/T(\d{2}):(\d{2}):/);
                if (isoEndTimeMatch) {
                    endTimeString = `${isoEndTimeMatch[1]}:${isoEndTimeMatch[2]}`;
                } else {
                    // Fallback на локальное время
                    endTimeString = new Date(event.DATE_TO).toLocaleTimeString('ru-RU', { 
                        hour: '2-digit', 
                        minute: '2-digit',
                        hour12: false
                    });
                }
            }
        } else {
            // Fallback на локальное время
            endTimeString = new Date(event.DATE_TO).toLocaleTimeString('ru-RU', { 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: false
            });
        }

        // Формируем заголовок: Название - Имя - Телефон
        let eventTitle = event.TITLE || '';
        if (event.CONTACT_NAME) {
            eventTitle += ' - ' + event.CONTACT_NAME;
        }
        if (event.CONTACT_PHONE) {
            eventTitle += ' - ' + event.CONTACT_PHONE;
        }
        
        eventElement.innerHTML = `
            <div class="event-content">
                <div class="event-title">${eventTitle}</div>
                <div class="event-time"><span>${timeString} – ${endTimeString}</span><div class="event-icons">
                        <span class="event-icon contact-icon ${event.CONTACT_ENTITY_ID ? 'active' : ''}" title="Контакт">👤</span>
                        <span class="event-icon deal-icon ${getDealIconClass(event.DEAL_ENTITY_ID)}" title="Сделка">💼</span>
                        <span class="event-icon visit-icon ${getVisitIconClass(event.VISIT_STATUS)}" title="Визит">🏥</span>
                        <span class="event-icon confirmation-icon ${getConfirmationIconClass(event.CONFIRMATION_STATUS)}" title="Подтверждение">✅</span>
                    </div></div>
            </div>
            <div class="event-arrow">▼</div>
        `;
        
        // Добавляем обработчик клика по событию для предотвращения всплытия
        eventElement.addEventListener('click', function(e) {
            e.stopPropagation();
        });
        
        // Добавляем обработчик клика на стрелочку для вызова бокового окна
        const arrowElement = eventElement.querySelector('.event-arrow');
        if (arrowElement) {
            arrowElement.addEventListener('click', function(e) {
                e.stopPropagation();
                showEventSidePanel(event.ID);
            });
        }
        
        return eventElement;
    }

    // Функция для обновления глобальных переменных из URL
    function updateGlobalDateFromURL() {
        const urlParams = new URLSearchParams(window.location.search);
        const dateParam = urlParams.get('date');
        
        if (dateParam) {
            const urlDate = new Date(dateParam);
            if (!isNaN(urlDate.getTime())) {
                window.currentYear = urlDate.getFullYear();
                window.currentMonth = urlDate.getMonth() + 1;
                console.log('updateGlobalDateFromURL: Обновлены глобальные переменные из URL:', dateParam, 'year:', window.currentYear, 'month:', window.currentMonth);
                return true;
            }
        }
        return false;
    }

    // Функции навигации по календарю
    function previousMonth(event) {
        console.log('=== previousMonth() ВЫЗВАНА ===', event);
        
        // Предотвращаем поведение по умолчанию
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        // Получаем текущий год и месяц из URL или глобальных переменных
        const urlParams = new URLSearchParams(window.location.search);
        const dateParam = urlParams.get('date');
        
        console.log('previousMonth: dateParam из URL:', dateParam);
        console.log('previousMonth: window.currentYear:', window.currentYear, 'window.currentMonth:', window.currentMonth);
        
        let currentYear, currentMonth;
        
        if (dateParam) {
            // Если есть дата в URL, парсим её напрямую (избегаем проблем с часовыми поясами)
            const dateParts = dateParam.split('-');
            console.log('previousMonth: dateParts:', dateParts);
            
            if (dateParts.length === 3) {
                currentYear = parseInt(dateParts[0], 10);
                currentMonth = parseInt(dateParts[1], 10) - 1; // Преобразуем 1-12 в 0-11
                console.log('previousMonth: Парсинг из URL - год:', currentYear, 'месяц (0-11):', currentMonth, 'месяц (1-12):', currentMonth + 1);
            } else {
                // Fallback на Date, если формат не распознан
                const urlDate = new Date(dateParam);
                if (!isNaN(urlDate.getTime())) {
                    currentYear = urlDate.getFullYear();
                    currentMonth = urlDate.getMonth();
                    console.log('previousMonth: Fallback Date - год:', currentYear, 'месяц:', currentMonth);
                } else {
                    const now = new Date();
                    currentYear = now.getFullYear();
                    currentMonth = now.getMonth();
                    console.log('previousMonth: Используем текущую дату - год:', currentYear, 'месяц:', currentMonth);
                }
            }
        } else {
            // Если нет даты в URL, используем глобальные переменные или текущую дату
            const now = new Date();
            currentYear = window.currentYear || now.getFullYear();
            currentMonth = (window.currentMonth || (now.getMonth() + 1)) - 1; // Преобразуем 1-12 в 0-11
            console.log('previousMonth: Нет dateParam, используем глобальные - год:', currentYear, 'месяц (0-11):', currentMonth);
        }
        
        console.log('previousMonth: Текущий месяц (1-12):', currentMonth + 1, 'год:', currentYear);
        
        // Переходим к предыдущему месяцу
        if (currentMonth === 0) {
            currentMonth = 11;
            currentYear--;
            console.log('previousMonth: Переход через год назад - декабрь предыдущего года');
        } else {
            currentMonth--;
            console.log('previousMonth: Уменьшаем месяц на 1');
        }
        
        // Обновляем глобальные переменные
        window.currentYear = currentYear;
        window.currentMonth = currentMonth + 1;
        
        console.log('previousMonth: Новый месяц (1-12):', window.currentMonth, 'год:', window.currentYear);
        
        // Формируем новую дату
        const newDateString = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-01`;
        console.log('previousMonth: Переход на дату:', newDateString);
        
        // Обновляем URL и перезагружаем страницу
        window.location.href = window.location.pathname + '?date=' + newDateString;
    }

    function nextMonth() {
        // Получаем текущий год и месяц из URL или глобальных переменных
        const urlParams = new URLSearchParams(window.location.search);
        const dateParam = urlParams.get('date');
        
        let currentYear, currentMonth;
        
        if (dateParam) {
            // Если есть дата в URL, парсим её напрямую (избегаем проблем с часовыми поясами)
            const dateParts = dateParam.split('-');
            if (dateParts.length === 3) {
                currentYear = parseInt(dateParts[0], 10);
                currentMonth = parseInt(dateParts[1], 10) - 1; // Преобразуем 1-12 в 0-11
            } else {
                // Fallback на Date, если формат не распознан
                const urlDate = new Date(dateParam);
                if (!isNaN(urlDate.getTime())) {
                    currentYear = urlDate.getFullYear();
                    currentMonth = urlDate.getMonth();
                } else {
                    const now = new Date();
                    currentYear = now.getFullYear();
                    currentMonth = now.getMonth();
                }
            }
        } else {
            // Если нет даты в URL, используем глобальные переменные или текущую дату
            const now = new Date();
            currentYear = window.currentYear || now.getFullYear();
            currentMonth = (window.currentMonth || (now.getMonth() + 1)) - 1; // Преобразуем 1-12 в 0-11
        }
        
        console.log('nextMonth: Текущий месяц:', currentMonth + 1, 'год:', currentYear);
        
        // Переходим к следующему месяцу
        if (currentMonth === 11) {
            currentMonth = 0;
            currentYear++;
        } else {
            currentMonth++;
        }
        
        // Обновляем глобальные переменные
        window.currentYear = currentYear;
        window.currentMonth = currentMonth + 1;
        
        console.log('nextMonth: Новый месяц:', window.currentMonth, 'год:', window.currentYear);
        
        // Формируем новую дату
        const newDateString = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-01`;
        console.log('nextMonth: Переход на дату:', newDateString);
        
        // Обновляем URL и перезагружаем страницу
        window.location.href = window.location.pathname + '?date=' + newDateString;
    }

    function goToToday() {
        const today = new Date();
        // Обновляем глобальные переменные
        window.currentYear = today.getFullYear();
        window.currentMonth = today.getMonth() + 1;
        
        console.log('goToToday: Обновлены глобальные переменные - year:', window.currentYear, 'month:', window.currentMonth);
        
        // Форматируем дату в локальном формате, избегая проблем с часовыми поясами
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');
        const todayString = `${year}-${month}-${day}`;
        window.location.href = window.location.pathname + '?date=' + todayString;
    }

    // Функция для обновления занятых временных слотов в форме редактирования
    function updateOccupiedTimeSlots(employeeId, excludeEventId) {
        const dateInput = document.getElementById('edit-event-date');
        const timeSelect = document.getElementById('edit-event-time');
        
        if (!dateInput || !timeSelect) {
            console.error('updateOccupiedTimeSlots: Не найдены поля даты или времени');
            return;
        }
        
        const selectedDate = dateInput.value;
        if (!selectedDate) {
            console.log('updateOccupiedTimeSlots: Дата не выбрана');
            return;
        }
        
        // Получаем занятые слоты с сервера
        const csrfToken = getCSRFToken();
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
            },
            body: new URLSearchParams({
                action: 'getOccupiedTimes',
                date: selectedDate,
                employee_id: employeeId || '',
                excludeEventId: excludeEventId || '',
                sessid: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.occupiedTimes) {
                // Сбрасываем все disabled состояния
                const options = timeSelect.querySelectorAll('option');
                options.forEach(option => {
                    option.disabled = false;
                    option.style.color = '';
                });
                
                // Устанавливаем disabled для занятых слотов
                data.occupiedTimes.forEach(occupiedTime => {
                    const option = timeSelect.querySelector(`option[value="${occupiedTime}"]`);
                    if (option) {
                        option.disabled = true;
                        option.style.color = '#ccc';
                    }
                });
                
                console.log('updateOccupiedTimeSlots: Обновлены занятые слоты:', data.occupiedTimes);
            } else {
                console.error('updateOccupiedTimeSlots: Ошибка получения занятых слотов:', data.error);
            }
        })
        .catch(error => {
            console.error('updateOccupiedTimeSlots: Ошибка запроса:', error);
        });
    }

    // Функция для переноса записи
    function moveEventFromSidePanel() {
        if (!window.currentEventId) {
            showNotification('Ошибка: не найдено событие', 'error');
            return;
        }

        // Получаем данные о текущем событии
        const csrfToken = getCSRFToken();
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
            },
            body: new URLSearchParams({
                action: 'getEvent',
                eventId: window.currentEventId,
                sessid: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                openMoveEventModal(data.event);
            } else {
                showNotification('Ошибка получения данных события', 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка при получении данных события:', error);
            showNotification('Ошибка соединения с сервером', 'error');
        });
    }

    // Функция для переключения статуса записи (отменить/вернуть)
    function toggleEventStatusFromSidePanel() {
        if (!window.currentEventId) {
            showNotification('Ошибка: не найдено событие', 'error');
            return;
        }
        
        // Получаем текущий статус из кнопки
        const cancelBtn = document.getElementById('cancel-event-btn');
        const isCurrentlyCancelled = cancelBtn.textContent.includes('Вернуть');
        
        if (isCurrentlyCancelled) {
            // Возвращаем в расписание
            if (confirm('Вы уверены, что хотите вернуть эту запись в расписание?')) {
                updateEventStatus(window.currentEventId, 'active');
            }
        } else {
            // Отменяем запись
            if (confirm('Вы уверены, что хотите отменить эту запись?')) {
                updateEventStatus(window.currentEventId, 'cancelled');
            }
        }
    }
    
    // Функция для обновления кнопки в зависимости от статуса
    function updateCancelButtonByStatus(status) {
        console.log('updateCancelButtonByStatus: Обновляем кнопку для статуса:', status);
        const cancelBtn = document.getElementById('cancel-event-btn');
        if (!cancelBtn) {
            console.log('updateCancelButtonByStatus: Кнопка не найдена');
            return;
        }
        
        if (status === 'cancelled') {
            console.log('updateCancelButtonByStatus: Устанавливаем кнопку "Вернуть в расписание"');
            cancelBtn.innerHTML = '✅ Вернуть в расписание';
            cancelBtn.className = 'cancel-event-btn return-event-btn';
        } else {
            console.log('updateCancelButtonByStatus: Устанавливаем кнопку "Отменить запись"');
            cancelBtn.innerHTML = '❌ Отменить запись';
            cancelBtn.className = 'cancel-event-btn';
        }
    }

    // Функция для обновления статуса события
    function updateEventStatus(eventId, status) {
        const csrfToken = getCSRFToken();
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
            },
            body: new URLSearchParams({
                action: 'updateEventStatus',
                eventId: eventId,
                status: status,
                sessid: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const statusText = status === 'cancelled' ? 'отменена' : 
                                 status === 'moved' ? 'перенесена' : 'активна';
                showNotification(`Запись ${statusText}`, 'success');
                
                // Обновляем кнопку в боковой панели
                updateCancelButtonByStatus(status);
                
                closeEventSidePanel();
                refreshCalendarEvents();
            } else {
                showNotification('Ошибка обновления статуса: ' + (data.error || 'Неизвестная ошибка'), 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка при обновлении статуса:', error);
            showNotification('Ошибка соединения с сервером', 'error');
        });
    }

    // Функция открытия модального окна переноса записи
    function openMoveEventModal(event) {
        if (!event || !event.ID) {
            showNotification('Ошибка: данные события не найдены', 'error');
            return;
        }
        
        const url = `/local/components/artmax/move.event/page.php?EVENT_ID=${event.ID}&IFRAME=Y&IFRAME_TYPE=SIDE_SLIDER`;
        if (typeof BX !== 'undefined' && BX.SidePanel) {
            BX.SidePanel.Instance.open(url, {
                width: 600,
                cacheable: false,
                allowChangeHistory: false,
                events: {
                    onClose: function() {
                        // Можно добавить логику при закрытии, если нужно
                    }
                }
            });
        } else {
            // Fallback для старых версий
            window.open(url, '_blank', 'width=600,height=400');
        }
    }

    // Функция закрытия модального окна переноса
    function closeMoveEventModal() {
        const modal = document.getElementById('moveEventModal');
        if (modal) {
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }, 300);
        }
    }

    // Функция загрузки филиалов для переноса
    function loadBranchesForMove(currentBranchId, currentEmployeeId = null) {
        const csrfToken = getCSRFToken();
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
            },
            body: new URLSearchParams({
                action: 'getBranches',
                sessid: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateBranchSelectForMove(data.branches, currentBranchId, currentEmployeeId);
            } else {
                console.error('Ошибка загрузки филиалов:', data.error);
                showNotification('Ошибка загрузки филиалов', 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка при загрузке филиалов:', error);
            showNotification('Ошибка соединения с сервером', 'error');
        });
    }

    // Функция заполнения селектора филиалов для переноса
    function populateBranchSelectForMove(branches, currentBranchId, currentEmployeeId = null) {
        const branchSelect = document.getElementById('move-event-branch');
        branchSelect.innerHTML = '<option value="">Выберите филиал</option>';
        
        branches.forEach(branch => {
            const option = document.createElement('option');
            option.value = branch.ID;
            option.textContent = branch.NAME;
            
            // Устанавливаем текущий филиал как выбранный
            if (branch.ID == currentBranchId) {
                option.selected = true;
            }
            
            branchSelect.appendChild(option);
        });
        
        // После загрузки филиалов загружаем врачей для выбранного филиала
        if (currentBranchId) {
            loadEmployeesForMoveByBranch(currentBranchId, currentEmployeeId);
        }
    }

    // Функция загрузки врачей для переноса по филиалу
    function loadEmployeesForMoveByBranch(branchId, currentEmployeeId) {
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
            if (data.success) {
                populateEmployeeSelectForMove(data.employees, currentEmployeeId);
            } else {
                console.error('Ошибка загрузки врачей филиала:', data.error);
                showNotification('Ошибка загрузки врачей филиала', 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка при загрузке врачей филиала:', error);
            showNotification('Ошибка соединения с сервером', 'error');
        });
    }

    // Функция загрузки врачей для переноса (старая версия для совместимости)
    function loadEmployeesForMove(currentEmployeeId) {
        const csrfToken = getCSRFToken();
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
            },
            body: new URLSearchParams({
                action: 'getEmployees',
                sessid: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateEmployeeSelectForMove(data.employees, currentEmployeeId);
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

    // Функция заполнения селектора врачей для переноса
    function populateEmployeeSelectForMove(employees, currentEmployeeId) {
        const employeeSelect = document.getElementById('move-event-employee');
        employeeSelect.innerHTML = '<option value="">Выберите врача</option>';
        
        employees.forEach(employee => {
            const option = document.createElement('option');
            option.value = employee.ID;
            option.textContent = `${employee.NAME} ${employee.LAST_NAME}`.trim() || employee.LOGIN;
            
            // Устанавливаем текущего врача как выбранного
            if (employee.ID == currentEmployeeId) {
                option.selected = true;
            }
            
            employeeSelect.appendChild(option);
        });
        
        // Если есть выбранный врач и дата, загружаем его расписание
        if (currentEmployeeId) {
            const dateInput = document.getElementById('move-event-date');
            const selectedDate = dateInput.value;
            if (selectedDate) {
                loadDoctorScheduleForMove(currentEmployeeId, selectedDate);
            }
        }
    }

    // Функция обработки изменения филиала в форме переноса
    function onMoveBranchChange() {
        const branchSelect = document.getElementById('move-event-branch');
        const selectedBranchId = branchSelect.value;
        
        // Очищаем селектор врачей
        const employeeSelect = document.getElementById('move-event-employee');
        employeeSelect.innerHTML = '<option value="">Выберите врача</option>';
        
        // Очищаем селектор времени
        const timeSelect = document.getElementById('move-event-time');
        timeSelect.innerHTML = '<option value="">Выберите время</option>';
        
        // Загружаем врачей для выбранного филиала
        if (selectedBranchId) {
            loadEmployeesForMoveByBranch(selectedBranchId, null);
        }
    }

    // Функция загрузки расписания врача для переноса
    function loadDoctorScheduleForMove(employeeId, date, branchId = null) {
        // Если branchId не передан, получаем его из селектора
        if (!branchId) {
            const branchSelect = document.getElementById('move-event-branch');
            branchId = branchSelect ? branchSelect.value : null;
        }
        
        const csrfToken = getCSRFToken();
        const postData = {
            action: 'getDoctorScheduleForMove',
            employeeId: employeeId,
            date: date,
            excludeEventId: window.currentEventId,
            sessid: csrfToken
        };
        
        // Добавляем branchId только если он есть
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
            console.log('Ответ сервера для getDoctorScheduleForMove:', data);
            if (data.success) {
                console.log('availableTimes:', data.availableTimes);
                populateTimeSelectForMove(data.availableTimes);
            } else {
                console.error('Ошибка загрузки расписания врача:', data.error);
                showNotification('Ошибка загрузки расписания врача', 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка при загрузке расписания врача:', error);
            showNotification('Ошибка соединения с сервером', 'error');
        });
    }

    // Функция загрузки доступных времен для переноса (устаревшая, оставляем для совместимости)
    function loadAvailableTimesForMove(date) {
        const employeeId = document.getElementById('move-event-employee').value;
        if (!employeeId) {
            const timeSelect = document.getElementById('move-event-time');
            timeSelect.innerHTML = '<option value="">Сначала выберите врача</option>';
            return;
        }

        loadDoctorScheduleForMove(employeeId, date);
    }

    // Функция заполнения селектора времени для переноса
    function populateTimeSelectForMove(availableTimes) {
        const timeSelect = document.getElementById('move-event-time');
        timeSelect.innerHTML = '<option value="">Выберите время</option>';
        
        // Проверяем, что availableTimes является массивом
        if (!Array.isArray(availableTimes)) {
            console.error('availableTimes не является массивом:', availableTimes);
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'Ошибка загрузки времен';
            option.disabled = true;
            timeSelect.appendChild(option);
            return;
        }
        
        if (availableTimes.length === 0) {
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

    // Обработчик изменения врача в модальном окне переноса
    function onMoveEmployeeChange() {
        const employeeId = document.getElementById('move-event-employee').value;
        const dateInput = document.getElementById('move-event-date');
        const selectedDate = dateInput.value;
        
        if (!employeeId) {
            const timeSelect = document.getElementById('move-event-time');
            timeSelect.innerHTML = '<option value="">Сначала выберите врача</option>';
            return;
        }
        
        if (selectedDate) {
            // Загружаем расписание врача для выбранной даты
            loadDoctorScheduleForMove(employeeId, selectedDate);
        } else {
            const timeSelect = document.getElementById('move-event-time');
            timeSelect.innerHTML = '<option value="">Выберите дату</option>';
        }
    }

    // Обработчик изменения даты в модальном окне переноса
    function onMoveDateChange() {
        const employeeId = document.getElementById('move-event-employee').value;
        const dateInput = document.getElementById('move-event-date');
        const selectedDate = dateInput.value;
        
        if (!employeeId) {
            const timeSelect = document.getElementById('move-event-time');
            timeSelect.innerHTML = '<option value="">Сначала выберите врача</option>';
            return;
        }
        
        if (selectedDate) {
            // Загружаем расписание врача для выбранной даты
            loadDoctorScheduleForMove(employeeId, selectedDate);
        } else {
            const timeSelect = document.getElementById('move-event-time');
            timeSelect.innerHTML = '<option value="">Выберите дату</option>';
        }
    }

    // Обработчик отправки формы переноса
    function handleMoveEventSubmit(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        const eventId = formData.get('eventId');
        const branchId = formData.get('branch_id');
        const employeeId = formData.get('employee_id');
        const date = formData.get('date');
        const time = formData.get('time');
        
        if (!eventId || !branchId || !employeeId || !date || !time) {
            showNotification('Заполните все поля', 'error');
            return;
        }
        
        // Создаем дату и время для переноса
        const [year, month, day] = date.split('-');
        const [hours, minutes] = time.split(':');
        const newDateTime = `${year}-${month}-${day} ${hours}:${minutes}:00`;
        
        // Получаем длительность события из текущего события
        const csrfToken = getCSRFToken();
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
        .then(data => {
            if (data.success) {
                const event = data.event;
                console.log('moveEvent: исходное событие:', event);
                console.log('moveEvent: DATE_FROM:', event.DATE_FROM);
                console.log('moveEvent: DATE_TO:', event.DATE_TO);
                
                const duration = getEventDuration(event.DATE_FROM, event.DATE_TO);
                console.log('moveEvent: длительность в минутах:', duration);
                
                // Вычисляем новое время окончания
                const startTime = new Date(newDateTime);
                console.log('moveEvent: новое время начала:', startTime);
                console.log('moveEvent: startTime isValid:', !isNaN(startTime.getTime()));
                
                const endTime = new Date(startTime.getTime() + duration * 60 * 1000);
                console.log('moveEvent: новое время окончания:', endTime);
                console.log('moveEvent: endTime isValid:', !isNaN(endTime.getTime()));
                
                const newEndDateTime = formatLocalDateTime(endTime);
                console.log('moveEvent: форматированное время окончания:', newEndDateTime);
                
                // Переносим событие с обменом местами
                return fetch('/local/components/artmax/calendar/ajax.php', {
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
                });
            } else {
                throw new Error('Ошибка получения данных события');
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Запись успешно перенесена', 'success');
                closeMoveEventModal();
                closeEventSidePanel();
                
                console.log('moveEvent: результат переноса:', data);
                
                // Получаем список всех затронутых событий
                const affectedEventIds = data.affectedEvents || [parseInt(eventId)];
                console.log('moveEvent: затронутые события:', affectedEventIds);
                
                // Получаем обновленные данные для всех затронутых событий
                const fetchPromises = affectedEventIds.map(id => 
                    fetch('/local/components/artmax/calendar/ajax.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-Bitrix-Csrf-Token': csrfToken
                        },
                        body: new URLSearchParams({
                            action: 'getEvent',
                            eventId: id,
                            sessid: csrfToken
                        })
                    }).then(response => response.json())
                );
                
                return Promise.all(fetchPromises);
            } else {
                throw new Error('Ошибка переноса записи: ' + (data.error || 'Неизвестная ошибка'));
            }
        })
        .then(responses => {
            console.log('moveEvent: получили данные о затронутых событиях:', responses);
            
            // Обновляем все затронутые события в календаре
            responses.forEach(response => {
                if (response.success && response.event) {
                    const updatedEvent = response.event;
                    
                    
                    updateEventInCalendar({
                        id: updatedEvent.ID,
                        title: updatedEvent.TITLE,
                        description: updatedEvent.DESCRIPTION || '',
                        dateFrom: updatedEvent.DATE_FROM,
                        dateTo: updatedEvent.DATE_TO,
                        eventColor: updatedEvent.EVENT_COLOR || '#3498db',
                        contactName: updatedEvent.CONTACT_NAME || '',
                        contactPhone: updatedEvent.CONTACT_PHONE || '',
                        contactEntityId: updatedEvent.CONTACT_ENTITY_ID || null,
                        dealEntityId: updatedEvent.DEAL_ENTITY_ID || null,
                        visitStatus: updatedEvent.VISIT_STATUS || 'not_specified',
                        confirmationStatus: updatedEvent.CONFIRMATION_STATUS || 'pending'
                    });
                }
            });
            
            // После обновления всех событий сортируем их по времени
            sortAllEventsInCalendar();
        })
        .catch(error => {
            console.error('Ошибка при переносе записи:', error);
            console.warn('Fallback: перезагружаем весь календарь из-за ошибки');
            
            // Fallback: если что-то пошло не так, перезагружаем весь календарь
            refreshCalendarEvents();
            showNotification('Ошибка при обновлении событий, календарь перезагружен', 'warning');
        });
    }

    // Вспомогательная функция для получения длительности события в минутах
    function getEventDuration(dateFrom, dateTo) {
        // Преобразуем российский формат даты в стандартный для парсинга
        const convertRussianDate = (russianDate) => {
            if (!russianDate || typeof russianDate !== 'string') {
                console.error('Invalid date format:', russianDate);
                return null;
            }
            
            // Формат: "13.10.2025 09:00:00" -> "2025-10-13 09:00:00"
            const parts = russianDate.split(' ');
            if (parts.length !== 2) {
                console.error('Invalid date format, expected "DD.MM.YYYY HH:MM:SS":', russianDate);
                return null;
            }
            
            const [datePart, timePart] = parts;
            const [day, month, year] = datePart.split('.');
            
            if (!day || !month || !year) {
                console.error('Invalid date part:', datePart);
                return null;
            }
            
            return `${year}-${month}-${day} ${timePart}`;
        };
        
        const standardDateFrom = convertRussianDate(dateFrom);
        const standardDateTo = convertRussianDate(dateTo);
        
        console.log('getEventDuration: исходные даты:', { dateFrom, dateTo });
        console.log('getEventDuration: конвертированные даты:', { standardDateFrom, standardDateTo });
        
        if (!standardDateFrom || !standardDateTo) {
            console.error('Failed to convert dates');
            return 60; // Возвращаем 1 час по умолчанию
        }
        
        const start = new Date(standardDateFrom);
        const end = new Date(standardDateTo);
        
        console.log('getEventDuration: объекты дат:', { start, end });
        console.log('getEventDuration: даты валидны:', !isNaN(start.getTime()), !isNaN(end.getTime()));
        
        if (isNaN(start.getTime()) || isNaN(end.getTime())) {
            console.error('Invalid dates after conversion');
            return 60; // Возвращаем 1 час по умолчанию
        }
        
        const duration = Math.round((end - start) / (1000 * 60));
        console.log('getEventDuration: длительность в минутах:', duration);
        
        return duration;
    }

    // Вспомогательная функция для форматирования даты и времени
    // Функции для навигации по месяцам - делаем их глобальными
    window.changeMonth = function(month) {
        console.log('changeMonth v2.2 called with month:', month);
        
        // Получаем текущую дату из URL
        const urlParams = new URLSearchParams(window.location.search);
        const currentDateStr = urlParams.get('date') || new Date().toISOString().split('T')[0];
        
        console.log('Current date from URL:', currentDateStr);
        
        // Парсим текущую дату
        const currentDate = new Date(currentDateStr);
        const year = currentDate.getFullYear();
        
        console.log('Parsed year:', year);
        
        // Создаем новую дату с выбранным месяцем (1-е число месяца)
        // Используем правильный формат: год-месяц-день
        const dateString = year + '-' + String(month).padStart(2, '0') + '-' + '01';
        
        console.log('Changing month to:', month, 'New date:', dateString);
        
        // Обновляем URL с новой датой
        const url = new URL(window.location);
        url.searchParams.set('date', dateString);
        window.location.href = url.toString();
    };
    
    // Функция для установки правильного значения в селектор при загрузке страницы
    window.initMonthSelector = function() {
        console.log('initMonthSelector v2.2 called');
        
        // Получаем текущую дату из URL
        const urlParams = new URLSearchParams(window.location.search);
        const currentDateStr = urlParams.get('date') || new Date().toISOString().split('T')[0];
        
        console.log('Current date from URL for selector:', currentDateStr);
        
        // Парсим дату и получаем месяц
        const currentDate = new Date(currentDateStr);
        const month = currentDate.getMonth() + 1; // getMonth() возвращает 0-11, нам нужно 1-12
        
        console.log('Setting month selector to:', month);
        
        // Устанавливаем значение в селектор
        const monthSelect = document.getElementById('monthSelect');
        if (monthSelect) {
            monthSelect.value = month;
            console.log('Month selector updated to:', monthSelect.value);
        } else {
            console.log('Month selector not found!');
        }
    };

    function formatLocalDateTime(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        const seconds = String(date.getSeconds()).padStart(2, '0');
        return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
    }

    // Делаем функции доступными глобально
    window.moveEventFromSidePanel = moveEventFromSidePanel;
    window.toggleEventStatusFromSidePanel = toggleEventStatusFromSidePanel;
    window.updateEventStatus = updateEventStatus;
    window.updateCancelButtonByStatus = updateCancelButtonByStatus;
    window.openMoveEventModal = openMoveEventModal;
    window.closeMoveEventModal = closeMoveEventModal;
    window.onMoveDateChange = onMoveDateChange;
    window.onMoveBranchChange = onMoveBranchChange;
    window.onMoveEmployeeChange = onMoveEmployeeChange;
    window.handleMoveEventSubmit = handleMoveEventSubmit;

    // Глобальные функции для формы расписания
    window.selectSchedulePresetColor = function(color) {
        console.log('selectSchedulePresetColor вызвана с цветом:', color);
        document.getElementById('schedule-selected-color').value = color;
        document.getElementById('custom-color-input').value = color;
        
        // Обновляем активный класс для пресетов в форме расписания
        const scheduleModal = document.getElementById('scheduleModal');
        if (scheduleModal) {
            scheduleModal.querySelectorAll('.color-preset').forEach(preset => {
                preset.classList.remove('active');
            });
            event.target.classList.add('active');
        }
        
        console.log('schedule-selected-color установлен в:', document.getElementById('schedule-selected-color').value);
    };
    
    window.selectScheduleCustomColor = function(color) {
        console.log('selectScheduleCustomColor вызвана с цветом:', color);
        document.getElementById('schedule-selected-color').value = color;
        
        // Убираем активный класс со всех пресетов в форме расписания
        const scheduleModal = document.getElementById('scheduleModal');
        if (scheduleModal) {
            scheduleModal.querySelectorAll('.color-preset').forEach(preset => {
                preset.classList.remove('active');
            });
        }
        
        console.log('schedule-selected-color установлен в:', document.getElementById('schedule-selected-color').value);
    };

    // Делаем showNotification доступной глобально
    window.showNotification = showNotification;

    // Функция для перехода к текущему дню
    window.goToToday = function() {
        const today = new Date();
        const year = today.getFullYear();
        const month = today.getMonth() + 1;
        const day = today.getDate();
        
        const dateString = year + '-' + String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0');
        const url = new URL(window.location);
        url.searchParams.set('date', dateString);
        window.location.href = url.toString();
    };

    // Убеждаемся, что функции навигации экспортированы в window
    // (они уже экспортированы выше, но на всякий случай повторяем)
    if (typeof previousMonth === 'function') {
        window.previousMonth = previousMonth;
        console.log('previousMonth экспортирована в window');
    }
    if (typeof nextMonth === 'function') {
        window.nextMonth = nextMonth;
        console.log('nextMonth экспортирована в window');
    }

})();