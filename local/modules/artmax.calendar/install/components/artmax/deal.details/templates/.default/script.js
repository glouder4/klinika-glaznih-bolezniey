function initializeDealDetailsForm() {
    const saveButton = document.getElementById('deal-details-save');
    const cancelButton = document.getElementById('deal-details-cancel');
    const form = document.getElementById('deal-details-form');

    if (saveButton) {
        saveButton.addEventListener('click', function(e) {
            e.preventDefault();
            saveDealDetails();
        });
    }

    if (cancelButton) {
        cancelButton.addEventListener('click', function(e) {
            e.preventDefault();
            closeSidePanel();
        });
    }

    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            saveDealDetails();
        });
    }

    // Автоматическое заполнение поля филиала
    if (window.dealDetailsData) {
        console.log('dealDetailsData:', window.dealDetailsData);
        
        // Проверяем, что currentBranchEnumId установлен и не пустой
        const currentBranchEnumId = window.dealDetailsData.currentBranchEnumId;
        if (currentBranchEnumId && currentBranchEnumId !== '' && currentBranchEnumId !== null && currentBranchEnumId !== 'null') {
            const branchSelect = document.getElementById('deal-branch');
            if (branchSelect) {
                // Проверяем, не заполнено ли уже поле значением из сделки
                const currentValue = branchSelect.value;
                const hasDealValue = currentValue && currentValue !== '';
                
                console.log('Текущее значение филиала:', currentValue);
                console.log('ID enum филиала для установки:', currentBranchEnumId);
                
                // Если поле пустое или значение не установлено, устанавливаем текущий филиал
                if (!hasDealValue) {
                    // Проверяем, существует ли опция с таким значением
                    const targetEnumId = String(currentBranchEnumId);
                    const optionExists = Array.from(branchSelect.options).some(
                        option => String(option.value) === targetEnumId
                    );
                    
                    console.log('Опция существует:', optionExists);
                    console.log('Доступные опции:', Array.from(branchSelect.options).map(opt => ({value: opt.value, text: opt.text})));
                    
                    if (optionExists) {
                        branchSelect.value = targetEnumId;
                        console.log('✓ Значение филиала установлено:', branchSelect.value);
                        // Триггерим событие change для обновления UI
                        branchSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    } else {
                        console.warn('✗ Enum значение филиала не найдено в списке:', currentBranchEnumId);
                        console.log('Доступные опции:', Array.from(branchSelect.options).map(opt => ({value: opt.value, text: opt.text})));
                    }
                } else {
                    console.log('Поле филиала уже заполнено значением из сделки:', currentValue);
                }
            } else {
                console.warn('Элемент deal-branch не найден');
            }
        } else {
            console.log('currentBranchEnumId не установлен или пустой. targetBranchId:', window.dealDetailsData?.targetBranchId);
            console.log('Проверка: currentBranchEnumId =', currentBranchEnumId, '(тип:', typeof currentBranchEnumId, ')');
        }
    }
}

function getCSRFToken() {
    if (typeof BX !== 'undefined' && typeof BX.bitrix_sessid === 'function') {
        return BX.bitrix_sessid();
    }

    const tokenInput = document.querySelector('input[name="sessid"]');
    if (tokenInput) {
        return tokenInput.value;
    }

    return '';
}

function showDealNotification(message, type) {
    if (typeof BX !== 'undefined' && BX.UI && BX.UI.Notification) {
        BX.UI.Notification.Center.notify({
            content: message,
            position: 'top-right'
        });
    } else {
        alert(message);
    }
}

function closeSidePanel() {
    if (typeof BX !== 'undefined' && BX.SidePanel && BX.SidePanel.Instance) {
        BX.SidePanel.Instance.close();
    } else {
        window.close();
    }
}

function saveDealDetails() {
    const form = document.getElementById('deal-details-form');
    if (!form) {
        return;
    }

    const dealId = form.getAttribute('data-deal-id');
    if (!dealId) {
        showDealNotification('Ошибка: не удалось определить сделку', 'error');
        return;
    }

    const eventId = form.getAttribute('data-event-id');

    const payload = {
        title: document.getElementById('deal-title')?.value.trim() || '',
        service: document.getElementById('deal-service')?.value || '',
        source: document.getElementById('deal-source')?.value || '',
        branch: document.getElementById('deal-branch')?.value || '',
        amountValue: document.getElementById('deal-amount-value')?.value || '',
        amountCurrency: document.getElementById('deal-amount-currency')?.value || 'RUB'
    };

    const saveButton = document.getElementById('deal-details-save');
    if (saveButton) {
        saveButton.value = 'Сохранение...';
        saveButton.disabled = true;
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
            action: 'saveDealCustomFields',
            dealId: dealId,
            fields: JSON.stringify(payload),
            sessid: csrfToken
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showDealNotification('Данные сделки сохранены', 'success');

            if (window.parent && window.parent.postMessage) {
                window.parent.postMessage({
                    type: 'calendar:dealCustomFieldsSaved',
                    dealId: data.dealId || dealId,
                    dealTitle: data.dealTitle || payload.title,
                    eventId: eventId || window.dealDetailsData?.eventId || null
                }, '*');
            }

            setTimeout(closeSidePanel, 400);
        } else {
            showDealNotification(data.error || 'Ошибка сохранения', 'error');
        }
    })
    .catch(error => {
        console.error('saveDealDetails error:', error);
        showDealNotification('Ошибка соединения с сервером', 'error');
    })
    .finally(() => {
        if (saveButton) {
            saveButton.value = 'Сохранить';
            saveButton.disabled = false;
        }
    });
}

if (typeof BX !== 'undefined' && BX.ready) {
    BX.ready(initializeDealDetailsForm);
} else if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeDealDetailsForm);
} else {
    initializeDealDetailsForm();
}

