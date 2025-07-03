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

$eventId = (int)($_REQUEST['id'] ?? 0);
$calendar = new Calendar();

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $dateFrom = $_POST['date_from'] ?? '';
    $dateTo = $_POST['date_to'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);

    $errors = [];

    if (empty($title)) {
        $errors[] = 'Название события обязательно для заполнения';
    }

    if (empty($dateFrom)) {
        $errors[] = 'Дата начала обязательна для заполнения';
    }

    if (empty($dateTo)) {
        $errors[] = 'Дата окончания обязательна для заполнения';
    }

    if (empty($userId)) {
        $errors[] = 'Пользователь обязателен для заполнения';
    }

    if (empty($errors)) {
        if ($eventId > 0) {
            // Обновление существующего события
            if ($calendar->updateEvent($eventId, $title, $description, $dateFrom, $dateTo)) {
                LocalRedirect('artmax_calendar_events.php?success=updated');
            } else {
                $errors[] = 'Ошибка при обновлении события';
            }
        } else {
            // Добавление нового события
            if ($calendar->addEvent($title, $description, $dateFrom, $dateTo, $userId)) {
                LocalRedirect('artmax_calendar_events.php?success=added');
            } else {
                $errors[] = 'Ошибка при добавлении события';
            }
        }
    }
}

// Получение данных события для редактирования
$event = null;
if ($eventId > 0) {
    $event = $calendar->getEvent($eventId);
    if (!$event) {
        ShowError('Событие не найдено');
        require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
        die();
    }
    $APPLICATION->SetTitle('Редактирование события');
} else {
    $APPLICATION->SetTitle('Добавление нового события');
}

// Вывод ошибок
if (!empty($errors)) {
    foreach ($errors as $error) {
        CAdminMessage::ShowMessage(['MESSAGE' => $error, 'TYPE' => 'ERROR']);
    }
}
?>

<form method="post" action="">
    <?= bitrix_sessid_post() ?>
    
    <table class="adm-detail-content-table edit-table">
        <tr>
            <td class="adm-detail-content-cell-l" width="40%">
                <label for="title">Название события:</label>
            </td>
            <td class="adm-detail-content-cell-r" width="60%">
                <input type="text" name="title" id="title" value="<?= htmlspecialchars($event['TITLE'] ?? '') ?>" size="50" required>
            </td>
        </tr>
        
        <tr>
            <td class="adm-detail-content-cell-l">
                <label for="description">Описание:</label>
            </td>
            <td class="adm-detail-content-cell-r">
                <textarea name="description" id="description" rows="5" cols="50"><?= htmlspecialchars($event['DESCRIPTION'] ?? '') ?></textarea>
            </td>
        </tr>
        
        <tr>
            <td class="adm-detail-content-cell-l">
                <label for="date_from">Дата начала:</label>
            </td>
            <td class="adm-detail-content-cell-r">
                <input type="datetime-local" name="date_from" id="date_from" 
                       value="<?= $event['DATE_FROM'] ? date('Y-m-d\TH:i', strtotime($event['DATE_FROM'])) : '' ?>" required>
            </td>
        </tr>
        
        <tr>
            <td class="adm-detail-content-cell-l">
                <label for="date_to">Дата окончания:</label>
            </td>
            <td class="adm-detail-content-cell-r">
                <input type="datetime-local" name="date_to" id="date_to" 
                       value="<?= $event['DATE_TO'] ? date('Y-m-d\TH:i', strtotime($event['DATE_TO'])) : '' ?>" required>
            </td>
        </tr>
        
        <tr>
            <td class="adm-detail-content-cell-l">
                <label for="user_id">Пользователь:</label>
            </td>
            <td class="adm-detail-content-cell-r">
                <select name="user_id" id="user_id" required>
                    <option value="">Выберите пользователя</option>
                    <?php
                    $rsUsers = CUser::GetList('ID', 'ASC', ['ACTIVE' => 'Y']);
                    while ($user = $rsUsers->Fetch()) {
                        $selected = ($event['USER_ID'] == $user['ID']) ? 'selected' : '';
                        echo '<option value="'.$user['ID'].'" '.$selected.'>'.$user['LOGIN'].' ('.$user['NAME'].' '.$user['LAST_NAME'].')</option>';
                    }
                    ?>
                </select>
            </td>
        </tr>
    </table>
    
    <div class="adm-detail-content-btns">
        <input type="submit" name="save" value="Сохранить" class="adm-btn-save">
        <input type="button" name="cancel" value="Отмена" onclick="window.location.href='artmax_calendar_events.php'" class="adm-btn">
    </div>
</form>

<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
?> 