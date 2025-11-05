<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$eventId = $arParams["EVENT_ID"] ?? 0;
$journalEntries = $arResult["JOURNAL_ENTRIES"] ?? [];
$event = $arResult["EVENT"] ?? null;

// Карта действий для отображения понятных названий
$actionLabels = [
    'CREATED_BY_CUSTOM' => 'Создано вручную',
    'CREATED_BY_SCHEDULE' => 'Создано из расписания',
    'CONTACT_ATTACHED' => 'Клиент привязан',
    'CONTACT_DETACHED' => 'Клиент отвязан',
    'DEAL_ATTACHED' => 'Сделка привязана',
    'DEAL_DETACHED' => 'Сделка отвязана',
    'EMPLOYEE_CHANGED' => 'Изменен ответственный врач',
    'CONFIRMATION_STATUS_CHANGED' => 'Изменен статус подтверждения',
    'VISIT_STATUS_CHANGED' => 'Изменен статус визита',
    'EVENT_TITLE_CHANGED' => 'Изменено название',
    'EVENT_DESCRIPTION_CHANGED' => 'Изменено описание',
    'EVENT_DATE_FROM_CHANGED' => 'Изменено время начала',
    'EVENT_DATE_TO_CHANGED' => 'Изменено время окончания',
    'EVENT_COLOR_CHANGED' => 'Изменен цвет',
    'EVENT_BRANCH_CHANGED' => 'Изменен филиал',
    'EVENT_EMPLOYEE_CHANGED' => 'Изменен врач',
    'EVENT_MOVED_DATE_FROM' => 'Перенос: изменено время начала',
    'EVENT_MOVED_DATE_TO' => 'Перенос: изменено время окончания',
    'EVENT_MOVED_EMPLOYEE' => 'Перенос: изменен врач',
    'EVENT_MOVED_BRANCH' => 'Перенос: изменен филиал',
    'EVENT_CANCELLED' => 'Запись отменена',
    'EVENT_RESTORED' => 'Запись возвращена в расписание',
    'EVENT_STATUS_CHANGED' => 'Изменен статус записи',
];

// Функция для получения имени пользователя
function getUserName($userId) {
    if (!$userId) {
        return 'Система';
    }
    
    $user = \CUser::GetByID($userId)->Fetch();
    if ($user) {
        $name = trim($user['NAME'] . ' ' . $user['LAST_NAME']);
        return $name ?: $user['LOGIN'] ?: 'Пользователь #' . $userId;
    }
    
    return 'Пользователь #' . $userId;
}

// Функция для получения иконки действия
function getActionIcon($action) {
    $icons = [
        'CREATED_BY_CUSTOM' => '➕',
        'CREATED_BY_SCHEDULE' => '📅',
        'CONTACT_ATTACHED' => '👤',
        'CONTACT_DETACHED' => '👤',
        'DEAL_ATTACHED' => '💼',
        'DEAL_DETACHED' => '💼',
        'EMPLOYEE_CHANGED' => '👨‍⚕️',
        'CONFIRMATION_STATUS_CHANGED' => '✅',
        'VISIT_STATUS_CHANGED' => '🏥',
        'EVENT_TITLE_CHANGED' => '📝',
        'EVENT_DESCRIPTION_CHANGED' => '📄',
        'EVENT_DATE_FROM_CHANGED' => '⏰',
        'EVENT_DATE_TO_CHANGED' => '⏰',
        'EVENT_COLOR_CHANGED' => '🎨',
        'EVENT_BRANCH_CHANGED' => '🏢',
        'EVENT_EMPLOYEE_CHANGED' => '👨‍⚕️',
        'EVENT_MOVED_DATE_FROM' => '↔️',
        'EVENT_MOVED_DATE_TO' => '↔️',
        'EVENT_MOVED_EMPLOYEE' => '↔️',
        'EVENT_MOVED_BRANCH' => '↔️',
        'EVENT_CANCELLED' => '❌',
        'EVENT_RESTORED' => '✅',
        'EVENT_STATUS_CHANGED' => '🔄',
    ];
    
    return $icons[$action] ?? '📌';
}

// Функция для получения цвета действия
function getActionColor($action) {
    if (strpos($action, 'CREATED') !== false) {
        return '#4CAF50'; // Зеленый для создания
    } elseif (strpos($action, 'CANCELLED') !== false) {
        return '#F44336'; // Красный для отмены
    } elseif (strpos($action, 'RESTORED') !== false) {
        return '#2196F3'; // Синий для восстановления
    } elseif (strpos($action, 'MOVED') !== false) {
        return '#FF9800'; // Оранжевый для переноса
    } elseif (strpos($action, 'ATTACHED') !== false) {
        return '#9C27B0'; // Фиолетовый для привязки
    } elseif (strpos($action, 'DETACHED') !== false) {
        return '#607D8B'; // Серый для отвязки
    } else {
        return '#2196F3'; // Синий по умолчанию
    }
}
?>

