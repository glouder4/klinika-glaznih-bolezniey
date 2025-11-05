<?php
// Проверяем, открыта ли страница в iframe SidePanel
if (isset($_REQUEST["IFRAME"]) && $_REQUEST["IFRAME"] === "Y") {
    // Адаптация для iframe режима
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
    
    // Получаем ID события из параметров
    $eventId = (int)($_REQUEST['EVENT_ID'] ?? 0);
    
    if ($eventId <= 0) {
        die('ID события не указан');
    }
    
    $APPLICATION->SetTitle("Редактировать событие");
    
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
                "artmax:event.edit",
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

    if ($eventId <= 0) {
        die('ID события не указан');
    }

    $APPLICATION->SetTitle("Редактировать событие");

    // Вызываем компонент
    $APPLICATION->IncludeComponent(
        "artmax:event.edit",
        ".default",
        [
            'EVENT_ID' => $eventId
        ]
    );

    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
}

