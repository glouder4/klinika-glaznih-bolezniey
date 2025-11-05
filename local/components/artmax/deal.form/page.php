<?php
// Редирект на календарь, если нет параметра IFRAME=Y
if (!isset($_REQUEST["IFRAME"]) || $_REQUEST["IFRAME"] !== "Y") {
    // Используем JavaScript редирект для надежности
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta http-equiv="refresh" content="0;url=/page/artmax_calendar/calendar_branch_1/">
        <script type="text/javascript">
            window.location.href = '/page/artmax_calendar/calendar_branch_1/';
        </script>
    </head>
    <body>
        <p>Перенаправление...</p>
    </body>
    </html>
    <?php
    exit;
}

// Проверяем, открыта ли страница в iframe SidePanel
if (isset($_REQUEST["IFRAME"]) && $_REQUEST["IFRAME"] === "Y") {
    // Адаптация для iframe режима
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
    
    // Получаем ID события из параметров
    $eventId = (int)($_REQUEST['EVENT_ID'] ?? 0);
    
    $APPLICATION->SetTitle("Добавить или выбрать сделку");
    
    // Подключаем библиотеку SidePanel
    CJSCore::Init("sidepanel");
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <script type="text/javascript">
            // Prevent loading page without header and footer
            if(window == window.top)
            {
                window.location = "<?=CUtil::JSEscape($APPLICATION->GetCurPageParam('', array('IFRAME'))); ?>";
            }
        </script>
        <?$APPLICATION->ShowHead();?>
        <?$APPLICATION->ShowHeadStrings();?>
    </head>
    <body class="artmax-sidepanel-body">
        <div class="artmax-sidepanel-content">
            <?
            $APPLICATION->IncludeComponent(
                "artmax:deal.form",
                ".default",
                [
                    'EVENT_ID' => $eventId
                ]
            );
            ?>
        </div>
    </body>
    </html>
    <?
    require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php');
    exit;
} else {
    // Обычный режим (не iframe)
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

    $eventId = (int)($_REQUEST['EVENT_ID'] ?? 0);

    $APPLICATION->SetTitle("Добавить или выбрать сделку");

    // Вызываем компонент
    $APPLICATION->IncludeComponent(
        "artmax:deal.form",
        ".default",
        [
            'EVENT_ID' => $eventId
        ]
    );

    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
}

