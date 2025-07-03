<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php');

use Bitrix\Main\Localization\Loc;
use Artmax\Calendar\Calendar;

Loc::loadMessages(__FILE__);

if (!CModule::IncludeModule('artmax.calendar')) {
    ShowError('Модуль artmax.calendar не установлен');
    require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
    die();
}

$APPLICATION->SetTitle('Управление событиями календаря');

// Обработка действий
$action = $_REQUEST['action'] ?? '';
$eventId = (int)($_REQUEST['id'] ?? 0);

if ($action === 'delete' && $eventId > 0) {
    $calendar = new Calendar();
    if ($calendar->deleteEvent($eventId)) {
        $message = 'Событие успешно удалено';
    } else {
        $error = 'Ошибка при удалении события';
    }
}

// Получение списка событий
$calendar = new Calendar();
$events = $calendar->getEvents(
    date('Y-m-d H:i:s', strtotime('-1 month')),
    date('Y-m-d H:i:s', strtotime('+1 year'))
);

// Создание таблицы
$lAdmin = new CAdminList($sTableID, $oSort);

$lAdmin->AddHeaders([
    ['id' => 'ID', 'content' => 'ID', 'sort' => 'ID', 'default' => true],
    ['id' => 'TITLE', 'content' => 'Название', 'sort' => 'TITLE', 'default' => true],
    ['id' => 'DESCRIPTION', 'content' => 'Описание', 'sort' => 'DESCRIPTION', 'default' => true],
    ['id' => 'DATE_FROM', 'content' => 'Дата начала', 'sort' => 'DATE_FROM', 'default' => true],
    ['id' => 'DATE_TO', 'content' => 'Дата окончания', 'sort' => 'DATE_TO', 'default' => true],
    ['id' => 'USER_ID', 'content' => 'Пользователь', 'sort' => 'USER_ID', 'default' => true],
    ['id' => 'ACTIONS', 'content' => 'Действия', 'sort' => '', 'default' => true],
]);

foreach ($events as $event) {
    $row = $lAdmin->AddRow($event['ID'], $event);
    
    $row->AddViewField('ID', $event['ID']);
    $row->AddViewField('TITLE', htmlspecialchars($event['TITLE']));
    $row->AddViewField('DESCRIPTION', htmlspecialchars($event['DESCRIPTION']));
    $row->AddViewField('DATE_FROM', $event['DATE_FROM']);
    $row->AddViewField('DATE_TO', $event['DATE_TO']);
    $row->AddViewField('USER_ID', $event['USER_ID']);
    
    $actions = [];
    $actions[] = [
        'ICON' => 'edit',
        'TEXT' => 'Редактировать',
        'ACTION' => $lAdmin->ActionRedirect('artmax_calendar_event_edit.php?id='.$event['ID'])
    ];
    $actions[] = [
        'ICON' => 'delete',
        'TEXT' => 'Удалить',
        'ACTION' => "if(confirm('Удалить событие?')) ".$lAdmin->ActionDoGroup($event['ID'], 'delete')
    ];
    
    $row->AddActions($actions);
}

$lAdmin->AddGroupActionTable([
    'delete' => 'Удалить выбранные события'
]);

$lAdmin->CheckListMode();

// Вывод сообщений
if (!empty($message)) {
    CAdminMessage::ShowMessage(['MESSAGE' => $message, 'TYPE' => 'OK']);
}

if (!empty($error)) {
    CAdminMessage::ShowMessage(['MESSAGE' => $error, 'TYPE' => 'ERROR']);
}

// Форма добавления нового события
?>
<div class="adm-info-message">
    <a href="artmax_calendar_event_edit.php">Добавить новое событие</a>
</div>

<?php
$lAdmin->Display();

require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
?> 