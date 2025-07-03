<?php if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);
?>

<form method="get" id="branch-select-form" style="margin-bottom: 20px;">
    <label for="branch_id"><b>Выберите филиал:</b></label>
    <select name="branch_id" id="branch_id" onchange="document.getElementById('branch-select-form').submit();">
        <?php foreach ($arResult['BRANCHES'] as $branch): ?>
            <option value="<?= $branch['ID'] ?>" <?= ($arResult['SELECTED_BRANCH_ID'] == $branch['ID'] ? 'selected' : '') ?>>
                <?= htmlspecialchars($branch['NAME']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

<div class="artmax-calendar">
    <?php if (!empty($arResult['ERROR'])): ?>
        <div class="artmax-calendar-error">
            <?= htmlspecialchars($arResult['ERROR']) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($arResult['SUCCESS'])): ?>
        <div class="artmax-calendar-success">
            <?= htmlspecialchars($arResult['SUCCESS']) ?>
        </div>
    <?php endif; ?>

    <?php if ($arParams['SHOW_FORM'] === 'Y'): ?>
        <div class="artmax-calendar-form">
            <h3>Добавить новое событие</h3>
            <form method="post" action="">
                <?= bitrix_sessid_post() ?>
                <input type="hidden" name="action" value="add_event">
                
                <div class="form-group">
                    <label for="title">Название события *:</label>
                    <input type="text" name="title" id="title" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Описание:</label>
                    <textarea name="description" id="description" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="date_from">Дата начала *:</label>
                    <input type="datetime-local" name="date_from" id="date_from" required>
                </div>
                
                <div class="form-group">
                    <label for="date_to">Дата окончания *:</label>
                    <input type="datetime-local" name="date_to" id="date_to" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Добавить событие</button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <div class="artmax-calendar-events">
        <h3>Мои события</h3>
        
        <?php if (empty($arResult['EVENTS'])): ?>
            <p>У вас пока нет событий.</p>
        <?php else: ?>
            <div class="events-list">
                <?php foreach ($arResult['EVENTS'] as $event): ?>
                    <div class="event-item">
                        <div class="event-header">
                            <h4><?= htmlspecialchars($event['TITLE']) ?></h4>
                            <form method="post" action="" style="display: inline;">
                                <?= bitrix_sessid_post() ?>
                                <input type="hidden" name="action" value="delete_event">
                                <input type="hidden" name="event_id" value="<?= $event['ID'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" 
                                        onclick="return confirm('Удалить это событие?')">
                                    Удалить
                                </button>
                            </form>
                        </div>
                        
                        <?php if (!empty($event['DESCRIPTION'])): ?>
                            <div class="event-description">
                                <?= nl2br(htmlspecialchars($event['DESCRIPTION'])) ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="event-dates">
                            <strong>Начало:</strong> <?= date('d.m.Y H:i', strtotime($event['DATE_FROM'])) ?><br>
                            <strong>Окончание:</strong> <?= date('d.m.Y H:i', strtotime($event['DATE_TO'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.artmax-calendar {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.artmax-calendar-error {
    background-color: #f8d7da;
    color: #721c24;
    padding: 10px;
    border: 1px solid #f5c6cb;
    border-radius: 4px;
    margin-bottom: 20px;
}

.artmax-calendar-success {
    background-color: #d4edda;
    color: #155724;
    padding: 10px;
    border: 1px solid #c3e6cb;
    border-radius: 4px;
    margin-bottom: 20px;
}

.artmax-calendar-form {
    background-color: #f8f9fa;
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
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-sizing: border-box;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}

.btn-primary {
    background-color: #007bff;
    color: white;
}

.btn-danger {
    background-color: #dc3545;
    color: white;
}

.btn-sm {
    padding: 4px 8px;
    font-size: 12px;
}

.events-list {
    display: grid;
    gap: 15px;
}

.event-item {
    background-color: white;
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
    margin-bottom: 10px;
    color: #666;
    line-height: 1.5;
}

.event-dates {
    font-size: 14px;
    color: #888;
}
</style> 