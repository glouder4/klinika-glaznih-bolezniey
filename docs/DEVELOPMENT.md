# 👨‍💻 Руководство разработчика "Клиника глазных болезней"

## 🎯 Обзор для разработчиков

Это руководство предназначено для разработчиков, которые будут работать с системой управления записями клиники. Оно содержит информацию о структуре кода, стандартах разработки и лучших практиках.

## 🏗️ Структура проекта

### Модуль `artmax.calendar`

```
local/modules/artmax.calendar/
├── install/                    # Установка модуля
│   ├── index.php              # Основной файл установки
│   ├── step.php               # Шаг установки (создание таблиц)
│   ├── unstep.php             # Удаление модуля
│   ├── admin/                 # Административные страницы
│   ├── components/            # Компоненты для копирования
│   ├── css/                   # Стили
│   ├── js/                    # JavaScript файлы
│   └── lang/                  # Языковые файлы
├── lib/                       # Основные классы
│   ├── autoload.php           # Автозагрузка классов
│   ├── Calendar.php           # Управление событиями
│   ├── Branch.php             # Управление филиалами
│   ├── TimezoneManager.php    # Работа с часовыми поясами
│   ├── Events.php             # Обработчики событий Bitrix
│   └── EventHandlers.php      # Дополнительные обработчики
└── .settings.php             # Настройки модуля
```

### Компонент календаря

```
local/components/artmax/calendar/
├── class.php                  # Основной класс компонента
├── ajax.php                   # AJAX обработчик
├── .description.php           # Описание компонента
├── .parameters.php            # Параметры компонента
└── templates/
    └── .default/
        ├── template.php       # Шаблон отображения
        ├── style.css          # Стили шаблона
        └── script.js           # JavaScript шаблона
```

## 📝 Стандарты кодирования

## 🎨 Стандарты дизайна (SidePanel архитектура)

### Основные принципы

1. **SidePanel First** - все формы создания/редактирования открываются в SidePanel
2. **Bitrix24 Native Design** - использование оригинальных CSS классов Bitrix24
3. **Двухколоночная раскладка** - лейблы слева (200px), поля справа
4. **Консистентный UX** - единообразный интерфейс для всех форм

### Структура HTML для форм

```html
<div class="side-panel-content-container">
    <div class="artmax-event-form">
        <form id="form-id" novalidate>
            <!-- Название - большое поле сверху -->
            <div class="artmax-event-title-section">
                <label for="field-id" class="artmax-title-label">Название</label>
                <input type="text" id="field-id" class="artmax-title-input" required>
            </div>
            
            <!-- Блок настроек -->
            <div class="artmax-settings-block">
                <!-- Поля в двухколоночной раскладке -->
                <div class="artmax-form-field">
                    <label class="artmax-field-label">Лейбл</label>
                    <div class="artmax-field-content">
                        <input type="text" class="artmax-input">
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

### CSS классы

#### Основные контейнеры
- `.side-panel-content-container` - основной контейнер SidePanel
- `.artmax-event-form` - контейнер формы
- `.artmax-event-title-section` - секция названия
- `.artmax-settings-block` - блок настроек

#### Раскладка полей
- `.artmax-form-field` - обычное поле в две колонки
- `.artmax-form-row` - поле с несколькими элементами в строке
- `.artmax-field-label` - лейбл поля (200px ширина)
- `.artmax-field-content` - контент поля
- `.artmax-field-half` - половина поля для даты/времени

#### Элементы форм
- `.artmax-title-input` - большое поле названия
- `.artmax-input` - обычное поле ввода
- `.artmax-textarea` - текстовое поле
- `.artmax-select` - выпадающий список

#### Кнопки
- `.webform-buttons.calendar-form-buttons-fixed` - контейнер кнопок
- `.ui-btn.ui-btn-success` - кнопка сохранения
- `.ui-btn.ui-btn-link` - кнопка отмены

### JavaScript стандарты

#### Подключение Bitrix UI
```php
// В template.php
CJSCore::Init(['ui.buttons']);
```

#### Обработка дат без конвертации
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

#### SidePanel интеграция
```javascript
function openFormInSidePanel(url, title) {
    if (typeof BX !== 'undefined' && BX.SidePanel) {
        BX.SidePanel.Instance.open(url, {
            title: title,
            width: 600,
            cacheable: false,
            events: {
                onClose: function() {
                    // Обновление данных после закрытия
                }
            }
        });
    }
}
```

### Миграция существующих форм

#### Этапы миграции

1. **Создание page.php для SidePanel**
```php
<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Название формы");

