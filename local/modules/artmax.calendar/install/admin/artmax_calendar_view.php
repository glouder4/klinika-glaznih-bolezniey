<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

// Получаем ID филиала из URL или GET параметра
$branchId = null;

// Сначала проверяем GET параметр (для .htaccess)
if (isset($_GET['branch_id'])) {
    $branchId = (int)$_GET['branch_id'];
} else {
    // Парсим URL вида /artmax-calendar/{id}
    $requestUri = $_SERVER['REQUEST_URI'];
    if (preg_match('/\/artmax-calendar\/(\d+)/', $requestUri, $matches)) {
        $branchId = (int)$matches[1];
    }
}

// Если ID не найден, показываем ошибку
if (!$branchId) {
    ShowError('ID филиала не указан. Используйте URL вида /artmax-calendar/{id}');
    require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
    die();
}

// Проверяем существование филиала
if (!CModule::IncludeModule('artmax.calendar')) {
    ShowError('Модуль artmax.calendar не установлен');
    require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
    die();
}

$branchObj = new \Artmax\Calendar\Branch();
$branch = $branchObj->getBranch($branchId);

if (!$branch) {
    ShowError('Филиал не найден');
    require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
    die();
}

$APPLICATION->SetTitle('Календарь событий - ' . $branch['NAME']);
$APPLICATION->SetPageProperty('title', 'Календарь событий - ' . $branch['NAME']);

// Подключаем стили
$APPLICATION->SetAdditionalCSS('/local/css/artmax.calendar/style.css');

// Подключаем скрипты
$APPLICATION->AddHeadScript('/local/js/artmax-calendar/script.js');
?>

<div class="calendar-page">
    <div class="calendar-header">
        <h1>Календарь событий</h1>
        <p>Филиал: <strong><?= htmlspecialchars($branch['NAME']) ?></strong></p>
        <p>Управляйте событиями и встречами</p>
    </div>

    <div class="calendar-content">
        <?php
        // Выводим компонент календаря с передачей ID филиала
        $APPLICATION->IncludeComponent(
            'artmax:calendar',
            '',
            [
                'CACHE_TYPE' => 'A',
                'CACHE_TIME' => 3600,
                'EVENTS_COUNT' => 20,
                'SHOW_FORM' => 'Y',
                'BRANCH_ID' => $branchId, // Передаем ID филиала в компонент
            ],
            false
        );
        ?>
    </div>
</div>

<style>
.calendar-page {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.calendar-header {
    text-align: center;
    margin-bottom: 30px;
    padding: 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 10px;
}

.calendar-header h1 {
    margin: 0 0 10px 0;
    font-size: 2.5em;
    font-weight: 300;
}

.calendar-header p {
    margin: 0 0 5px 0;
    font-size: 1.1em;
    opacity: 0.9;
}

.calendar-header p:last-child {
    margin-bottom: 0;
}

.calendar-content {
    background: white;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}
</style>

<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
?> 