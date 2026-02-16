/**
 * ArtMax Client Form Component JavaScript
 */

// Функция инициализации после загрузки BX или сразу если BX недоступен
function initializeClientForm() {
    let eventId = window.clientFormData ? window.clientFormData.eventId : null;
    let searchResults = [];
    let searchTimeout = null;
    
    // Функция закрытия SidePanel
    window.closeSidePanel = function() {
        // Устанавливаем флаг, чтобы предотвратить редирект при закрытии
        window._isClosingSidePanel = true;
        
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

    // Функция показа/скрытия выпадающего окошка
    function showContactDropdown() {
        const dropdown = document.getElementById('contact-search-dropdown');
        if (dropdown) {
            dropdown.style.display = 'block';
        }
    }

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

    // Поиск контактов через Bitrix UI Entity Selector
    function searchContactsViaStandardService(query) {
        const csrfToken = getCSRFToken();
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
                    entities: [{
                        id: "contact",
                        options: {},
                        searchable: true,
                        dynamicLoad: true,
                        dynamicSearch: true,
                        filters: [],
                        substituteEntityId: null
                    }],
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
                searchResults = processedContacts;
                updateSearchResults(processedContacts);
            } else if (data && data.status === 'error') {
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

    // Обработка результатов поиска контактов от Bitrix UI Entity Selector
    function processBitrixEntitySelectorContacts(data) {
        if (!data || !data.dialog || !data.dialog.items || !Array.isArray(data.dialog.items)) {
            return [];
        }
        
        return data.dialog.items.map(item => {
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
            
            return {
                id: item.id,
                name: item.title || 'Контакт #' + item.id,
                firstName: '',
                lastName: '',
                secondName: '',
                phone: phones.join(', '),
                email: emails.join(', '),
                company: item.subtitle || ''
            };
        });
    }

    // Показ индикатора загрузки
    function showSearchLoading() {
        const dropdown = document.getElementById('contact-search-dropdown');
        if (dropdown) {
            dropdown.innerHTML = `
                <div class="search-loading" style="padding: 12px; text-align: center; color: #6c757d;">
                    <span>Поиск контактов...</span>
                </div>
                <button class="create-new-contact-btn" onclick="showCreateContactForm()">
                    <span class="plus-icon">+</span>
                    создать новый контакт
                </button>
            `;
        }
    }

    // Обновление результатов поиска
    function updateSearchResults(contacts) {
        const dropdown = document.getElementById('contact-search-dropdown');
        if (!dropdown) return;
        
        if (contacts.length === 0) {
            dropdown.innerHTML = `
                <div class="search-no-results" style="padding: 12px; text-align: center; color: #6c757d;">
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
                    <div class="client-search-item" data-contact-id="${contact.id}">
                        <div class="client-info">
                            <div class="client-name">${contact.name}</div>
                            <div class="client-details">
                                ${contact.phone ? `<div class="contact-phone">📞 ${contact.phone}</div>` : ''}
                                ${contact.email ? `<div class="contact-email">✉️ ${contact.email}</div>` : ''}
                                ${contact.company ? `<div class="contact-company">🏢 ${contact.company}</div>` : ''}
                            </div>
                        </div>
                        <button class="select-client-btn">Выбрать</button>
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
            const contactItems = dropdown.querySelectorAll('.client-search-item');
            contactItems.forEach(item => {
                const selectBtn = item.querySelector('.select-client-btn');
                if (selectBtn) {
                    selectBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const contactId = item.getAttribute('data-contact-id');
                        const contact = contacts.find(c => c.id == contactId);
                        if (contact) {
                            selectContact(contact);
                        }
                    });
                }
                item.addEventListener('click', function(e) {
                    if (!e.target.closest('.select-client-btn')) {
                        const contactId = item.getAttribute('data-contact-id');
                        const contact = contacts.find(c => c.id == contactId);
                        if (contact) {
                            selectContact(contact);
                        }
                    }
                });
            });
        }
    }

    // Функция показа ошибки поиска
    function showSearchError(errorMessage) {
        const dropdown = document.getElementById('contact-search-dropdown');
        if (dropdown) {
            dropdown.innerHTML = `
                <div class="search-error" style="padding: 12px; text-align: center; color: #ff5752;">
                    <span>❌ ${errorMessage}</span>
                </div>
                <button class="create-new-contact-btn" onclick="showCreateContactForm()">
                    <span class="plus-icon">+</span>
                    создать новый контакт
                </button>
            `;
        }
    }

    // Функция выбора контакта
    function selectContact(contact) {
        // Сохраняем ID контакта
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
            phoneInput.value = formatPhoneNumber(contact.phone);
        }
        
        // Заполняем поле email
        const emailInput = document.getElementById('email-input');
        if (emailInput && contact.email) {
            emailInput.value = contact.email;
        }
        
        // Показываем дополнительные поля
        showContactDetailsFields();
        
        // Скрываем выпадающий список
        hideContactDropdown();
    }

    // Показ дополнительных полей контакта
    function showContactDetailsFields() {
        const detailFields = document.querySelectorAll('.contact-details-field');
        detailFields.forEach((field, index) => {
            setTimeout(() => {
                field.style.display = 'block';
                field.classList.add('show');
            }, index * 100);
        });
        
        // Показываем кнопку сохранения
        const saveBtn = document.getElementById('save-client-btn');
        if (saveBtn) {
            setTimeout(() => {
                saveBtn.style.display = 'inline-block';
            }, detailFields.length * 100);
        }
    }

    // Скрытие дополнительных полей контакта
    function hideContactDetailsFields() {
        const detailFields = document.querySelectorAll('.contact-details-field');
        detailFields.forEach(field => {
            field.classList.remove('show');
            setTimeout(() => {
                field.style.display = 'none';
            }, 300);
        });
        
        // Скрываем кнопку сохранения
        const saveBtn = document.getElementById('save-client-btn');
        if (saveBtn) {
            saveBtn.style.display = 'none';
        }
    }

    // Функция показа формы создания контакта
    window.showCreateContactForm = function() {
        const createForm = document.getElementById('create-contact-form');
        const searchGroup = document.getElementById('contact-search-group');
        const backToSearch = document.getElementById('back-to-search');
        
        if (searchGroup) searchGroup.style.display = 'none';
        if (backToSearch) backToSearch.style.display = 'block';
        if (createForm) createForm.style.display = 'block';
        
        // Показываем кнопку сохранения для формы создания контакта
        const saveBtn = document.getElementById('save-client-btn');
        if (saveBtn) {
            saveBtn.style.display = 'inline-block';
        }
        
        hideContactDropdown();
        clearCreateContactForm();
        
        // Инициализируем маску для поля телефона
        const phoneInput = document.getElementById('new-contact-phone');
        if (phoneInput) {
            initPhoneMask(phoneInput);
        }
    };

    // Функция скрытия формы создания контакта
    window.hideCreateContactForm = function() {
        const createForm = document.getElementById('create-contact-form');
        const searchGroup = document.getElementById('contact-search-group');
        const backToSearch = document.getElementById('back-to-search');
        
        if (createForm) createForm.style.display = 'none';
        if (searchGroup) searchGroup.style.display = 'block';
        if (backToSearch) backToSearch.style.display = 'none';
        
        // Скрываем кнопку сохранения, если не выбран контакт
        const contactIdInput = document.getElementById('contact-id');
        const saveBtn = document.getElementById('save-client-btn');
        if (saveBtn && (!contactIdInput || !contactIdInput.value)) {
            saveBtn.style.display = 'none';
        }
        
        clearCreateContactForm();
    };

    // Функция форматирования телефона в формат +7 (999) 999-99-99
    function formatPhoneNumber(phone) {
        if (!phone) return '';
        
        // Удаляем все нецифровые символы
        let value = phone.replace(/\D/g, '');
        
        // Если номер начинается с 8, заменяем на 7
        if (value.length > 0 && value[0] === '8') {
            value = '7' + value.substring(1);
        }
        
        // Если номер не начинается с 7, добавляем 7
        if (value.length > 0 && value[0] !== '7') {
            value = '7' + value;
        }
        
        // Ограничиваем длину до 11 цифр (7 + 10 цифр)
        if (value.length > 11) {
            value = value.substring(0, 11);
        }
        
        // Если номер слишком короткий, возвращаем как есть
        if (value.length <= 1) {
            return value.length === 1 && value[0] === '7' ? '+7 (' : phone;
        }
        
        // Форматируем номер
        let formattedValue = '+7';
        if (value.length > 1) {
            formattedValue += ' (' + value.substring(1, 4);
            if (value.length > 4) {
                formattedValue += ') ' + value.substring(4, 7);
                if (value.length > 7) {
                    formattedValue += '-' + value.substring(7, 9);
                    if (value.length > 9) {
                        formattedValue += '-' + value.substring(9, 11);
                    }
                }
            }
        }
        
        return formattedValue;
    }

    // Функция маски ввода телефона +7 (999) 999-99-99
    function initPhoneMask(inputElement) {
        if (!inputElement) return;
        
        // Проверяем, не инициализирована ли уже маска
        if (inputElement.dataset.maskInitialized === 'true') {
            return;
        }
        inputElement.dataset.maskInitialized = 'true';
        
        // Устанавливаем начальное значение только если поле пустое
        if (!inputElement.value || inputElement.value.trim() === '') {
            inputElement.value = '+7 (';
        }
        
        inputElement.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, ''); // Удаляем все нецифровые символы
            
            // Если начинается не с 7, добавляем 7
            if (value.length > 0 && value[0] !== '7') {
                value = '7' + value;
            }
            
            // Ограничиваем длину до 11 цифр (7 + 10 цифр)
            if (value.length > 11) {
                value = value.substring(0, 11);
            }
            
            // Форматируем номер
            let formattedValue = '+7';
            if (value.length > 1) {
                formattedValue += ' (' + value.substring(1, 4);
                if (value.length > 4) {
                    formattedValue += ') ' + value.substring(4, 7);
                    if (value.length > 7) {
                        formattedValue += '-' + value.substring(7, 9);
                        if (value.length > 9) {
                            formattedValue += '-' + value.substring(9, 11);
                        }
                    }
                }
            }
            
            e.target.value = formattedValue;
        });
        
        inputElement.addEventListener('keydown', function(e) {
            // Разрешаем удаление, но не позволяем удалить +7 (
            if (e.key === 'Backspace' && e.target.value === '+7 (') {
                e.preventDefault();
            }
        });
        
        inputElement.addEventListener('focus', function(e) {
            // Если поле пустое, устанавливаем начальное значение
            if (!e.target.value || e.target.value.trim() === '') {
                e.target.value = '+7 (';
            }
        });
        
        inputElement.addEventListener('blur', function(e) {
            // Если остался только +7 (, очищаем поле
            if (e.target.value === '+7 (') {
                e.target.value = '';
            }
        });
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

    // Привязка контакта к событию, уведомление родителя и закрытие панели
    function bindContactToEventAndClose(eventId, contactId, contactData) {
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
                contactData: JSON.stringify(contactData),
                sessid: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (window.parent && window.parent.postMessage) {
                    window.parent.postMessage({
                        type: 'calendar:contactSaved',
                        contactId: contactId,
                        eventId: eventId
                    }, '*');
                }
                hideCreateContactForm();
                setTimeout(closeSidePanel, 300);
            } else {
                showNotification('Контакт создан, но не удалось привязать к записи: ' + (data.error || ''), 'error');
                selectContactById(contactId);
            }
        })
        .catch(error => {
            console.error('Ошибка привязки контакта к событию:', error);
            showNotification('Контакт создан, но не удалось привязать к записи', 'error');
            selectContactById(contactId);
        });
    }

    // Функция создания контакта
    window.createContact = function() {
        const name = document.getElementById('new-contact-name').value.trim();
        const lastname = document.getElementById('new-contact-lastname').value.trim();
        const phone = document.getElementById('new-contact-phone').value.trim();
        const email = document.getElementById('new-contact-email').value.trim();
        
        if (!name) {
            showNotification('Поле "Имя" обязательно для заполнения', 'error');
            return;
        }
        
        const contactData = {
            name: name,
            lastname: lastname,
            phone: phone,
            email: email
        };
        
        const csrfToken = getCSRFToken();
        
        fetch('/local/components/artmax/calendar/ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Bitrix-Csrf-Token': csrfToken
            },
            body: new URLSearchParams({
                action: 'createContact',
                contactData: JSON.stringify(contactData),
                sessid: csrfToken
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Контакт успешно создан', 'success');
                
                if (data.contactId) {
                    const currentEventId = window.clientFormData ? window.clientFormData.eventId : null;
                    // Если открыто в контексте события — привязываем контакт и закрываем панель
                    if (currentEventId) {
                        const contactForSave = data.contact ? { ...data.contact, id: String(data.contact.id || data.contactId) } : { id: String(data.contactId), name: (contactData.name + ' ' + (contactData.lastname || '').trim()).trim(), phone: contactData.phone || '', email: contactData.email || '', company: '' };
                        bindContactToEventAndClose(currentEventId, data.contactId, contactForSave);
                    } else {
                        selectContactById(data.contactId);
                    }
                } else {
                    hideCreateContactForm();
                }
            } else {
                showNotification('Ошибка создания контакта: ' + (data.error || 'Неизвестная ошибка'), 'error');
            }
        })
        .catch(error => {
            console.error('Ошибка при создании контакта:', error);
            showNotification('Ошибка соединения с сервером', 'error');
        });
    };

    // Функция выбора контакта по ID
    function selectContactById(contactId) {
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
                selectContact({
                    id: data.contact.id,
                    name: data.contact.name,
                    phone: data.contact.phone || '',
                    email: data.contact.email || '',
                    company: ''
                });
                hideCreateContactForm();
            }
        })
        .catch(error => {
            console.error('Ошибка получения данных контакта:', error);
        });
    }

    // Функция сохранения данных клиента
    window.saveClientData = function() {
        // Проверяем, открыта ли форма создания контакта
        const createForm = document.getElementById('create-contact-form');
        if (createForm && createForm.style.display !== 'none') {
            // Если форма создания контакта видна, вызываем функцию создания контакта
            createContact();
            return;
        }
        
        const contactInput = document.getElementById('contact-input');
        const phoneInput = document.getElementById('phone-input');
        const emailInput = document.getElementById('email-input');
        const contactIdInput = document.getElementById('contact-id');
        
        const clientData = {
            id: contactIdInput ? contactIdInput.value : '',
            contact: contactInput ? contactInput.value.trim() : '',
            phone: phoneInput ? phoneInput.value.trim() : '',
            email: emailInput ? emailInput.value.trim() : ''
        };
        
        if (!clientData.id) {
            showNotification('Не выбран контакт из списка', 'error');
            return;
        }
        
        // Если нет eventId, получаем его из родительского окна или создаём новое событие
        if (!eventId) {
            // Пытаемся получить eventId из родительского окна
            if (window.parent && window.parent.postMessage) {
                // Запрашиваем eventId у родителя
                window.parent.postMessage({
                    type: 'calendar:getCurrentEventId'
                }, '*');
                
                // Ждем ответа
                const messageHandler = function(e) {
                    if (e.data && e.data.type === 'calendar:currentEventId') {
                        eventId = e.data.eventId;
                        window.removeEventListener('message', messageHandler);
                        proceedSaveClient();
                    }
                };
                window.addEventListener('message', messageHandler);
                return;
            }
        }
        
        proceedSaveClient();
        
        function proceedSaveClient() {
            const csrfToken = getCSRFToken();
            const action = eventId ? 'saveEventContact' : 'createEventWithContact';
            const body = eventId ? {
                action: action,
                eventId: eventId,
                contactData: JSON.stringify(clientData),
                sessid: csrfToken
            } : {
                action: action,
                contactData: JSON.stringify(clientData),
                sessid: csrfToken
            };
            
            fetch('/local/components/artmax/calendar/ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Bitrix-Csrf-Token': csrfToken
                },
                body: new URLSearchParams(body)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Контакт успешно сохранен', 'success');
                    
                    // Отправляем сообщение родительскому окну
                    if (window.parent) {
                        window.parent.postMessage({
                            type: 'calendar:contactSaved',
                            contactId: clientData.id,
                            eventId: data.eventId || eventId
                        }, '*');
                    }
                    
                    // Закрываем SidePanel
                    setTimeout(() => {
                        closeSidePanel();
                    }, 500);
                } else {
                    showNotification('Ошибка сохранения: ' + (data.error || 'Неизвестная ошибка'), 'error');
                }
            })
            .catch(error => {
                console.error('Ошибка AJAX запроса:', error);
                showNotification('Ошибка соединения с сервером', 'error');
            });
        }
    };

    // Инициализация маски для полей телефона при загрузке страницы
    const newContactPhoneInput = document.getElementById('new-contact-phone');
    if (newContactPhoneInput) {
        initPhoneMask(newContactPhoneInput);
    }
    
    const phoneInput = document.getElementById('phone-input');
    if (phoneInput) {
        initPhoneMask(phoneInput);
    }
    
    // Инициализация поиска
    const contactInput = document.getElementById('contact-input');
    const contactDropdown = document.getElementById('contact-search-dropdown');
    
    if (contactInput) {
        contactInput.addEventListener('input', function() {
            const query = this.value.trim();
            
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
            
            if (query.length > 0) {
                updateSearchText(query);
                showContactDropdown();
                
                if (query.length >= 2) {
                    showSearchLoading();
                    searchTimeout = setTimeout(() => {
                        searchContactsViaStandardService(query);
                    }, 300);
                }
            } else {
                hideContactDropdown();
            }
        });
        
        contactInput.addEventListener('focus', function() {
            const query = this.value.trim();
            if (query.length > 0) {
                updateSearchText(query);
                showContactDropdown();
            }
        });
    }
    
    // Обработчик клика вне выпадающего окошка
    document.addEventListener('click', function(e) {
        if (!contactInput?.contains(e.target) && !contactDropdown?.contains(e.target)) {
            hideContactDropdown();
        }
    });
}

// Инициализируем форму
if (typeof BX !== 'undefined' && BX.ready) {
    BX.ready(function() {
        initializeClientForm();
    });
} else {
    // Fallback для случаев, когда BX недоступен
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeClientForm);
    } else {
        initializeClientForm();
    }
}

