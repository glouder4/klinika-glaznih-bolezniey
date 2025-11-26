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

