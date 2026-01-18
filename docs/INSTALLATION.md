# ⚙️ Установка и настройка "Клиника глазных болезней"

## 📋 Требования

### Системные требования
- **PHP**: 7.4 или выше
- **MySQL**: 5.7 или выше
- **Bitrix24**: Корпоративная версия или выше
- **Память**: минимум 256MB для PHP
- **Диск**: минимум 100MB свободного места

### Модули Bitrix24
- **CRM** - для работы с контактами и сделками
- **Intranet** - для настраиваемых разделов
- **Main** - базовый модуль

## 🚀 Установка модуля

### 1. Подготовка файлов

Убедитесь, что все файлы модуля находятся в правильных директориях:

```
local/modules/artmax.calendar/
├── install/
│   ├── index.php
│   ├── step.php
│   └── unstep.php
├── lib/
├── admin/
└── components/
```

### 2. Установка через админ-панель

1. Войдите в админ-панель Bitrix24
2. Перейдите в **Настройки** → **Настройки продукта** → **Модули**
3. Найдите модуль **"ArtMax Calendar"**
4. Нажмите **"Установить"**
5. Дождитесь завершения установки

### 3. Проверка установки

После установки проверьте:

- ✅ Модуль появился в списке установленных
- ✅ Созданы таблицы в базе данных
- ✅ Скопированы файлы компонентов
- ✅ Созданы административные ссылки

## 🗄️ Настройка базы данных

### Автоматическое создание таблиц

При установке автоматически создаются следующие таблицы:

```sql
-- События/записи
artmax_calendar_events

-- Филиалы клиники  
artmax_calendar_branches

-- Связь филиалов и сотрудников
artmax_calendar_branch_employees

-- Настройки часовых поясов
artmax_calendar_timezone_settings
```

### Ручная настройка (если необходимо)

Если автоматическое создание не сработало, выполните SQL скрипт:

