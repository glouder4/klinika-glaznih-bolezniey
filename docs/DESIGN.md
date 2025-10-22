# 🎨 Дизайн-концепция ArtMax Calendar

## 📋 Обзор

Данный документ описывает дизайн-концепцию и UI/UX принципы для модуля `artmax.calendar`, включая переход на SidePanel архитектуру и современный дизайн Bitrix24.

## 🎯 Основные принципы

### 1. **SidePanel First**
- Все формы создания/редактирования открываются в SidePanel
- Консистентный пользовательский опыт
- Сохранение контекста основной страницы

### 2. **Bitrix24 Native Design**
- Использование оригинальных CSS классов Bitrix24
- Следование дизайн-системе Bitrix24
- Адаптивность и доступность

### 3. **Двухколоночная раскладка**
- Лейблы слева, поля справа
- Фиксированная ширина лейблов (200px)
- Выравнивание по левому краю

## 🏗️ Архитектура форм

### Структура HTML

```html
<div class="side-panel-content-container">
    <div class="artmax-event-form">
        <form id="add-event-form" novalidate>
            <!-- Название события - большое поле сверху -->
            <div class="artmax-event-title-section">
                <label for="event-title" class="artmax-title-label">Название события</label>
                <input type="text" id="event-title" name="title" class="artmax-title-input" placeholder="Введите название события" required>
            </div>
            
            <!-- Блок настроек -->
            <div class="artmax-settings-block">
                <!-- Поля формы в двухколоночной раскладке -->
                <div class="artmax-form-field">
                    <label class="artmax-field-label">Описание</label>
                    <div class="artmax-field-content">
                        <textarea class="artmax-textarea"></textarea>
                    </div>
                </div>
                
                <!-- Дата и время в одной строке -->
                <div class="artmax-form-row">
                    <label class="artmax-field-label">Дата и время *</label>
                    <div class="artmax-field-content">
                        <div class="artmax-field-half">
                            <input type="date" class="artmax-input">
                        </div>
                        <div class="artmax-field-half">
                            <select class="artmax-select"></select>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Кнопки внизу -->
    <div class="webform-buttons calendar-form-buttons-fixed">
        <input type="button" class="ui-btn ui-btn-success" value="Сохранить">
        <input type="button" class="ui-btn ui-btn-link" value="Отмена">
    </div>
</div>
```

## 🎨 CSS Классы и стили

### Основные контейнеры

```css
/* SidePanel контейнер */
.side-panel-content-container {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 100vh;
}

/* Основная форма */
.artmax-event-form {
    padding: 12px;
    margin: 15px;
    border-radius: 12px;
    background: #ffffff;
}
```

### Поле названия события

```css
.artmax-title-input {
    width: 100%;
    padding: 16px 20px;
    border: 0;
    border-bottom: 1px solid silver;
    border-radius: 0;
    font-size: 18px;
    font-weight: 500;
    height: 56px;
}
```

### Блок настроек

```css
.artmax-settings-block {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 24px;
    background: var(--ui-color-palette-gray-03, #f8f9fa);
}
```

### Двухколоночная раскладка

```css
.artmax-form-field,
.artmax-form-row {
    display: flex !important;
    align-items: flex-start;
    margin-bottom: 16px;
    padding-bottom: 16px;
    gap: 16px;
    flex-direction: row !important;
    border-bottom: 1px solid #e6e9ec;
}

.artmax-field-label {
    flex: 0 0 200px !important;
    font-size: 13px;
    font-weight: 400;
    color: #525c69;
    text-align: left !important;
    padding-top: 6px;
}

.artmax-field-content {
    flex: 1;
    min-width: 0;
}
```

### Дата и время в одной строке

```css
.artmax-form-row .artmax-field-content {
    display: flex !important;
    gap: 12px;
    flex-direction: row !important;
}

.artmax-field-half {
    flex: 1 !important;
    min-width: 0;
}
```

### Кнопки

```css
.webform-buttons.calendar-form-buttons-fixed {
    display: flex;
    gap: 12px;
    justify-content: flex-start;
    margin-top: auto;
    padding: 20px;
    border-top: 1px solid #e5e7eb;
    background: #ffffff;
    position: sticky;
    bottom: 0;
    z-index: 10;
}
```

