<?php
// Проверяем, открыта ли страница в iframe SidePanel
if (isset($_REQUEST["IFRAME"]) && $_REQUEST["IFRAME"] === "Y") {
    // Адаптация для iframe режима
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
    
    // Получаем ID филиала из параметров
    $branchId = (int)($_REQUEST['BRANCH_ID'] ?? 0);
    
    if (!$branchId) {
        ShowError('ID филиала не указан');
        require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php');
        exit;
    }
    
    $APPLICATION->SetTitle("Настройки филиала");
    
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
                "artmax:branch.settings",
                ".default",
                [
                    'BRANCH_ID' => $branchId
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

    $branchId = (int)($_REQUEST['BRANCH_ID'] ?? 0);
    
    if (!$branchId) {
        ShowError('ID филиала не указан');
        require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
        exit;
    }

    $APPLICATION->SetTitle("Настройки филиала");

    // Вызываем компонент
    $APPLICATION->IncludeComponent(
        "artmax:branch.settings",
        ".default",
        [
            'BRANCH_ID' => $branchId
        ]
    );

    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
}