```sql
-- Создание таблицы событий
CREATE TABLE IF NOT EXISTS artmax_calendar_events (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    TITLE VARCHAR(255) NOT NULL,
    DESCRIPTION TEXT,
    DATE_FROM DATETIME NOT NULL,
    DATE_TO DATETIME NOT NULL,
    ORIGINAL_DATE_FROM DATETIME DEFAULT NULL,
    ORIGINAL_DATE_TO DATETIME DEFAULT NULL,
    TIME_IS_CHANGED TINYINT(1) DEFAULT 0,
    USER_ID INT NOT NULL,
    BRANCH_ID INT NOT NULL DEFAULT 1,
    EVENT_COLOR VARCHAR(7) DEFAULT '#3498db',
    CONTACT_ENTITY_ID INT DEFAULT NULL,
    DEAL_ENTITY_ID INT DEFAULT NULL,
    NOTE TEXT DEFAULT NULL,
    EMPLOYEE_ID INT DEFAULT NULL,
    CONFIRMATION_STATUS ENUM('pending','confirmed','not_confirmed') DEFAULT 'pending',
    STATUS ENUM('active','moved','cancelled') DEFAULT 'active',
    VISIT_STATUS ENUM('not_specified','client_came','client_did_not_come') DEFAULT 'not_specified',
    CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UPDATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_date (USER_ID, DATE_FROM),
    INDEX idx_branch_date (BRANCH_ID, DATE_FROM),
    INDEX idx_date_range (DATE_FROM, DATE_TO),
    INDEX idx_contact_entity (CONTACT_ENTITY_ID),
    INDEX idx_deal_entity (DEAL_ENTITY_ID),
    INDEX idx_employee (EMPLOYEE_ID),
    INDEX idx_confirmation_status (CONFIRMATION_STATUS),
    INDEX idx_status (STATUS),
    INDEX idx_visit_status (VISIT_STATUS),
    INDEX idx_time_changed (TIME_IS_CHANGED)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Создание таблицы филиалов
CREATE TABLE IF NOT EXISTS artmax_calendar_branches (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    NAME VARCHAR(255) NOT NULL,
    ADDRESS TEXT,
    PHONE VARCHAR(50),
    EMAIL VARCHAR(100),
    TIMEZONE VARCHAR(50) DEFAULT 'Europe/Moscow',
    TIMEZONE_OFFSET INT DEFAULT 3,
    IS_ACTIVE TINYINT(1) DEFAULT 1,
    CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UPDATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Создание таблицы связи филиалов и сотрудников
CREATE TABLE IF NOT EXISTS artmax_calendar_branch_employees (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    BRANCH_ID INT NOT NULL,
    EMPLOYEE_ID INT NOT NULL,
    CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_branch_employee (BRANCH_ID, EMPLOYEE_ID),
    FOREIGN KEY (BRANCH_ID) REFERENCES artmax_calendar_branches(ID) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Создание таблицы настроек часовых поясов
CREATE TABLE IF NOT EXISTS artmax_calendar_timezone_settings (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    BRANCH_ID INT NOT NULL,
    TIMEZONE_NAME VARCHAR(50) NOT NULL,
    TIMEZONE_OFFSET INT NOT NULL,
    DST_ENABLED TINYINT(1) DEFAULT 1,
    DST_START_MONTH TINYINT DEFAULT 3,
    DST_START_DAY TINYINT DEFAULT 31,
    DST_START_HOUR TINYINT DEFAULT 2,
    DST_END_MONTH TINYINT DEFAULT 10,
    DST_END_DAY TINYINT DEFAULT 27,
    DST_END_HOUR TINYINT DEFAULT 3,
    IS_ACTIVE TINYINT(1) DEFAULT 1,
    CREATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UPDATED_AT TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_branch_timezone (BRANCH_ID),
    FOREIGN KEY (BRANCH_ID) REFERENCES artmax_calendar_branches(ID) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 🏢 Настройка филиалов

### 1. Создание первого филиала

После установки автоматически создается филиал по умолчанию. Для настройки:

1. Перейдите в **Настройки** → **Модули** → **ArtMax Calendar**
2. Выберите **"Управление филиалами"**
3. Отредактируйте данные филиала:
   - Название
   - Адрес
   - Телефон
   - Email
   - Часовой пояс

### 2. Добавление дополнительных филиалов

```php
// Пример создания филиала через API
$branchData = [
    'action' => 'addBranch',
    'name' => 'Филиал №2',
    'address' => 'г. Санкт-Петербург, ул. Другая, д. 2',
    'phone' => '+7 (812) 987-65-43',
    'email' => 'spb@example.com'
];
```

### 3. Настройка часовых поясов

Для каждого филиала настройте часовой пояс:

- **Москва**: Europe/Moscow (UTC+3)
- **Екатеринбург**: Asia/Yekaterinburg (UTC+5)
- **Новосибирск**: Asia/Novosibirsk (UTC+7)
- **Владивосток**: Asia/Vladivostok (UTC+10)

## 👥 Настройка сотрудников

### 1. Назначение сотрудников к филиалам

1. Перейдите в настройки филиала
2. Выберите **"Сотрудники филиала"**
3. Добавьте нужных сотрудников из списка пользователей Bitrix24

### 2. Проверка прав доступа

Убедитесь, что сотрудники имеют права:
- Доступ к модулю календаря
- Создание и редактирование записей
- Просмотр расписания

### 3. Настройка прав доступа к файлам Bitrix

**⚠️ ВАЖНО**: При создании новых групп пользователей для календаря необходимо вручную выдать права на чтение файлов Bitrix:

1. Перейдите в **Настройки** → **Настройки продукта** → **Права доступа**
2. Выберите созданную группу
3. Назначьте права **"Чтение" (R)** для следующих папок:
   - `/page/` - доступ к страницам календаря
   - `/local/components/artmax/` - доступ к компонентам календаря
   - `/local/modules/artmax.calendar/` - доступ к модулю календаря

**Примечание**: Без этих прав пользователи из созданных групп не смогут войти в систему и просматривать календарь.

Альтернативный способ через административный интерфейс:
1. **Настройки** → **Настройки продукта** → **Управление структурой**
2. Выберите нужную папку (`/page/`, `/local/components/artmax/`, `/local/modules/artmax.calendar/`)
3. Нажмите **"Права доступа"**
4. Добавьте группу с правом **"Чтение"**

## 🔧 Настройка компонента

### 1. Добавление на страницу

```php
<?$APPLICATION->IncludeComponent(
    "artmax:calendar",
    "",
    Array(
        "CACHE_TYPE" => "A",
        "CACHE_TIME" => "3600",
        "EVENTS_COUNT" => "20",
        "SHOW_FORM" => "Y",
        "BRANCH_ID" => "1"
    )
);?>
```

### 2. Параметры компонента

- **CACHE_TYPE**: Тип кеширования (A - авто)
- **CACHE_TIME**: Время кеширования в секундах
- **EVENTS_COUNT**: Количество событий для отображения
- **SHOW_FORM**: Показывать форму добавления (Y/N)
- **BRANCH_ID**: ID филиала по умолчанию

## 🎨 Настройка внешнего вида

### 1. CSS стили

Файл стилей: `/local/css/artmax.calendar/style.css`

Основные классы:
- `.artmax-calendar` - контейнер календаря
- `.calendar-grid` - сетка календаря
- `.event-item` - элемент события
- `.modal` - модальные окна

### 2. JavaScript

Файл скриптов: `/local/js/artmax-calendar/script.js`

Основные функции:
- `initCalendar()` - инициализация календаря
- `openAddEventModal()` - открытие формы добавления
- `loadEvents()` - загрузка событий

## 🔍 Проверка работоспособности

### 1. Тест создания записи

1. Откройте страницу с календарем
2. Нажмите на свободное время
3. Заполните форму записи
4. Сохраните запись
5. Проверьте появление в календаре

### 2. Тест интеграции CRM

1. Создайте запись с привязкой к контакту
2. Проверьте создание контакта в CRM
3. Проверьте создание сделки (если настроено)

### 3. Тест часовых поясов

1. Создайте филиалы в разных часовых поясах
2. Создайте записи в разных филиалах
3. Проверьте корректность отображения времени

## 🚨 Устранение неполадок

### Проблема: Модуль не устанавливается

**Решение:**
- Проверьте права доступа к папкам
- Убедитесь в корректности файлов модуля
- Проверьте логи ошибок Bitrix24

### Проблема: Таблицы не создаются

**Решение:**
- Проверьте права доступа к базе данных
- Выполните SQL скрипты вручную
- Проверьте версию MySQL

### Проблема: Компонент не отображается

**Решение:**
- Проверьте подключение модуля
- Убедитесь в корректности параметров компонента
- Проверьте кеширование

### Проблема: AJAX запросы не работают

**Решение:**
- Проверьте права доступа к файлу ajax.php
- Убедитесь в корректности URL
- Проверьте JavaScript консоль на ошибки

## 📊 Мониторинг

### Логи системы

- **Основные логи**: `/bitrix/modules/main/classes/general/event_log.php`
- **Отладочные логи**: `/debug_calendar_ajax.log`
- **Логи копирования**: `/copy_error.log`

### Проверка работоспособности

Регулярно проверяйте:
- Создание новых записей
- Отображение календаря
- Работу поиска клиентов
- Синхронизацию с CRM

## 🔄 Обновление

### Обновление модуля

1. Сделайте резервную копию базы данных
2. Обновите файлы модуля
3. Переустановите модуль через админ-панель
4. Проверьте работоспособность

### Миграция данных

При обновлении структуры базы данных:
1. Создайте скрипт миграции
2. Протестируйте на копии данных
3. Выполните миграцию на продакшене
4. Проверьте целостность данных

---

*Документ актуален на: 27.12.2025*  
*Версия установки: 1.1*
