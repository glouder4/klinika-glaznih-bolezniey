<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin.php');

use Bitrix\Main\Application;
use Artmax\Calendar\TimezoneManager;

// Проверяем права доступа
if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm('Доступ запрещен');
}

// Подключаем модуль
if (!CModule::IncludeModule('artmax.calendar')) {
    ShowError('Модуль artmax.calendar не установлен');
    require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
    die();
}

$timezoneManager = new TimezoneManager();
$connection = Application::getConnection();

// Обработка формы
if ($_POST['action'] === 'update_timezone') {
    $branchId = (int)$_POST['branch_id'];
    $timezoneData = [
        'timezone_name' => $_POST['timezone_name'],
        'timezone_offset' => (int)$_POST['timezone_offset'],
        'dst_enabled' => isset($_POST['dst_enabled']) ? 1 : 0,
        'dst_start_month' => (int)$_POST['dst_start_month'],
        'dst_start_day' => (int)$_POST['dst_start_day'],
        'dst_start_hour' => (int)$_POST['dst_start_hour'],
        'dst_end_month' => (int)$_POST['dst_end_month'],
        'dst_end_day' => (int)$_POST['dst_end_day'],
        'dst_end_hour' => (int)$_POST['dst_end_hour']
    ];
    
    if ($timezoneManager->updateBranchTimezone($branchId, $timezoneData)) {
        $successMessage = 'Настройки часового пояса обновлены успешно';
    } else {
        $errorMessage = 'Ошибка обновления настроек часового пояса';
    }
}

// Получаем список филиалов
$sql = "SELECT * FROM artmax_calendar_branches WHERE IS_ACTIVE = 1 ORDER BY NAME";
$result = $connection->query($sql);
$branches = $result->fetchAll();

// Получаем все часовые пояса
$availableTimezones = $timezoneManager->getAvailableTimezones();

$APPLICATION->SetTitle('Управление часовыми поясами филиалов');
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_admin_after.php');

if (isset($successMessage)) {
    echo '<div class="adm-info-message">' . $successMessage . '</div>';
}

if (isset($errorMessage)) {
    echo '<div class="adm-error-message">' . $errorMessage . '</div>';
}
?>

