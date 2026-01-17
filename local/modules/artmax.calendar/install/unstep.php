<?php
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$request = \Bitrix\Main\Context::getCurrent()->getRequest();
$step = (int)$request->get('step');

// Показываем форму, если step < 2 (первый вызов или step = 1)
if ($step < 2) {
    // Проверяем sessid для первого шага
    if (!check_bitrix_sessid()) {
        return;
    }
    // Первый шаг - форма выбора
    ?>
    <div class="adm-info-message-wrap">
        <div class="adm-info-message">
            <?= Loc::getMessage('ARTMAX_CALENDAR_UNINSTALL_SELECT') ?>
        </div>
    </div>
    
    <form action="<?= $APPLICATION->GetCurPage() ?>" method="POST">
        <?= bitrix_sessid_post() ?>
        <input type="hidden" name="id" value="artmax.calendar">
        <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
        <input type="hidden" name="uninstall" value="Y">
        <input type="hidden" name="step" value="2">
        
        <div class="adm-detail-content-wrap">
            <div class="adm-detail-content">
                <table class="adm-detail-content-table edit-table">
                    <tbody>
                        <tr>
                            <td class="adm-detail-content-cell-l" width="100%">
                                <label style="display: flex; align-items: flex-start; cursor: pointer; padding: 10px 0;">
                                    <input type="checkbox" name="delete_groups" value="Y" checked style="margin-right: 10px; margin-top: 3px; flex-shrink: 0;">
                                    <div style="flex: 1; text-align: left;">
                                        <strong style="display: block; margin-bottom: 4px;"><?= Loc::getMessage('ARTMAX_CALENDAR_UNINSTALL_DELETE_GROUPS') ?></strong>
                                        <span style="color: #80868e; font-size: 12px; line-height: 1.4; display: block;">
                                            <?= Loc::getMessage('ARTMAX_CALENDAR_UNINSTALL_DELETE_GROUPS_DESC') ?>
                                        </span>
                                    </div>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <td class="adm-detail-content-cell-l" width="100%">
                                <label style="display: flex; align-items: flex-start; cursor: pointer; padding: 10px 0;">
                                    <input type="checkbox" name="delete_tables" value="Y" checked style="margin-right: 10px; margin-top: 3px; flex-shrink: 0;">
                                    <div style="flex: 1; text-align: left;">
                                        <strong style="display: block; margin-bottom: 4px;"><?= Loc::getMessage('ARTMAX_CALENDAR_UNINSTALL_DELETE_TABLES') ?></strong>
                                        <span style="color: #80868e; font-size: 12px; line-height: 1.4; display: block;">
                                            <?= Loc::getMessage('ARTMAX_CALENDAR_UNINSTALL_DELETE_TABLES_DESC') ?>
                                        </span>
                                    </div>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <td class="adm-detail-content-cell-l" width="100%">
                                <label style="display: flex; align-items: flex-start; cursor: pointer; padding: 10px 0;">
                                    <input type="checkbox" name="delete_crm_fields" value="Y" checked style="margin-right: 10px; margin-top: 3px; flex-shrink: 0;">
                                    <div style="flex: 1; text-align: left;">
                                        <strong style="display: block; margin-bottom: 4px;"><?= Loc::getMessage('ARTMAX_CALENDAR_UNINSTALL_DELETE_CRM_FIELDS') ?></strong>
                                        <span style="color: #80868e; font-size: 12px; line-height: 1.4; display: block;">
                                            <?= Loc::getMessage('ARTMAX_CALENDAR_UNINSTALL_DELETE_CRM_FIELDS_DESC') ?>
                                        </span>
                                    </div>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <td class="adm-detail-content-cell-l" width="100%">
                                <label style="display: flex; align-items: flex-start; cursor: pointer; padding: 10px 0;">
                                    <input type="checkbox" name="delete_settings" value="Y" checked style="margin-right: 10px; margin-top: 3px; flex-shrink: 0;">
                                    <div style="flex: 1; text-align: left;">
                                        <strong style="display: block; margin-bottom: 4px;"><?= Loc::getMessage('ARTMAX_CALENDAR_UNINSTALL_DELETE_SETTINGS') ?></strong>
                                        <span style="color: #80868e; font-size: 12px; line-height: 1.4; display: block;">
                                            <?= Loc::getMessage('ARTMAX_CALENDAR_UNINSTALL_DELETE_SETTINGS_DESC') ?>
                                        </span>
                                    </div>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <td class="adm-detail-content-cell-l" width="100%">
                                <label style="display: flex; align-items: flex-start; cursor: pointer; padding: 10px 0;">
                                    <input type="checkbox" name="delete_files" value="Y" checked style="margin-right: 10px; margin-top: 3px; flex-shrink: 0;">
                                    <div style="flex: 1; text-align: left;">
                                        <strong style="display: block; margin-bottom: 4px;"><?= Loc::getMessage('ARTMAX_CALENDAR_UNINSTALL_DELETE_FILES') ?></strong>
                                        <span style="color: #80868e; font-size: 12px; line-height: 1.4; display: block;">
                                            <?= Loc::getMessage('ARTMAX_CALENDAR_UNINSTALL_DELETE_FILES_DESC') ?>
                                        </span>
                                    </div>
                                </label>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="adm-detail-toolbar-wrap">
            <div class="adm-detail-toolbar">
                <input type="submit" name="uninstall" value="<?= Loc::getMessage('ARTMAX_CALENDAR_UNINSTALL_BUTTON') ?>" class="adm-btn-save">
                <input type="button" value="<?= Loc::getMessage('ARTMAX_CALENDAR_UNINSTALL_CANCEL') ?>" onclick="window.location.href='<?= $APPLICATION->GetCurPage() ?>?lang=<?= LANGUAGE_ID ?>&id=artmax.calendar'" class="adm-btn">
            </div>
        </div>
    </form>
    <?php
    } else {
        // Второй шаг - редирект на страницу списка модулей с сообщением
        // Удаление уже выполнено в DoUninstall()
        $deleteGroups = $request->get('delete_groups') == 'Y';
        $deleteTables = $request->get('delete_tables') == 'Y';
        $deleteCrmFields = $request->get('delete_crm_fields') == 'Y';
        $deleteSettings = $request->get('delete_settings') == 'Y';
        $deleteFiles = $request->get('delete_files') == 'Y';
        
        // Формируем сообщение
        if ($ex = $APPLICATION->GetException()) {
            $message = Loc::getMessage('ARTMAX_CALENDAR_UNINSTALL_ERROR');
            $messageDetails = $ex->GetString();
            $messageType = 'ERROR';
        } else {
            $messages = [];
            if ($deleteGroups) {
                $messages[] = Loc::getMessage('ARTMAX_CALENDAR_UNINSTALL_DELETED_GROUPS');
            }
            if ($deleteTables) {
                $messages[] = Loc::getMessage('ARTMAX_CALENDAR_UNINSTALL_DELETED_TABLES');
            }
            if ($deleteCrmFields) {
                $messages[] = Loc::getMessage('ARTMAX_CALENDAR_UNINSTALL_DELETED_CRM_FIELDS');
            }
            if ($deleteSettings) {
                $messages[] = Loc::getMessage('ARTMAX_CALENDAR_UNINSTALL_DELETED_SETTINGS');
            }
            if ($deleteFiles) {
                $messages[] = Loc::getMessage('ARTMAX_CALENDAR_UNINSTALL_DELETED_FILES');
            }
            
            $message = Loc::getMessage('ARTMAX_CALENDAR_UNINSTALL_SUCCESS');
            if (!empty($messages)) {
                $message .= '<br><br><strong>' . Loc::getMessage('ARTMAX_CALENDAR_UNINSTALL_DELETED_ITEMS') . ':</strong><ul>';
                foreach ($messages as $msg) {
                    $message .= '<li>' . $msg . '</li>';
                }
                $message .= '</ul>';
            }
            $messageType = 'OK';
            $messageDetails = '';
        }
        
        // Формируем параметры для передачи сообщения через URL
        $messageParam = base64_encode(json_encode([
            'TYPE' => $messageType,
            'MESSAGE' => $message,
            'DETAILS' => $messageDetails
        ]));
        
        // Показываем сообщение и делаем редирект через JavaScript
        // Это нужно, чтобы сообщение было показано на странице partner_modules.php
        ?>
        <script>
            (function() {
                var messageData = <?= json_encode([
                    'TYPE' => $messageType,
                    'MESSAGE' => $message,
                    'DETAILS' => $messageDetails
                ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
                
                // Сохраняем сообщение в sessionStorage для показа на странице partner_modules.php
                if (typeof sessionStorage !== 'undefined') {
                    sessionStorage.setItem('artmax_calendar_uninstall_message', JSON.stringify(messageData));
                }
                
                // Редирект на страницу списка модулей
                window.location.href = '/bitrix/admin/partner_modules.php?lang=<?= LANGUAGE_ID ?>';
            })();
        </script>
        <?php
        // Также делаем редирект на случай, если JavaScript отключен
        LocalRedirect('/bitrix/admin/partner_modules.php?lang=' . LANGUAGE_ID);
    }
?>