<div class="journal-container">
    <div class="journal-header">
        <h2>Журнал изменений</h2>
        <?php if ($event): ?>
            <div class="journal-event-info">
                <div class="event-title">
                    <span class="event-color" style="background-color: <?= htmlspecialchars($event['EVENT_COLOR'] ?? '#3498db') ?>"></span>
                    <?= htmlspecialchars($event['TITLE'] ?? 'Без названия') ?>
                </div>
                <div class="event-dates">
                    <?php if (!empty($event['DATE_FROM'])): ?>
                        <span class="event-date"><?= htmlspecialchars($event['DATE_FROM']) ?></span>
                        <?php if (!empty($event['DATE_TO'])): ?>
                            <span class="event-date-separator">—</span>
                            <span class="event-date"><?= htmlspecialchars($event['DATE_TO']) ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (empty($journalEntries)): ?>
        <div class="journal-empty">
            <p>Записей в журнале пока нет</p>
        </div>
    <?php else: ?>
        <div class="journal-timeline">
            <?php foreach ($journalEntries as $entry): ?>
                <?php
                $action = $entry['ACTION'] ?? '';
                $actionLabel = $actionLabels[$action] ?? $action;
                $actionIcon = getActionIcon($action);
                $actionColor = getActionColor($action);
                $actionDate = $entry['ACTION_DATE_FORMATTED'] ?? '';
                $actionValue = $entry['ACTION_VALUE'] ?? '';
                $userId = $entry['USER_ID'] ?? null;
                $userName = getUserName($userId);
                $initiator = $entry['INITIATOR'] ?? '';
                ?>
                <div class="timeline-item">
                    <div class="timeline-marker" style="background-color: <?= $actionColor ?>">
                        <span class="timeline-icon"><?= $actionIcon ?></span>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-header">
                            <div class="timeline-action">
                                <span class="action-label"><?= htmlspecialchars($actionLabel) ?></span>
                                <?php if ($actionValue): ?>
                                    <span class="action-value">
                                        <?php
                                        // Парсим ACTION_VALUE (формат: FIELD=oldValue->newValue)
                                        if (preg_match('/^(.+?)=(.+?)->(.+)$/', $actionValue, $matches)) {
                                            $field = $matches[1];
                                            $oldValue = $matches[2];
                                            $newValue = $matches[3];
                                            echo '<span class="value-change">' . htmlspecialchars($oldValue) . '</span>';
                                            echo ' <span class="value-arrow">→</span> ';
                                            echo '<span class="value-change">' . htmlspecialchars($newValue) . '</span>';
                                        } else {
                                            echo htmlspecialchars($actionValue);
                                        }
                                        ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="timeline-date"><?= htmlspecialchars($actionDate) ?></div>
                        </div>
                        <div class="timeline-footer">
                            <span class="timeline-user"><?= htmlspecialchars($userName) ?></span>
                            <?php if ($initiator): ?>
                                <span class="timeline-separator">•</span>
                                <span class="timeline-initiator"><?= htmlspecialchars($initiator) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.journal-container {
    padding: 20px;
    max-width: 800px;
    margin: 0 auto;
}

.journal-header {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #e0e0e0;
}

.journal-header h2 {
    margin: 0 0 15px 0;
    color: #2c3e50;
    font-size: 24px;
    font-weight: 600;
}

.journal-event-info {
    background: #f5f5f5;
    padding: 15px;
    border-radius: 8px;
    margin-top: 15px;
}

.event-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 16px;
    font-weight: 500;
    color: #2c3e50;
    margin-bottom: 10px;
}

.event-color {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    display: inline-block;
    flex-shrink: 0;
}

.event-dates {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #666;
}

.event-date-separator {
    color: #999;
}

.journal-empty {
    text-align: center;
    padding: 60px 20px;
    color: #999;
    font-size: 16px;
}

.journal-timeline {
    position: relative;
    padding-left: 40px;
}

.journal-timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e0e0e0;
}

.timeline-item {
    position: relative;
    margin-bottom: 30px;
    display: flex;
    gap: 15px;
}

.timeline-item:last-child {
    margin-bottom: 0;
}

.timeline-marker {
    position: absolute;
    left: -32px;
    top: 0;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    z-index: 1;
    border: 3px solid #fff;
}

.timeline-icon {
    font-size: 16px;
}

.timeline-content {
    flex: 1;
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 15px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
    gap: 15px;
}

.timeline-action {
    flex: 1;
}

.action-label {
    font-weight: 600;
    color: #2c3e50;
    font-size: 15px;
    display: block;
    margin-bottom: 5px;
}

.action-value {
    font-size: 13px;
    color: #666;
    display: block;
    margin-top: 5px;
    padding: 5px 8px;
    background: #f9f9f9;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
}

.value-change {
    color: #333;
}

.value-arrow {
    color: #999;
    margin: 0 5px;
}

.timeline-date {
    font-size: 12px;
    color: #999;
    white-space: nowrap;
    flex-shrink: 0;
}

.timeline-footer {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: #999;
    padding-top: 10px;
    border-top: 1px solid #f0f0f0;
}

.timeline-user {
    font-weight: 500;
    color: #666;
}

.timeline-separator {
    color: #ccc;
}

.timeline-initiator {
    color: #999;
    font-family: 'Courier New', monospace;
    font-size: 11px;
}
</style>
