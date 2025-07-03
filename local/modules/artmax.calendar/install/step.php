<?php
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

if (!check_bitrix_sessid()) {
    return;
}

if ($ex = $APPLICATION->GetException()) {
    echo CAdminMessage::ShowMessage([
        'TYPE' => 'ERROR',
        'MESSAGE' => Loc::getMessage('ARTMAX_CALENDAR_INSTALL_ERROR'),
        'DETAILS' => $ex->GetString(),
        'HTML' => true
    ]);
} else {
    echo CAdminMessage::ShowNote(Loc::getMessage('ARTMAX_CALENDAR_INSTALL_SUCCESS'));
}
?> 