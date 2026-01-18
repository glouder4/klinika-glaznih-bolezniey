# 🔐 Документация по правам доступа

Документация по системе прав доступа модуля `artmax.calendar`.

## 📋 Содержание

1. [Обзор системы прав](#обзор-системы-прав)
2. [Список всех прав](#список-всех-прав)
3. [Реализованные права](#реализованные-права)
4. [Не реализованные права](#не-реализованные-права)
5. [Использование в коде](#использование-в-коде)
6. [Рекомендации по реализации](#рекомендации-по-реализации)

---

## Обзор системы прав

Система прав доступа позволяет контролировать доступ пользователей к различным функциям календаря. Права назначаются на уровне групп пользователей или индивидуально пользователям.

### Структура прав

Права имеют иерархическую структуру и называются в формате: `calendar.{действие}`.

Примеры:
- `calendar.view` - просмотр календаря
- `calendar.create` - создание записи
- `calendar.edit` - редактирование записи

---

## Список всех прав

### ✅ Реализованные права

| Код права | Название | Описание | Статус |
|-----------|----------|----------|--------|
| `calendar.view_others` | Просмотр чужих записей | Право на просмотр записей других врачей | ✅ **Реализовано** |
| `calendar.create` | Создание записи | Право на создание новых записей в календаре | ✅ **Реализовано** |
| `calendar.manage_groups` | Управление группами и правами | Право на создание групп пользователей и управление правами доступа | ✅ **Реализовано** |
| `calendar.change_employee` | Смена ответственного врача | Право на смену ответственного врача в записи | ✅ **Реализовано** |

### ⚠️ Частично реализованные права

| Код права | Название | Описание | Статус |
|-----------|----------|----------|--------|
| `calendar.edit` | Редактирование записи | Право на редактирование существующих записей | ⚠️ **Частично** (только проверка USER_ID) |
| `calendar.delete` | Удаление записи | Право на удаление записей из календаря | ⚠️ **Частично** (только проверка USER_ID) |

### ❌ Не реализованные права

| Код права | Название | Описание | Статус |
|-----------|----------|----------|--------|
| `calendar.manage_employees` | Управление сотрудниками | Право на управление сотрудниками | ❌ **Не реализовано** |

**Примечание:** Право `calendar.view` было удалено, так как не использовалось. Для контроля доступа к просмотру записей используется право `calendar.view_others`.

---

## Реализованные права

### `calendar.view_others` - Просмотр чужих записей

**Статус:** ✅ Полностью реализовано

**Описание:** Позволяет пользователю просматривать записи других врачей в календаре. Без этого права пользователь видит только свои записи.

**Где используется:**
- `local/components/artmax/calendar/class.php` - фильтрация событий при загрузке календаря
- `local/components/artmax/calendar/templates/.default/template.php` - отображение переключателя "Все записи / Мои записи"

**Пример использования:**

```php
// Проверка права
$permissionsObj = new \Artmax\Calendar\Permissions();
$hasViewOthersPermission = $permissionsObj->hasPermission($userId, 'calendar.view_others');

if ($hasViewOthersPermission) {
    // Показать все записи
    $events = $calendarObj->getEvents($branchId, $dateFrom, $dateTo, null);
} else {
    // Показать только свои записи
    $events = $calendarObj->getEvents($branchId, $dateFrom, $dateTo, $userId);
}
```

**JavaScript:**
```javascript
// Право передается в window.HAS_VIEW_OTHERS_PERMISSION
if (window.HAS_VIEW_OTHERS_PERMISSION) {
    // Показать переключатель "Все записи / Мои записи"
}
```

---

### `calendar.create` - Создание записи

**Статус:** ✅ Полностью реализовано

**Описание:** Позволяет пользователю создавать новые записи в календаре. Без этого права клик по календарю не открывает форму создания события.

**Где используется:**
- `local/components/artmax/calendar/class.php` - передача права в шаблон
- `local/components/artmax/calendar/templates/.default/template.php` - передача права в JavaScript
- `local/components/artmax/calendar/templates/.default/script.js` - проверка права перед открытием формы

**Пример использования:**

```php
// Проверка права в PHP
$permissionsObj = new \Artmax\Calendar\Permissions();
$hasCreatePermission = $permissionsObj->hasPermission($userId, 'calendar.create');

// Передача в шаблон
$this->arResult['HAS_CREATE_PERMISSION'] = $hasCreatePermission;
```

**JavaScript:**
```javascript
// Проверка права перед открытием формы
function openEventForm(date) {
    // Проверяем право на создание событий
    if (!window.HAS_CREATE_PERMISSION) {
        console.log('User does not have permission to create events');
        return;
    }
    
    // Открываем форму создания события
    // ...
}
```

---

### `calendar.manage_groups` - Управление группами и правами

**Статус:** ✅ Полностью реализовано

**Описание:** Позволяет пользователю управлять группами пользователей и назначать права доступа. Право проверяется как в административной панели Bitrix, так и в публичной части календаря.

**Где используется:**
- `local/modules/artmax.calendar/install/admin/artmax_calendar_permissions.php` - проверка доступа к странице управления правами
- `local/modules/artmax.calendar/install/admin/artmax_calendar_settings.php` - проверка доступа к настройкам
- `local/components/artmax/calendar/class.php` - проверка права и передача в шаблон
- `local/components/artmax/calendar/templates/.default/template.php` - отображение кнопки "Группы и права" в интерфейсе календаря

**UI элементы:**
- **В админ-панели:** Пункт меню "Группы и права" в панельной кнопке настроек (через `Toolbar::addButton()`)
- **В публичной части календаря:** Кнопка "Группы и права" в верхней части календаря (справа от фильтра врачей)

**Пример использования:**

```php
// Проверка права на управление группами
$permissionsObj = new \Artmax\Calendar\Permissions();
$hasManageGroupsPermission = $permissionsObj->hasPermission($USER->GetID(), 'calendar.manage_groups') || $USER->IsAdmin();

// Передача права в шаблон
$this->arResult['HAS_MANAGE_GROUPS_PERMISSION'] = $hasManageGroupsPermission;
```

**В шаблоне:**
```php
<?php if ($hasManageGroups): ?>
<div class="calendar-admin-actions">
    <button 
        class="btn-manage-groups" 
        onclick="window.location.href='/bitrix/admin/artmax.calendar_artmax_calendar_permissions.php?lang=<?= LANGUAGE_ID ?>'"
        title="Управление группами пользователей и правами доступа"
    >
        <span>⚙️</span>
        <span>Группы и права</span>
    </button>
</div>
<?php endif; ?>
```

**JavaScript:**
```javascript
// Право передается в window.HAS_MANAGE_GROUPS_PERMISSION
if (window.HAS_MANAGE_GROUPS_PERMISSION) {
    // Пользователь имеет право управлять группами
}
```

---

### `calendar.change_employee` - Смена ответственного врача

**Статус:** ✅ Полностью реализовано

**Описание:** Позволяет пользователю изменять ответственного врача в записи календаря. Без этого права карточка "Ответственный врач" и кнопка назначения врача не отображаются в интерфейсе.

**Где используется:**
- `local/components/artmax/calendar/class.php` - проверка права и передача в шаблон
- `local/components/artmax/calendar/templates/.default/template.php` - условное отображение карточки врача и передача права в JavaScript
- `local/components/artmax/calendar/ajax.php` - проверка права перед выполнением действия `assignDoctor`

**UI элементы:**
- **Карточка "Ответственный врач"** в боковой панели события - показывается только пользователям с правом `calendar.change_employee`
- **Кнопка "Назначить врача"** - показывается только пользователям с правом `calendar.change_employee`

**Пример использования:**

```php
// Проверка права на смену ответственного врача
$permissionsObj = new \Artmax\Calendar\Permissions();
$hasChangeEmployeePermission = $permissionsObj->hasPermission($USER->GetID(), 'calendar.change_employee') || $USER->IsAdmin();

// Передача права в шаблон
$this->arResult['HAS_CHANGE_EMPLOYEE_PERMISSION'] = $hasChangeEmployeePermission;
```

**В шаблоне:**
```php
<?php 
$hasChangeEmployee = isset($arResult['HAS_CHANGE_EMPLOYEE_PERMISSION']) ? $arResult['HAS_CHANGE_EMPLOYEE_PERMISSION'] : false;
if ($hasChangeEmployee): 
?>
    <div class="action-card" id="employee-card" onclick="openEmployeeDetails()">
        <!-- Карточка врача -->
    </div>
<?php endif; ?>
```

**JavaScript:**
```javascript
// Право передается в window.HAS_CHANGE_EMPLOYEE_PERMISSION
if (window.HAS_CHANGE_EMPLOYEE_PERMISSION) {
    // Пользователь имеет право менять ответственного врача
}
```

**AJAX проверка:**
```php
// В ajax.php при обработке assignDoctor
$permissionsObj = new \Artmax\Calendar\Permissions();
$hasChangeEmployeePermission = $permissionsObj->hasPermission($USER->GetID(), 'calendar.change_employee') || $USER->IsAdmin();

if (!$hasChangeEmployeePermission) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Нет прав на смену ответственного врача']));
}
```

---

## Не реализованные права

### `calendar.view` - Просмотр календаря

**Статус:** ❌ Не реализовано

**Описание:** Предполагается для контроля доступа к просмотру календаря в целом. Сейчас доступ к календарю не контролируется этим правом.

**Рекомендации по реализации:**
- Добавить проверку права при инициализации компонента календаря
- Если право отсутствует, показывать сообщение о недостатке прав вместо календаря

---

### `calendar.edit` - Редактирование записи

**Статус:** ⚠️ Частично реализовано (только проверка USER_ID)

**Описание:** Сейчас редактирование записей ограничено только проверкой - является ли пользователь автором записи. Система прав `calendar.edit` не используется.

**Где проверяется сейчас:**
- `local/components/artmax/calendar/ajax.php` - проверка `$event['USER_ID'] != $GLOBALS['USER']->GetID()`

**Рекомендации по реализации:**
```php
// Вместо:
if ($event['USER_ID'] != $USER->GetID()) {
    return ['success' => false, 'error' => 'Нет прав на редактирование'];
}

// Использовать:
$permissionsObj = new \Artmax\Calendar\Permissions();
$canEdit = $event['USER_ID'] == $USER->GetID() || 
           $permissionsObj->hasPermission($USER->GetID(), 'calendar.edit') ||
           $USER->IsAdmin();

if (!$canEdit) {
    return ['success' => false, 'error' => 'Нет прав на редактирование'];
}
```

---

### `calendar.delete` - Удаление записи

**Статус:** ⚠️ Частично реализовано (только проверка USER_ID)

**Описание:** Сейчас удаление записей ограничено только проверкой - является ли пользователь автором записи. Система прав `calendar.delete` не используется.

**Где проверяется сейчас:**
- `local/components/artmax/calendar/ajax.php` - проверка `$event['USER_ID'] != $GLOBALS['USER']->GetID()`

**Рекомендации по реализации:**
Аналогично `calendar.edit` - добавить проверку права через `Permissions::hasPermission()`.

---

### `calendar.move` - Перемещение записи

**Статус:** ❌ Не реализовано

**Описание:** Предполагается для контроля доступа к функции переноса записей в календаре (drag-and-drop или форма переноса).

**Рекомендации по реализации:**
- Добавить проверку права в обработчик `moveEvent` в `ajax.php`
- Скрывать кнопку/функцию переноса в интерфейсе, если право отсутствует

---

### `calendar.confirm` - Подтверждение записи

**Статус:** ❌ Не реализовано

**Описание:** Предполагается для контроля доступа к функции подтверждения записей (изменение статуса `CONFIRMATION_STATUS`).

**Рекомендации по реализации:**
- Добавить проверку права в обработчик `updateConfirmationStatus` в `ajax.php`
- Скрывать кнопку подтверждения в интерфейсе, если право отсутствует

---

### `calendar.manage_schedule` - Управление расписанием

**Статус:** ❌ Не реализовано

**Описание:** Предполагается для контроля доступа к функциям управления расписанием врачей (создание, редактирование, удаление расписаний).

**Рекомендации по реализации:**
- Добавить проверку права в обработчики создания/редактирования расписаний
- Скрывать кнопку "Создать расписание", если право отсутствует

---

### `calendar.manage_branches` - Управление филиалами

**Статус:** ❌ Не реализовано

**Описание:** Предполагается для контроля доступа к функциям управления филиалами клиники (создание, редактирование, удаление филиалов).

**Рекомендации по реализации:**
- Добавить проверку права в обработчики управления филиалами
- Скрывать кнопку "Создать филиал", если право отсутствует

---

### `calendar.manage_employees` - Управление сотрудниками

**Статус:** ❌ Не реализовано

**Описание:** Предполагается для контроля доступа к функциям управления сотрудниками (добавление, редактирование, удаление сотрудников).

**Рекомендации по реализации:**
- Добавить проверку права в обработчики управления сотрудниками
- Скрывать интерфейс управления сотрудниками, если право отсутствует

---

## Использование в коде

### Проверка прав в PHP

```php
use Artmax\Calendar\Permissions;

// Создание объекта для работы с правами
$permissionsObj = new Permissions();

// Проверка права
$hasPermission = $permissionsObj->hasPermission($userId, 'calendar.create');

// Проверка с учетом администратора Bitrix
$hasPermission = $permissionsObj->hasPermission($userId, 'calendar.create') || $USER->IsAdmin();

// Передача права в шаблон
$this->arResult['HAS_CREATE_PERMISSION'] = $hasPermission;
```

### Проверка прав в JavaScript

```javascript
// Права передаются через window объект
if (window.HAS_CREATE_PERMISSION) {
    // Пользователь имеет право создавать записи
}

// Проверка перед открытием формы
function openEventForm(date) {
    if (!window.HAS_CREATE_PERMISSION) {
        console.log('Permission denied');
        return;
    }
    // Открываем форму
}
```

### Проверка прав в AJAX обработчиках

```php
// В ajax.php
$permissionsObj = new \Artmax\Calendar\Permissions();

// Проверка права перед выполнением действия
if (!$permissionsObj->hasPermission($USER->GetID(), 'calendar.edit')) {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Нет прав на редактирование']));
}
```

---

## Рекомендации по реализации

### Приоритет реализации

1. **Высокий приоритет:**
   - `calendar.edit` - полная реализация (сейчас только проверка USER_ID)
   - `calendar.delete` - полная реализация (сейчас только проверка USER_ID)

2. **Средний приоритет:**
   - `calendar.move` - для функции переноса записей
   - `calendar.confirm` - для функции подтверждения записей
   - `calendar.view` - для общего контроля доступа к календарю

3. **Низкий приоритет:**
   - `calendar.manage_schedule` - если нужен раздельный контроль
   - `calendar.manage_branches` - если нужен раздельный контроль
   - `calendar.manage_employees` - если нужен раздельный контроль

### Паттерн проверки прав

Рекомендуемый паттерн для проверки прав:

```php
/**
 * Проверка права с учетом админа и владельца
 */
private function canPerformAction($userId, $eventUserId, $permissionCode) {
    global $USER;
    
    // Админы Bitrix имеют все права
    if ($USER->IsAdmin()) {
        return true;
    }
    
    // Владелец события всегда может редактировать/удалять свое событие
    if ($userId == $eventUserId) {
        return true;
    }
    
    // Проверяем специальное право
    $permissionsObj = new \Artmax\Calendar\Permissions();
    return $permissionsObj->hasPermission($userId, $permissionCode);
}
```

### Удаление неиспользуемых прав

Если право не планируется реализовывать в обозримом будущем, его можно удалить из:
- `local/modules/artmax.calendar/install/index.php` - метод `createDefaultPermissions()`
- `local/modules/artmax.calendar/install/index.php` - метод `assignDefaultPermissions()`

**Внимание:** Удаление прав из кода установки не удалит их из базы данных. Для удаления из БД нужно будет создать миграцию или удалить вручную.

---

## Заключение

Система прав доступа находится в процессе разработки. На данный момент полностью реализованы 3 права из 12. Рекомендуется:

1. ✅ Полностью реализовать `calendar.edit` и `calendar.delete`
2. ⚠️ Рассмотреть удаление неиспользуемых прав или их реализацию
3. 📝 Обновлять эту документацию при добавлении новых прав

---

*Обновлено: 28.12.2025*  
*Версия документации: 1.0*