$APPLICATION->IncludeComponent(
    "artmax:component.name",
    ".default",
    [
        "PARAM1" => $param1,
        "PARAM2" => $param2,
    ]
);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
?>
```

2. **Обновление HTML структуры**
- Применение CSS классов дизайн-системы
- Реализация двухколоночной раскладки
- Добавление контейнеров для кнопок

3. **Обновление JavaScript**
- Адаптация обработчиков событий
- Интеграция с Bitrix UI
- Обновление AJAX запросов

4. **Тестирование**
- Проверка в разных браузерах
- Тестирование на мобильных устройствах
- Валидация форм

### PHP

#### Именование
- **Классы**: PascalCase (`Calendar`, `TimezoneManager`)
- **Методы**: camelCase (`addEvent`, `getBranchTimezone`)
- **Переменные**: camelCase (`$eventId`, `$branchData`)
- **Константы**: UPPER_SNAKE_CASE (`MODULE_ID`)

#### Структура классов
```php
<?php
namespace Artmax\Calendar;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Connection;

class Calendar
{
    private $connection;
    private $timezoneManager;

    public function __construct()
    {
        $this->connection = Application::getConnection();
        $this->timezoneManager = new TimezoneManager();
    }

    /**
     * Добавить новое событие
     * @param string $title Название события
     * @param string $description Описание
     * @param string $dateFrom Дата начала
     * @param string $dateTo Дата окончания
     * @param int $userId ID пользователя
     * @param int $branchId ID филиала
     * @param string $eventColor Цвет события
     * @param int|null $employeeId ID сотрудника
     * @return int|false ID события или false при ошибке
     */
    public function addEvent($title, $description, $dateFrom, $dateTo, $userId, $branchId = 1, $eventColor = '#3498db', $employeeId = null)
    {
        // Реализация метода
    }
}

/**
 * Компонент календаря с рефакторингом параметров
 */
