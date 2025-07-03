<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

if (CModule::IncludeModule('artmax.calendar')) {
    // Удаляем существующий раздел перед переустановкой
    \Artmax\Calendar\EventHandlers::removeCustomSection();
    
    echo '<p>✅ Существующий раздел удален</p>';
}

// Показываем форму установки
?>
<form action="<?= $APPLICATION->GetCurPage() ?>">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="step" value="2">
    <input type="hidden" name="id" value="artmax.calendar">
    <input type="hidden" name="lang" value="<?= LANG ?>">
    <input type="hidden" name="install" value="Y">
    
    <p>Модуль календаря ArtMax готов к установке.</p>
    <p>Нажмите "Установить" для продолжения.</p>
    
    <input type="submit" name="inst" value="Установить" class="adm-btn-save">
</form> 