## 🔧 Технические требования

### JavaScript

1. **Подключение Bitrix UI**:
```php
CJSCore::Init(['ui.buttons']);
```

2. **Обработка дат без конвертации**:
```javascript
// Формируем дату точно как указал пользователь
const dateFrom = date + ' ' + time + ':00';

// Вычисляем время окончания
const [hours, minutes] = time.split(':');
const startMinutes = parseInt(hours) * 60 + parseInt(minutes);
const endMinutes = startMinutes + duration;
const endHours = Math.floor(endMinutes / 60);
const endMins = endMinutes % 60;
const endTime = String(endHours).padStart(2, '0') + ':' + String(endMins).padStart(2, '0');
const dateTo = date + ' ' + endTime + ':00';
```

### PHP

1. **Структура компонента**:
```php
// Подключаем стили Bitrix UI для кнопок
CJSCore::Init(['ui.buttons']);

// Передаем параметр iframe режима
$arResult['IS_IFRAME'] = ($_GET['IFRAME'] === 'Y');
```

2. **Обработка AJAX**:
```php
// Сервер принимает дату как есть, без конвертации
$dateFrom = $_POST['dateFrom'] ?? '';
$dateTo = $_POST['dateTo'] ?? '';
```

## 📱 Адаптивность

### Breakpoints

- **Desktop**: Полная двухколоночная раскладка
- **Tablet**: Сохранение структуры с уменьшенными отступами
- **Mobile**: Переход на одноколоночную раскладку

### Responsive CSS

```css
@media (max-width: 768px) {
    .artmax-form-field,
    .artmax-form-row {
        flex-direction: column !important;
    }
    
    .artmax-field-label {
        flex: none !important;
        text-align: left !important;
        margin-bottom: 8px;
    }
}
```

## 🎯 Миграция существующих форм

### Этапы миграции

1. **Анализ существующей формы**
   - Определение полей и их типов
   - Выявление обязательных полей
   - Анализ валидации

2. **Создание SidePanel версии**
   - Создание `page.php` для SidePanel
   - Адаптация HTML структуры
   - Применение CSS классов

3. **Обновление JavaScript**
   - Адаптация обработчиков событий
   - Обновление AJAX запросов
   - Интеграция с Bitrix UI

4. **Тестирование**
   - Проверка в разных браузерах
   - Тестирование на мобильных устройствах
   - Валидация форм

### Чек-лист миграции

- [ ] Создан `page.php` для SidePanel
- [ ] Применена структура `side-panel-content-container`
- [ ] Использованы CSS классы Bitrix24
- [ ] Реализована двухколоночная раскладка
- [ ] Добавлены кнопки в `webform-buttons calendar-form-buttons-fixed`
- [ ] Обновлен JavaScript для работы с датами
- [ ] Протестирована адаптивность
- [ ] Проверена валидация форм

## 🔄 Обратная совместимость

### Поддержка старых форм

- Старые формы продолжают работать
- Постепенная миграция по приоритету
- Сохранение функциональности

### Fallback механизмы

```javascript
// Fallback для случаев, когда BX недоступен
if (typeof BX !== 'undefined' && BX.ready) {
    BX.ready(function() {
        initializeEventForm();
    });
} else {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeEventForm);
    } else {
        initializeEventForm();
    }
}
```

## 📊 Метрики успеха

### UX метрики

- **Время создания записи**: < 30 секунд
- **Количество кликов**: Минимизация
- **Ошибки валидации**: < 5% от общего числа попыток

### Технические метрики

- **Время загрузки SidePanel**: < 2 секунды
- **Совместимость браузеров**: 95%+
- **Мобильная адаптивность**: 100%

## 🚀 Планы развития

### Ближайшие задачи

1. **Миграция формы редактирования событий**
2. **Добавление drag & drop для расписания**
3. **Интеграция с календарем Bitrix24**
4. **Улучшение мобильной версии**

### Долгосрочные цели

1. **Полная интеграция с Bitrix24 UI Kit**
2. **Поддержка темной темы**
3. **Расширенная аналитика использования**
4. **Автоматизация тестирования UI**

---

**Версия документации**: 1.0  
**Дата обновления**: 22.10.2025  
**Автор**: ArtMax Development Team