class ArtmaxCalendarComponent extends CBitrixComponent
{
    /**
     * Добавление расписания с массивом параметров
     * @param array $params Массив параметров:
     *   - title (string) - Название события
     *   - date (string) - Дата события (YYYY-MM-DD)
     *   - time (string) - Время события (HH:MM)
     *   - employee_id (int|null) - ID врача
     *   - repeat (bool) - Повторяющееся ли событие
     *   - frequency (string|null) - Частота повторения
     *   - weekdays (array) - Дни недели для еженедельного повторения
     *   - repeat_end (string) - Тип окончания повторения
     *   - repeat_count (int|null) - Количество повторений
     *   - repeat_end_date (string|null) - Дата окончания повторений
     *   - event_color (string) - Цвет события
     *   - exclude_weekends (bool) - Исключать ли выходные дни
     *   - exclude_holidays (bool) - Исключать ли праздничные дни
     *   - include_end_date (bool) - Включать ли конечную дату
     * @return array Результат операции
     */
    public function addScheduleAction($params)
    {
        // Извлекаем параметры из массива с значениями по умолчанию
        $title = $params['title'] ?? '';
        $date = $params['date'] ?? '';
        $time = $params['time'] ?? '';
        $employeeId = $params['employee_id'] ?? null;
        $repeat = $params['repeat'] ?? false;
        $frequency = $params['frequency'] ?? null;
        $weekdays = $params['weekdays'] ?? [];
        $repeatEnd = $params['repeat_end'] ?? 'never';
        $repeatCount = $params['repeat_count'] ?? null;
        $repeatEndDate = $params['repeat_end_date'] ?? null;
        $eventColor = $params['event_color'] ?? '#3498db';
        $excludeWeekends = $params['exclude_weekends'] ?? true;
        $excludeHolidays = $params['exclude_holidays'] ?? true;
        $includeEndDate = $params['include_end_date'] ?? true;

        // Реализация метода...
    }
}
```

#### Обработка ошибок
```php
try {
    $result = $this->connection->query($sql);
    if ($result) {
        return $this->connection->getInsertedId();
    }
    return false;
} catch (\Exception $e) {
    error_log('Ошибка добавления события: ' . $e->getMessage());
    return false;
}
```

### JavaScript

#### Структура файлов
```javascript
// Основной объект календаря
window.ArtMaxCalendar = {
    // Инициализация
    init: function() {
        this.bindEvents();
        this.loadEvents();
    },

    // Привязка событий
    bindEvents: function() {
        $(document).on('click', '.add-event-btn', this.openAddEventModal);
        $(document).on('submit', '#event-form', this.submitEventForm);
    },

    // AJAX запросы
    ajaxRequest: function(action, data, callback) {
        $.ajax({
            url: '/local/components/artmax/calendar/ajax.php',
            method: 'POST',
            data: Object.assign({action: action}, data),
            dataType: 'json',
            success: function(response) {
                if (callback) callback(response);
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
            }
        });
    }
};
```

### CSS

#### БЭМ методология
```css
/* Блок */
.artmax-calendar {
    display: flex;
    flex-direction: column;
}

/* Элемент */
.artmax-calendar__grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
}

/* Модификатор */
.artmax-calendar__grid--month-view {
    grid-template-rows: repeat(6, 1fr);
}

.artmax-calendar__grid--week-view {
    grid-template-rows: repeat(1, 1fr);
}
```

## 🔧 Рефакторинг и улучшения

### Рефакторинг параметров методов

#### Проблема
Методы с большим количеством параметров (14+) создают проблемы:
- Легко перепутать порядок параметров
- Сложно читать и поддерживать код
- Высокий риск ошибок при вызове

#### Решение
Использование массивов параметров с ключами:

```php
// ❌ Старый подход (14+ параметров)
public function addScheduleAction($title, $date, $time, $employeeId, $repeat, $frequency, $weekdays, $repeatEnd, $repeatCount, $repeatEndDate, $eventColor, $excludeWeekends, $excludeHolidays, $includeEndDate)

// ✅ Новый подход (массив параметров)
public function addScheduleAction($params)
{
    $title = $params['title'] ?? '';
    $date = $params['date'] ?? '';
    $time = $params['time'] ?? '';
    // ... остальные параметры
}
```

#### Преимущества
- **Безопасность**: Невозможно перепутать порядок параметров
- **Читаемость**: Понятно, какой параметр передается
- **Расширяемость**: Легко добавлять новые параметры
- **Поддержка**: Код проще понимать и изменять

#### Пример использования
```php
// Вызов метода с массивом параметров
$result = $component->addScheduleAction([
    'title' => 'Консультация',
    'date' => '2024-01-15',
    'time' => '10:00',
    'employee_id' => 1,
    'repeat' => true,
    'frequency' => 'daily',
    'weekdays' => [],
    'repeat_end' => 'date',
    'repeat_count' => null,
    'repeat_end_date' => '2024-01-20',
    'event_color' => '#3498db',
    'exclude_weekends' => true,
    'exclude_holidays' => true,
    'include_end_date' => false
]);
```

## 🔧 Разработка новых функций

### 1. Добавление нового API метода

#### Шаг 1: Добавить обработчик в компонент
```php
// В local/components/artmax/calendar/class.php
private function handleAjaxRequest()
{
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'newAction':
            $result = $this->newActionMethod(
                $_POST['param1'] ?? '',
                $_POST['param2'] ?? ''
            );
            break;
        // ... другие действия
    }
}

