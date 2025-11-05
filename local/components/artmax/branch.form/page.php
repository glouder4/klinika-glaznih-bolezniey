<?php
// Редирект на календарь, если нет параметра IFRAME=Y
if (!isset($_REQUEST["IFRAME"]) || $_REQUEST["IFRAME"] !== "Y") {
    header('Location: /page/artmax_calendar/calendar_branch_1/');
    exit;
}

// Проверяем, открыта ли страница в iframe SidePanel
if (isset($_REQUEST["IFRAME"]) && $_REQUEST["IFRAME"] === "Y") {
    // Адаптация для iframe режима
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
    
    $APPLICATION->SetTitle("Создание филиала");
    
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
                "artmax:branch.form",
                ".default",
                []
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

    $APPLICATION->SetTitle("Создание филиала");

    // Вызываем компонент
    $APPLICATION->IncludeComponent(
        "artmax:branch.form",
        ".default",
        []
    );

    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
}

