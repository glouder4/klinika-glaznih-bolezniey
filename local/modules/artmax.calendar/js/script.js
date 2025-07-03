/**
 * Artmax Calendar Module JavaScript
 */

(function() {
    'use strict';

    // Инициализация модуля
    document.addEventListener('DOMContentLoaded', function() {
        initCalendar();
    });

    function initCalendar() {
        // Автозаполнение даты окончания
        const dateFromInput = document.getElementById('date_from');
        const dateToInput = document.getElementById('date_to');

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

        // Валидация формы
        const form = document.querySelector('.artmax-calendar-form form');
        if (form) {
            form.addEventListener('submit', validateForm);
        }

        // Анимация появления сообщений
        animateMessages();

        // Инициализация tooltips
        initTooltips();
    }

    function validateForm(event) {
        const title = document.getElementById('title');
        const dateFrom = document.getElementById('date_from');
        const dateTo = document.getElementById('date_to');

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
            event.preventDefault();
            showNotification(errorMessage, 'error');
        }
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
            z-index: 10000;
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
                document.body.removeChild(notification);
            }, 300);
        }, 5000);
    }

    function animateMessages() {
        const messages = document.querySelectorAll('.artmax-calendar-error, .artmax-calendar-success');
        messages.forEach((message, index) => {
            message.style.opacity = '0';
            message.style.transform = 'translateY(-20px)';
            
            setTimeout(() => {
                message.style.transition = 'all 0.5s ease';
                message.style.opacity = '1';
                message.style.transform = 'translateY(0)';
            }, index * 100);
        });
    }

    function initTooltips() {
        const tooltipElements = document.querySelectorAll('[data-tooltip]');
        
        tooltipElements.forEach(element => {
            element.addEventListener('mouseenter', showTooltip);
            element.addEventListener('mouseleave', hideTooltip);
        });
    }

    function showTooltip(event) {
        const tooltip = document.createElement('div');
        tooltip.className = 'artmax-calendar-tooltip';
        tooltip.textContent = event.target.getAttribute('data-tooltip');
        
        tooltip.style.cssText = `
            position: absolute;
            background: #2c3e50;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            z-index: 1000;
            pointer-events: none;
            white-space: nowrap;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        `;

        document.body.appendChild(tooltip);

        const rect = event.target.getBoundingClientRect();
        tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
        tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';

        event.target.tooltip = tooltip;
    }

    function hideTooltip(event) {
        if (event.target.tooltip) {
            document.body.removeChild(event.target.tooltip);
            event.target.tooltip = null;
        }
    }

    // Функция для обновления событий без перезагрузки страницы
    window.artmaxCalendarRefresh = function() {
        const eventsContainer = document.querySelector('.artmax-calendar-events');
        if (eventsContainer) {
            eventsContainer.innerHTML = '<div class="artmax-calendar-loading">Загрузка событий...</div>';
            
            // Здесь можно добавить AJAX запрос для обновления событий
            setTimeout(() => {
                location.reload();
            }, 1000);
        }
    };

    // Экспорт функций для использования в других скриптах
    window.ArtmaxCalendar = {
        showNotification: showNotification,
        validateForm: validateForm,
        refresh: window.artmaxCalendarRefresh
    };

})(); 