<div class="adm-detail-content">
    <h2>Настройки часовых поясов для филиалов</h2>
    
    <p>Здесь вы можете настроить часовые пояса для каждого филиала. Время будет автоматически конвертироваться между часовыми поясами.</p>
    
    <div class="adm-detail-content-item-block">
        <?php foreach ($branches as $branch): ?>
            <?php
            $timezone = $timezoneManager->getBranchTimezone($branch['ID']);
            $currentOffset = $timezoneManager->getCurrentOffset($branch['ID']);
            $isDSTActive = $timezoneManager->isDSTActive($branch['ID']);
            ?>
            
            <div class="adm-detail-content-item-block-item" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
                <h3><?= htmlspecialchars($branch['NAME']) ?></h3>
                <p><strong>Адрес:</strong> <?= htmlspecialchars($branch['ADDRESS']) ?></p>
                <p><strong>Текущий часовой пояс:</strong> <?= $timezone ? htmlspecialchars($timezone['TIMEZONE_NAME']) : 'Не настроен' ?></p>
                <p><strong>Текущее смещение:</strong> UTC<?= $currentOffset >= 0 ? '+' : '' ?><?= $currentOffset ?>:00</p>
                <p><strong>Летнее время:</strong> <?= $isDSTActive ? 'Активно' : 'Неактивно' ?></p>
                
                <form method="POST" style="margin-top: 15px;">
                    <input type="hidden" name="action" value="update_timezone">
                    <input type="hidden" name="branch_id" value="<?= $branch['ID'] ?>">
                    
                    <table class="adm-detail-content-table">
                        <tr>
                            <td class="adm-detail-content-cell-l" width="40%">
                                <label for="timezone_name_<?= $branch['ID'] ?>">Часовой пояс:</label>
                            </td>
                            <td class="adm-detail-content-cell-r">
                                <select name="timezone_name" id="timezone_name_<?= $branch['ID'] ?>" style="width: 300px;">
                                    <?php foreach ($availableTimezones as $tzName => $tzLabel): ?>
                                        <option value="<?= $tzName ?>" <?= ($timezone && $timezone['TIMEZONE_NAME'] === $tzName) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($tzLabel) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <td class="adm-detail-content-cell-l">
                                <label for="timezone_offset_<?= $branch['ID'] ?>">Смещение (часы):</label>
                            </td>
                            <td class="adm-detail-content-cell-r">
                                <input type="number" name="timezone_offset" id="timezone_offset_<?= $branch['ID'] ?>" 
                                       value="<?= $timezone ? $timezone['TIMEZONE_OFFSET'] : 3 ?>" 
                                       min="-12" max="14" style="width: 100px;">
                                <span style="margin-left: 10px; color: #666;">(например: 3 для UTC+3)</span>
                            </td>
                        </tr>
                        
                        <tr>
                            <td class="adm-detail-content-cell-l">
                                <label for="dst_enabled_<?= $branch['ID'] ?>">Летнее время:</label>
                            </td>
                            <td class="adm-detail-content-cell-r">
                                <input type="checkbox" name="dst_enabled" id="dst_enabled_<?= $branch['ID'] ?>" 
                                       value="1" <?= ($timezone && $timezone['DST_ENABLED']) ? 'checked' : '' ?>>
                                <span style="margin-left: 10px;">Включить автоматическое переключение на летнее время</span>
                            </td>
                        </tr>
                        
                        <tr>
                            <td class="adm-detail-content-cell-l">
                                <label>Начало летнего времени:</label>
                            </td>
                            <td class="adm-detail-content-cell-r">
                                <select name="dst_start_month" style="width: 80px;">
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <option value="<?= $i ?>" <?= ($timezone && $timezone['DST_START_MONTH'] == $i) ? 'selected' : '' ?>>
                                            <?= date('M', mktime(0, 0, 0, $i, 1)) ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                
                                <select name="dst_start_day" style="width: 60px; margin-left: 5px;">
                                    <?php for ($i = 1; $i <= 31; $i++): ?>
                                        <option value="<?= $i ?>" <?= ($timezone && $timezone['DST_START_DAY'] == $i) ? 'selected' : '' ?>>
                                            <?= $i ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                
                                <select name="dst_start_hour" style="width: 60px; margin-left: 5px;">
                                    <?php for ($i = 0; $i <= 23; $i++): ?>
                                        <option value="<?= $i ?>" <?= ($timezone && $timezone['DST_START_HOUR'] == $i) ? 'selected' : '' ?>>
                                            <?= str_pad($i, 2, '0', STR_PAD_LEFT) ?>:00
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <td class="adm-detail-content-cell-l">
                                <label>Конец летнего времени:</label>
                            </td>
                            <td class="adm-detail-content-cell-r">
                                <select name="dst_end_month" style="width: 80px;">
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <option value="<?= $i ?>" <?= ($timezone && $timezone['DST_END_MONTH'] == $i) ? 'selected' : '' ?>>
                                            <?= date('M', mktime(0, 0, 0, $i, 1)) ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                
                                <select name="dst_end_day" style="width: 60px; margin-left: 5px;">
                                    <?php for ($i = 1; $i <= 31; $i++): ?>
                                        <option value="<?= $i ?>" <?= ($timezone && $timezone['DST_END_DAY'] == $i) ? 'selected' : '' ?>>
                                            <?= $i ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                
                                <select name="dst_end_hour" style="width: 60px; margin-left: 5px;">
                                    <?php for ($i = 0; $i <= 23; $i++): ?>
                                        <option value="<?= $i ?>" <?= ($timezone && $timezone['DST_END_HOUR'] == $i) ? 'selected' : '' ?>>
                                            <?= str_pad($i, 2, '0', STR_PAD_LEFT) ?>:00
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <div style="margin-top: 15px;">
                        <input type="submit" value="Сохранить настройки" class="adm-btn-save">
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div style="margin-top: 30px; padding: 15px; background-color: #f5f5f5; border-radius: 5px;">
        <h3>Информация о часовых поясах</h3>
        <p><strong>Как это работает:</strong></p>
        <ul>
            <li>Время в базе данных всегда хранится в UTC (универсальное время)</li>
            <li>При отображении в календаре время автоматически конвертируется в локальный часовой пояс филиала</li>
            <li>При создании/редактировании событий время автоматически конвертируется из локального времени филиала в UTC</li>
            <li>Система автоматически учитывает переходы на летнее/зимнее время</li>
        </ul>
        
        <p><strong>Рекомендации:</strong></p>
        <ul>
            <li>Установите правильный часовой пояс для каждого филиала</li>
            <li>Проверьте настройки летнего времени для вашего региона</li>
            <li>После изменения настроек проверьте корректность отображения времени в календаре</li>
        </ul>
    </div>
</div>

<script>
// Автоматическое обновление смещения при выборе часового пояса
document.querySelectorAll('select[name="timezone_name"]').forEach(function(select) {
    select.addEventListener('change', function() {
        const timezoneName = this.value;
        const offsetInput = this.closest('form').querySelector('input[name="timezone_offset"]');
        
        // Простое определение смещения по названию часового пояса
        const offsetMap = {
            'Europe/Moscow': 3,
            'Europe/London': 0,
            'Europe/Paris': 1,
            'Europe/Berlin': 1,
            'America/New_York': -5,
            'America/Los_Angeles': -8,
            'Asia/Tokyo': 9,
            'Asia/Shanghai': 8,
            'Asia/Yekaterinburg': 5,
            'Asia/Novosibirsk': 7,
            'Asia/Vladivostok': 10,
            'Asia/Krasnoyarsk': 7,
            'Asia/Omsk': 6,
            'Asia/Irkutsk': 8,
            'Asia/Magadan': 11,
            'Asia/Kamchatka': 12
        };
        
        if (offsetMap[timezoneName] !== undefined) {
            offsetInput.value = offsetMap[timezoneName];
        }
    });
});
</script>

<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_admin.php');
?>
