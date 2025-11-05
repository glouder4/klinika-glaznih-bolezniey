<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$eventId = $arParams["EVENT_ID"] ?? 0;
?>

<div class="journal-container">
    <div class="journal-placeholder">
        <h2>Журнал событий</h2>
        <p style="color: #999; font-size: 14px; margin-top: 20px;">Содержимое журнала будет реализовано позже</p>
        <?php if ($eventId): ?>
            <p style="color: #666; font-size: 12px; margin-top: 10px;">ID события: <?= htmlspecialchars($eventId) ?></p>
        <?php endif; ?>
    </div>
</div>

<style>
.journal-container {
    padding: 20px;
}

.journal-placeholder {
    text-align: center;
    padding: 40px 20px;
}

.journal-placeholder h2 {
    margin: 0 0 10px 0;
    color: #2c3e50;
    font-size: 24px;
}

.journal-placeholder p {
    margin: 10px 0;
}
</style>

