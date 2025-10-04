<?php
/**
 * Шаг установки модуля ArtMax Calendar
 * Создание таблиц и начальных данных
 */

use Bitrix\Main\Application;
use Bitrix\Main\DB\Connection;

// Подключаем Bitrix
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

// Создаем подключение к базе данных
$connection = Application::getConnection();

echo "<h2>Установка модуля ArtMax Calendar</h2>";

// 1. Создаем таблицу филиалов
$sql = "
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
";

try {
    $connection->query($sql);
    echo "<p style='color: green;'>✓ Таблица филиалов создана успешно</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Ошибка создания таблицы филиалов: " . $e->getMessage() . "</p>";
}

// 2. Создаем таблицу событий (если не существует)
$sql = "
CREATE TABLE IF NOT EXISTS artmax_calendar_events (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    TITLE VARCHAR(255) NOT NULL,
    DESCRIPTION TEXT,
    DATE_FROM DATETIME NOT NULL,
    DATE_TO DATETIME NOT NULL,
    ORIGINAL_DATE_FROM DATETIME DEFAULT NULL COMMENT 'Оригинальная дата начала (заполняется только при создании)',
    ORIGINAL_DATE_TO DATETIME DEFAULT NULL COMMENT 'Оригинальная дата окончания (заполняется только при создании)',
    TIME_IS_CHANGED TINYINT(1) DEFAULT 0 COMMENT 'Флаг изменения времени записи',
    USER_ID INT NOT NULL,
    BRANCH_ID INT NOT NULL DEFAULT 1,
    EVENT_COLOR VARCHAR(7) DEFAULT '#3498db',
    CONTACT_ENTITY_ID INT DEFAULT NULL COMMENT 'ID контакта из CRM',
    DEAL_ENTITY_ID INT DEFAULT NULL COMMENT 'ID сделки из CRM',
    NOTE TEXT DEFAULT NULL COMMENT 'Заметка к событию',
    EMPLOYEE_ID INT DEFAULT NULL COMMENT 'ID ответственного сотрудника',
    CONFIRMATION_STATUS ENUM('pending','confirmed','not_confirmed') DEFAULT 'pending' COMMENT 'Статус подтверждения события',
    STATUS ENUM('active','moved','cancelled') DEFAULT 'active' COMMENT 'Статус события',
    VISIT_STATUS ENUM('not_specified','client_came','client_did_not_come') DEFAULT 'not_specified' COMMENT 'Статус визита клиента',
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
";

try {
    $connection->query($sql);
    echo "<p style='color: green;'>✓ Таблица событий создана успешно</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Ошибка создания таблицы событий: " . $e->getMessage() . "</p>";
}

// 2.1. Проверяем и добавляем колонку VISIT_STATUS, если она отсутствует
$sqlCheck = "SHOW COLUMNS FROM artmax_calendar_events LIKE 'VISIT_STATUS'";
try {
    $result = $connection->query($sqlCheck);
    
    if ($result->getSelectedRowsCount() == 0) {
        echo "<p style='color: orange;'>⚠ Колонка VISIT_STATUS не найдена. Добавляем...</p>";
        
        $sqlAddColumn = "
        ALTER TABLE artmax_calendar_events 
        ADD COLUMN VISIT_STATUS ENUM('not_specified','client_came','client_did_not_come') 
        DEFAULT 'not_specified' COMMENT 'Статус визита клиента' 
        AFTER STATUS
        ";
        
        $connection->query($sqlAddColumn);
        echo "<p style='color: green;'>✓ Колонка VISIT_STATUS добавлена</p>";
        
        // Добавляем индекс для колонки
        $sqlAddIndex = "ALTER TABLE artmax_calendar_events ADD INDEX idx_visit_status (VISIT_STATUS)";
        $connection->query($sqlAddIndex);
        echo "<p style='color: green;'>✓ Индекс для колонки VISIT_STATUS добавлен</p>";
    } else {
        echo "<p style='color: green;'>✓ Колонка VISIT_STATUS уже существует</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Ошибка при проверке/добавлении колонки VISIT_STATUS: " . $e->getMessage() . "</p>";
}

// 3. Добавляем внешний ключ для BRANCH_ID
$sql = "
ALTER TABLE artmax_calendar_events 
ADD CONSTRAINT fk_events_branch 
FOREIGN KEY (BRANCH_ID) REFERENCES artmax_calendar_branches(ID) 
ON DELETE CASCADE ON UPDATE CASCADE;
";

try {
    $connection->query($sql);
    echo "<p style='color: green;'>✓ Внешний ключ для филиалов добавлен успешно</p>";
} catch (Exception $e) {
    // Игнорируем ошибку, если ключ уже существует
    if (strpos($e->getMessage(), 'Duplicate key name') === false) {
        echo "<p style='color: orange;'>⚠ Внешний ключ уже существует или ошибка: " . $e->getMessage() . "</p>";
    } else {
        echo "<p style='color: green;'>✓ Внешний ключ для филиалов уже существует</p>";
    }
}

// 4. Добавляем начальные данные для филиалов
$sql = "
INSERT IGNORE INTO artmax_calendar_branches (ID, NAME, ADDRESS, PHONE, EMAIL, TIMEZONE, TIMEZONE_OFFSET) VALUES
(1, 'Главный филиал', 'г. Москва, ул. Примерная, д. 1', '+7 (495) 123-45-67', 'main@example.com', 'Europe/Moscow', 3),
(2, 'Филиал №2', 'г. Санкт-Петербург, ул. Другая, д. 2', '+7 (812) 987-65-43', 'spb@example.com', 'Europe/Moscow', 3),
(3, 'Филиал №3', 'г. Екатеринбург, ул. Третья, д. 3', '+7 (343) 555-44-33', 'ekb@example.com', 'Asia/Yekaterinburg', 5),
(4, 'Филиал №4', 'г. Новосибирск, ул. Четвертая, д. 4', '+7 (383) 777-66-55', 'nsk@example.com', 'Asia/Novosibirsk', 7),
(5, 'Филиал №5', 'г. Владивосток, ул. Пятая, д. 5', '+7 (423) 999-88-77', 'vlad@example.com', 'Asia/Vladivostok', 10);
";

try {
    $connection->query($sql);
    echo "<p style='color: green;'>✓ Начальные данные филиалов добавлены успешно</p>";
} catch (Exception $e) {
    echo "<p style='color: orange;'>⚠ Ошибка добавления начальных данных филиалов: " . $e->getMessage() . "</p>";
}

// 5. Создаем таблицу настроек часовых поясов
$sql = "
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
";

try {
    $connection->query($sql);
    echo "<p style='color: green;'>✓ Таблица настроек часовых поясов создана успешно</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Ошибка создания таблицы настроек часовых поясов: " . $e->getMessage() . "</p>";
}

// 6. Добавляем начальные настройки часовых поясов
$sql = "
INSERT IGNORE INTO artmax_calendar_timezone_settings (BRANCH_ID, TIMEZONE_NAME, TIMEZONE_OFFSET, DST_ENABLED) VALUES
(1, 'Europe/Moscow', 3, 1),
(2, 'Europe/Moscow', 3, 1),
(3, 'Asia/Yekaterinburg', 5, 1),
(4, 'Asia/Novosibirsk', 7, 1),
(5, 'Asia/Vladivostok', 10, 1);
";

try {
    $connection->query($sql);
    echo "<p style='color: green;'>✓ Начальные настройки часовых поясов добавлены успешно</p>";
} catch (Exception $e) {
    echo "<p style='color: orange;'>⚠ Ошибка добавления настроек часовых поясов: " . $e->getMessage() . "</p>";
}

echo "<h3>Установка завершена!</h3>";
echo "<p>Модуль ArtMax Calendar успешно установлен со следующими возможностями:</p>";
echo "<ul>";
echo "<li>Управление филиалами с индивидуальными часовыми поясами</li>";
echo "<li>Автоматическое преобразование времени между часовыми поясами</li>";
echo "<li>Поддержка летнего/зимнего времени</li>";
echo "<li>Гибкая настройка для каждого филиала</li>";
echo "</ul>";

echo "<p><strong>Следующие шаги:</strong></p>";
echo "<ol>";
echo "<li>Настройте часовые пояса для ваших филиалов в админ-панели</li>";
echo "<li>Проверьте корректность отображения времени в календаре</li>";
echo "<li>При необходимости скорректируйте настройки DST</li>";
echo "</ol>";
?> 