public function newActionMethod($param1, $param2)
{
    // Валидация
    if (!$GLOBALS['USER'] || !$GLOBALS['USER']->IsAuthorized()) {
        return ['success' => false, 'error' => 'Необходима авторизация'];
    }

    try {
        // Логика метода
        return ['success' => true, 'data' => $result];
    } catch (\Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
```

#### Шаг 2: Добавить JavaScript функцию
```javascript
// В template.js
ArtMaxCalendar.newAction = function(param1, param2) {
    this.ajaxRequest('newAction', {
        param1: param1,
        param2: param2
    }, function(response) {
        if (response.success) {
            // Обработка успешного ответа
            console.log('Данные:', response.data);
        } else {
            // Обработка ошибки
            alert('Ошибка: ' + response.error);
        }
    });
};
```

### 2. Добавление нового поля в базу данных

#### Шаг 1: Обновить структуру таблицы
```sql
-- В install/step.php или отдельном миграционном файле
ALTER TABLE artmax_calendar_events 
ADD COLUMN NEW_FIELD VARCHAR(255) DEFAULT NULL 
COMMENT 'Описание нового поля';
```

#### Шаг 2: Обновить класс Calendar
```php
// В lib/Calendar.php
public function addEvent($title, $description, $dateFrom, $dateTo, $userId, $branchId = 1, $eventColor = '#3498db', $employeeId = null, $newField = null)
{
    $sql = "INSERT INTO artmax_calendar_events (TITLE, DESCRIPTION, DATE_FROM, DATE_TO, USER_ID, BRANCH_ID, EVENT_COLOR, EMPLOYEE_ID, NEW_FIELD) VALUES ('" . 
           $this->connection->getSqlHelper()->forSql($title) . "', '" . 
           // ... другие поля
           $this->connection->getSqlHelper()->forSql($newField) . "')";
    
    // ... остальная логика
}
```

### 3. Добавление нового компонента

#### Шаг 1: Создать структуру компонента
```
local/components/artmax/newcomponent/
├── class.php
├── .description.php
├── .parameters.php
└── templates/
    └── .default/
        ├── template.php
        ├── style.css
        └── script.js
```

#### Шаг 2: Описать компонент
```php
// .description.php
<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$arComponentDescription = array(
    "NAME" => "Новый компонент",
    "DESCRIPTION" => "Описание нового компонента",
    "ICON" => "/images/icon.gif",
    "SORT" => 10,
    "PATH" => array(
        "ID" => "artmax",
        "NAME" => "ArtMax",
    ),
);
?>
```

## 🧪 Тестирование

### Unit тесты

```php
// tests/CalendarTest.php
class CalendarTest extends PHPUnit\Framework\TestCase
{
    private $calendar;

    protected function setUp(): void
    {
        $this->calendar = new \Artmax\Calendar\Calendar();
    }

    public function testAddEvent()
    {
        $eventId = $this->calendar->addEvent(
            'Тестовая запись',
            'Описание тестовой записи',
            '2024-01-15 10:00:00',
            '2024-01-15 11:00:00',
            1,
            1
        );

        $this->assertNotFalse($eventId);
        $this->assertIsInt($eventId);
    }

    public function testGetEvent()
    {
        $event = $this->calendar->getEvent(1);
        $this->assertIsArray($event);
        $this->assertArrayHasKey('TITLE', $event);
    }
}
```

### Integration тесты

```php
// tests/IntegrationTest.php
class IntegrationTest extends PHPUnit\Framework\TestCase
{
    public function testCalendarComponent()
    {
        // Тест работы компонента календаря
        $component = new ArtmaxCalendarComponent();
        $component->arParams = [
            'BRANCH_ID' => 1,
            'EVENTS_COUNT' => 10,
            'SHOW_FORM' => 'Y'
        ];
        
        $component->executeComponent();
        
        $this->assertArrayHasKey('BRANCH', $component->arResult);
        $this->assertArrayHasKey('EVENTS', $component->arResult);
    }
}
```

## 🐛 Отладка

### Логирование

```php
// Включение отладочного логирования
file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/debug_calendar_ajax.log', 
    "=== DEBUG START ===\n" . 
    "Timestamp: " . date('Y-m-d H:i:s') . "\n" . 
    "Action: " . $action . "\n" . 
    "Data: " . json_encode($data) . "\n", 
    FILE_APPEND | LOCK_EX);
```

### Отладка AJAX

```javascript
// Включение отладки AJAX запросов
$.ajaxSetup({
    beforeSend: function(xhr, settings) {
        console.log('AJAX Request:', settings.url, settings.data);
    },
    complete: function(xhr, status) {
        console.log('AJAX Response:', xhr.responseText);
    }
});
```

### Проверка базы данных

```sql
-- Проверка структуры таблиц
DESCRIBE artmax_calendar_events;

-- Проверка данных
SELECT COUNT(*) FROM artmax_calendar_events;
SELECT * FROM artmax_calendar_events ORDER BY CREATED_AT DESC LIMIT 10;

-- Проверка индексов
SHOW INDEX FROM artmax_calendar_events;
```

## 📦 Развертывание

### Подготовка к продакшену

1. **Оптимизация кода**
   ```php
   // Отключение отладочных логов
   if (!defined('DEBUG_MODE') || !DEBUG_MODE) {
       // Убрать все file_put_contents для отладки
   }
   ```

2. **Кеширование**
   ```php
   // Включение кеширования компонентов
   $this->arParams['CACHE_TYPE'] = 'A';
   $this->arParams['CACHE_TIME'] = 3600;
   ```

3. **Минификация ресурсов**
   ```bash
   # Минификация CSS
   cssnano style.css style.min.css
   
   # Минификация JavaScript
   uglifyjs script.js -o script.min.js
   ```

### Миграции

```php
// Создание миграционного файла
class Migration_001_AddNewField extends Migration
{
    public function up()
    {
        $connection = Application::getConnection();
        $connection->query("ALTER TABLE artmax_calendar_events ADD COLUMN NEW_FIELD VARCHAR(255)");
    }

    public function down()
    {
        $connection = Application::getConnection();
        $connection->query("ALTER TABLE artmax_calendar_events DROP COLUMN NEW_FIELD");
    }
}
```

## 🔒 Безопасность

### Валидация данных

```php
// Валидация входных данных
public function validateEventData($data)
{
    $errors = [];
    
    if (empty($data['title'])) {
        $errors[] = 'Название обязательно';
    }
    
    if (!strtotime($data['dateFrom'])) {
        $errors[] = 'Неверная дата начала';
    }
    
    if (!strtotime($data['dateTo'])) {
        $errors[] = 'Неверная дата окончания';
    }
    
    return $errors;
}
```

### Защита от SQL инъекций

```php
// Всегда используйте forSql()
$sql = "SELECT * FROM artmax_calendar_events WHERE TITLE = '" . 
       $this->connection->getSqlHelper()->forSql($title) . "'";
```

### Проверка прав доступа

```php
// Проверка прав на редактирование события
public function canEditEvent($eventId, $userId)
{
    $event = $this->getEvent($eventId);
    return $event && $event['USER_ID'] == $userId;
}
```

## 📚 Полезные ресурсы

### Документация Bitrix
- [Разработка модулей](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43)
- [Компоненты](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=37)
- [API](https://dev.1c-bitrix.ru/api_d7/)

### Инструменты разработки
- **IDE**: PhpStorm, VS Code
- **Отладчик**: Xdebug
- **Тестирование**: PHPUnit
- **Версионирование**: Git

### Лучшие практики
- Следование PSR стандартам
- Использование автозагрузки классов
- Применение принципов SOLID
- Написание тестов для критической функциональности

---

*Документ актуален на: 27.12.2025*  
*Версия разработки: 1.1*
