<?php if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);
?>

<div class="artmax-calendar">
    <div class="calendar-header">
        <h1>Календарь - <?= htmlspecialchars($arResult['BRANCH']['NAME']) ?></h1>
        
        <?php if (!empty($arResult['ALL_BRANCHES'])): ?>
            <div class="branch-navigation">
                <label for="branch-select">Выберите филиал:</label>
                <select id="branch-select" onchange="changeBranch(this.value)">
                    <?php foreach ($arResult['ALL_BRANCHES'] as $branch): ?>
                        <option value="<?= $branch['ID'] ?>" <?= $branch['ID'] == $arResult['BRANCH']['ID'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($branch['NAME']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($arResult['SHOW_FORM'] && $arResult['CAN_ADD_EVENTS']): ?>
        <div class="calendar-form">
            <h3>Добавить событие</h3>
            <form id="add-event-form">
                <?= bitrix_sessid_post() ?>
                <div class="form-group">
                    <label for="event-title">Название события *</label>
                    <input type="text" id="event-title" name="title" required>
                </div>
                
                <div class="form-group">
                    <label for="event-description">Описание</label>
                    <textarea id="event-description" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="event-date-from">Дата и время начала *</label>
                    <input type="datetime-local" id="event-date-from" name="dateFrom" required>
                </div>
                
                <div class="form-group">
                    <label for="event-date-to">Дата и время окончания *</label>
                    <input type="datetime-local" id="event-date-to" name="dateTo" required>
                </div>
                
                <button type="submit" class="btn btn-primary">Добавить событие</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="calendar-events">
        <h3>События</h3>
        
        <?php if (empty($arResult['EVENTS'])): ?>
            <p class="no-events">Событий пока нет</p>
        <?php else: ?>
            <div class="events-list">
                <?php foreach ($arResult['EVENTS'] as $event): ?>
                    <div class="event-item" data-event-id="<?= $event['ID'] ?>">
                        <div class="event-header">
                            <h4><?= htmlspecialchars($event['TITLE']) ?></h4>
                            <?php if ($event['USER_ID'] == $arResult['CURRENT_USER_ID']): ?>
                                <button class="btn btn-danger btn-sm" onclick="deleteEvent(<?= $event['ID'] ?>)">Удалить</button>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($event['DESCRIPTION'])): ?>
                            <p class="event-description"><?= htmlspecialchars($event['DESCRIPTION']) ?></p>
                        <?php endif; ?>
                        
                        <div class="event-details">
                            <span class="event-time">
                                <strong>Время:</strong> 
                                <?= date('d.m.Y H:i', strtotime($event['DATE_FROM'])) ?> - 
                                <?= date('d.m.Y H:i', strtotime($event['DATE_TO'])) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.artmax-calendar {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #eee;
}

.branch-navigation {
    display: flex;
    align-items: center;
    gap: 10px;
}

.branch-navigation select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.calendar-form {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 30px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    text-decoration: none;
    display: inline-block;
}

.btn-primary {
    background: #007cba;
    color: white;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 12px;
}

.events-list {
    display: grid;
    gap: 15px;
}

.event-item {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.event-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.event-header h4 {
    margin: 0;
    color: #333;
}

.event-description {
    color: #666;
    margin-bottom: 10px;
}

.event-details {
    font-size: 14px;
    color: #888;
}

.no-events {
    text-align: center;
    color: #666;
    font-style: italic;
    padding: 40px;
}

@media (max-width: 768px) {
    .calendar-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
    
    .event-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
}
</style>

<script>
function changeBranch(branchId) {
    // Перенаправляем на страницу с выбранным филиалом
    window.location.href = '/artmax-calendar/' + branchId;
}

function deleteEvent(eventId) {
    if (!confirm('Вы уверены, что хотите удалить это событие?')) {
        return;
    }
    
    BX.ajax.runComponentAction('artmax:calendar', 'deleteEvent', {
        mode: 'class',
        data: {
            eventId: eventId
        }
    }).then(function(response) {
        if (response.data.success) {
            // Удаляем элемент из DOM
            document.querySelector('[data-event-id="' + eventId + '"]').remove();
            alert('Событие удалено');
        } else {
            alert('Ошибка: ' + response.data.error);
        }
    }).catch(function(response) {
        alert('Ошибка: ' + response.errors[0].message);
    });
}

// Обработка формы добавления события
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('add-event-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            
            BX.ajax.runComponentAction('artmax:calendar', 'addEvent', {
                mode: 'class',
                data: {
                    title: formData.get('title'),
                    description: formData.get('description'),
                    dateFrom: formData.get('dateFrom'),
                    dateTo: formData.get('dateTo'),
                    branchId: <?= $arResult['BRANCH']['ID'] ?>
                }
            }).then(function(response) {
                if (response.data.success) {
                    alert('Событие добавлено');
                    form.reset();
                    // Перезагружаем страницу для отображения нового события
                    location.reload();
                } else {
                    alert('Ошибка: ' + response.data.error);
                }
            }).catch(function(response) {
                alert('Ошибка: ' + response.errors[0].message);
            });
        });
    }
});
</script> 