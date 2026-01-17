<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_before.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php');

use Bitrix\Main\Localization\Loc;
use Artmax\Calendar\Permissions;

Loc::loadMessages(__FILE__);

if (!CModule::IncludeModule('artmax.calendar')) {
    ShowError('Модуль artmax.calendar не установлен');
    require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
    die();
}

$permissionsObj = new Permissions();
$groupId = (int)($_GET['group_id'] ?? 0);

if ($groupId <= 0) {
    ShowError('Не указан ID группы');
    require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
    die();
}

// Получаем информацию о группе
$groups = $permissionsObj->getCalendarGroups();
$currentGroup = null;
foreach ($groups as $group) {
    if ($group['GROUP_ID'] == $groupId) {
        $currentGroup = $group;
        break;
    }
}

if (!$currentGroup) {
    ShowError('Группа не найдена');
    require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
    die();
}

$groupUsers = $permissionsObj->getGroupUsers($groupId);
$linkedGroups = $permissionsObj->getLinkedBitrixGroups($groupId);

$APPLICATION->SetTitle('Пользователи группы: ' . $currentGroup['GROUP_NAME']);

CJSCore::Init(['jquery', 'ui.buttons']);
?>

<style>
.artmax-users-list {
    padding: 20px;
    background: #fff;
}

.artmax-user-item {
    padding: 15px;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.artmax-user-item:last-child {
    border-bottom: none;
}

.artmax-user-info {
    flex: 1;
}

.artmax-user-name {
    font-weight: bold;
    font-size: 16px;
}

.artmax-user-details {
    color: #666;
    font-size: 14px;
    margin-top: 5px;
}
</style>

<div class="artmax-users-list">
    <h2>Пользователи группы: <?= htmlspecialchars($currentGroup['GROUP_NAME']) ?></h2>
    
    <p style="color: #666; margin-bottom: 20px;">
        Для добавления пользователей в группу используйте стандартный интерфейс Bitrix: 
        <a href="/bitrix/admin/user_edit.php?ID=0&lang=<?=LANGUAGE_ID?>" target="_blank">Управление пользователями</a>
    </p>
    
    <?php if (!empty($linkedGroups)): ?>
        <div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px;">
            <h3 style="margin-top: 0;">Привязанные группы Bitrix</h3>
            <p style="color: #666; font-size: 14px; margin-bottom: 10px;">
                Пользователи из этих групп автоматически включены в группу календаря:
            </p>
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($linkedGroups as $linkedGroup): ?>
                    <li style="margin-bottom: 5px;">
                        <strong><?= htmlspecialchars($linkedGroup['NAME']) ?></strong>
                        <?php if ($linkedGroup['DESCRIPTION']): ?>
                            <span style="color: #666;"> - <?= htmlspecialchars($linkedGroup['DESCRIPTION']) ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if (empty($groupUsers)): ?>
        <p>В группе нет пользователей.</p>
    <?php else: ?>
        <div style="border: 1px solid #e0e0e0; border-radius: 4px;">
            <?php foreach ($groupUsers as $user): ?>
                <div class="artmax-user-item">
                    <div class="artmax-user-info">
                        <div class="artmax-user-name">
                            <?= htmlspecialchars($user['NAME'] . ' ' . $user['LAST_NAME']) ?>
                        </div>
                        <div class="artmax-user-details">
                            Логин: <?= htmlspecialchars($user['LOGIN']) ?> | 
                            Email: <?= htmlspecialchars($user['EMAIL']) ?>
                            <?php if (isset($user['SOURCE']) && $user['SOURCE'] === 'linked'): ?>
                                | <span style="color: #1976d2;" title="Пользователь из привязанной группы">Из группы: <?= htmlspecialchars($user['LINKED_GROUP_NAME'] ?? '') ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <a href="/bitrix/admin/user_edit.php?ID=<?= $user['ID'] ?>&lang=<?=LANGUAGE_ID?>" 
                           target="_blank" 
                           class="ui-btn ui-btn-link">
                            Редактировать
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
?>
