/**
 * ArtMax Deal Form Component JavaScript
 */

// Функция инициализации после загрузки BX или сразу если BX недоступен
function initializeDealForm() {
    let eventId = window.dealFormData ? window.dealFormData.eventId : null;
    let searchResults = [];
    let searchTimeout = null;
    
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

    // Функция показа/скрытия выпадающего окошка
    function showDealDropdown() {
        const dropdown = document.getElementById('deal-search-dropdown');
        if (dropdown) {
            dropdown.style.display = 'block';
        }
    }

    function hideDealDropdown() {
        const dropdown = document.getElementById('deal-search-dropdown');
        if (dropdown) {
            dropdown.style.display = 'none';
        }
    }

    // Функция обновления текста поиска
    function updateDealSearchText(query) {
        const searchTextElement = document.querySelector('#deal-search-dropdown .search-text');
        if (searchTextElement) {
            searchTextElement.textContent = `«${query}»`;
        }
    }

    // Поиск сделок через Bitrix UI Entity Selector
    function searchDealsViaStandardService(query) {
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
                    id: "ui-selector-deal-search",
                    context: "BOOKING",
                    entities: [{
                        id: "deal",
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
                const processedDeals = processBitrixEntitySelectorDeals(data.data);
                searchResults = processedDeals;
                updateDealSearchResults(processedDeals);
            } else if (data && data.status === 'error') {
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

    // Обработка результатов поиска сделок от Bitrix UI Entity Selector
    function processBitrixEntitySelectorDeals(data) {
        if (!data || !data.dialog || !data.dialog.items || !Array.isArray(data.dialog.items)) {
            return [];
        }
        
        return data.dialog.items.map(item => {
            return {
                id: item.id,
                title: item.title || 'Сделка #' + item.id,
                subtitle: item.subtitle || '',
                amount: '',
                stage: '',
                company: item.subtitle || '',
                currency: 'RUB'
            };
        });
    }

    // Показ индикатора загрузки
    function showDealSearchLoading() {
        const dropdown = document.getElementById('deal-search-dropdown');
        if (dropdown) {
            dropdown.innerHTML = `
                <div class="search-loading" style="padding: 12px; text-align: center; color: #6c757d;">
                    <span>Поиск сделок...</span>
                </div>
                <button class="create-new-deal-btn" onclick="createNewDeal()">
                    <span class="plus-icon">+</span>
                    создать новую сделку
                </button>
            `;
        }
    }

    // Обновление результатов поиска
    function updateDealSearchResults(deals) {
        const dropdown = document.getElementById('deal-search-dropdown');
        if (!dropdown) return;
        
        if (deals.length === 0) {
            dropdown.innerHTML = `
                <div class="search-no-results" style="padding: 12px; text-align: center; color: #6c757d;">
                    <span>Сделки не найдены</span>
                </div>
                <button class="create-new-deal-btn" onclick="createNewDeal()">
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
                        <button class="select-deal-btn">Выбрать</button>
                    </div>
                `;
            });
            
            dropdown.innerHTML = resultsHtml + `
                <button class="create-new-deal-btn" onclick="createNewDeal()">
                    <span class="plus-icon">+</span>
                    создать новую сделку
                </button>
            `;
            
            // Добавляем обработчики клика для сделок
            const dealItems = dropdown.querySelectorAll('.search-deal-item');
            dealItems.forEach(item => {
                const selectBtn = item.querySelector('.select-deal-btn');
                if (selectBtn) {
                    selectBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const dealId = item.getAttribute('data-deal-id');
                        const deal = deals.find(d => d.id == dealId);
                        if (deal) {
                            selectDeal(deal);
                        }
                    });
                }
                item.addEventListener('click', function(e) {
                    if (!e.target.closest('.select-deal-btn')) {
                        const dealId = item.getAttribute('data-deal-id');
                        const deal = deals.find(d => d.id == dealId);
                        if (deal) {
                            selectDeal(deal);
                        }
                    }
                });
            });
        }
    }

    // Функция показа ошибки поиска
    function showDealSearchError(errorMessage) {
        const dropdown = document.getElementById('deal-search-dropdown');
        if (dropdown) {
            dropdown.innerHTML = `
                <div class="search-error" style="padding: 12px; text-align: center; color: #ff5752;">
                    <span>❌ ${errorMessage}</span>
                </div>
                <button class="create-new-deal-btn" onclick="createNewDeal()">
                    <span class="plus-icon">+</span>
                    создать новую сделку
                </button>
            `;
        }
    }

    // Функция выбора сделки
    function selectDeal(deal) {
        // Сохраняем ID сделки
        const dealIdInput = document.getElementById('deal-id');
        if (dealIdInput) {
            dealIdInput.value = deal.id;
        }
        
        // Заполняем поле сделки
        const dealInput = document.getElementById('deal-input');
        if (dealInput) {
            dealInput.value = deal.title;
        }
        
        // Показываем кнопку сохранения
        const saveBtn = document.getElementById('save-deal-btn');
        if (saveBtn) {
            saveBtn.style.display = 'inline-block';
        }
        
        // Скрываем выпадающий список
        hideDealDropdown();
    }

    // Функция создания новой сделки
    window.createNewDeal = function() {
        const dealInput = document.getElementById('deal-input');
        const query = dealInput ? dealInput.value.trim() : '';
        
        // Показываем форму создания сделки в Bitrix CRM
        if (typeof BX !== 'undefined' && BX.SidePanel) {
            const dealUrl = `/crm/deal/edit/0/?IFRAME=Y&IFRAME_TYPE=SIDE_SLIDER${query ? '&TITLE=' + encodeURIComponent(query) : ''}`;
            BX.SidePanel.Instance.open(dealUrl, {
                title: 'Создать сделку',
                width: 800,
                cacheable: false,
                events: {
                    onClose: function(event) {
                        // После создания сделки можно обновить поиск
                        if (event && event.getSlider) {
                            // Сделка создана, можно обновить результаты
                            if (dealInput && dealInput.value.trim()) {
                                searchDealsInBitrix24(dealInput.value.trim());
                            }
                        }
                    }
                }
            });
        } else {
            showNotification('Откройте форму создания сделки в новой вкладке', 'info');
        }
        
        hideDealDropdown();
    };

    // Функция сохранения данных сделки
    window.saveDealData = function() {
        const dealInput = document.getElementById('deal-input');
        const dealIdInput = document.getElementById('deal-id');
        
        if (!dealInput || !dealInput.value.trim()) {
            showNotification('Введите название сделки', 'error');
            return;
        }
        
        const dealData = {
            id: dealIdInput ? dealIdInput.value : null,
            title: dealInput.value.trim()
        };
        
        // Если нет eventId, получаем его из родительского окна
        if (!eventId) {
            if (window.parent && window.parent.postMessage) {
                window.parent.postMessage({
                    type: 'calendar:getCurrentEventId'
                }, '*');
                
                const messageHandler = function(e) {
                    if (e.data && e.data.type === 'calendar:currentEventId') {
                        eventId = e.data.eventId;
                        window.removeEventListener('message', messageHandler);
                        proceedSaveDeal();
                    }
                };
                window.addEventListener('message', messageHandler);
                return;
            }
        }
        
        proceedSaveDeal();
        
        function proceedSaveDeal() {
            if (!eventId) {
                showNotification('Ошибка: не удалось определить событие', 'error');
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
                    action: 'saveEventDeal',
                    eventId: eventId,
                    dealData: JSON.stringify(dealData),
                    sessid: csrfToken
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Сделка успешно сохранена', 'success');
                    
                    // Отправляем сообщение родительскому окну
                    if (window.parent) {
                        window.parent.postMessage({
                            type: 'calendar:dealSaved',
                            dealId: dealData.id,
                            eventId: eventId
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

    // Функция поиска сделок
    function searchDealsInBitrix24(query) {
        showDealSearchLoading();
        searchDealsViaStandardService(query);
    }

    // Инициализация поиска
    const dealInput = document.getElementById('deal-input');
    const dealDropdown = document.getElementById('deal-search-dropdown');
    
    if (dealInput) {
        dealInput.addEventListener('input', function() {
            const query = this.value.trim();
            
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
            
            if (query.length > 0) {
                updateDealSearchText(query);
                showDealDropdown();
                
                if (query.length >= 2) {
                    showDealSearchLoading();
                    searchTimeout = setTimeout(() => {
                        searchDealsInBitrix24(query);
                    }, 300);
                }
            } else {
                hideDealDropdown();
            }
        });
        
        dealInput.addEventListener('focus', function() {
            const query = this.value.trim();
            if (query.length > 0) {
                updateDealSearchText(query);
                showDealDropdown();
            }
        });
    }
    
    // Обработчик клика вне выпадающего окошка
    document.addEventListener('click', function(e) {
        if (!dealInput?.contains(e.target) && !dealDropdown?.contains(e.target)) {
            hideDealDropdown();
        }
    });
}

// Инициализируем форму
if (typeof BX !== 'undefined' && BX.ready) {
    BX.ready(function() {
        initializeDealForm();
    });
} else {
    // Fallback для случаев, когда BX недоступен
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeDealForm);
    } else {
        initializeDealForm();
    }
}

