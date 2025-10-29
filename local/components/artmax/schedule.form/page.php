<?php
// Проверяем, открыта ли страница в iframe SidePanel
if (isset($_REQUEST["IFRAME"]) && $_REQUEST["IFRAME"] === "Y") {
    // Адаптация для iframe режима
    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
    
    $APPLICATION->SetTitle("Создание расписания");
    
    // Получаем параметры из URL
    $branchId = (int)($_GET['BRANCH_ID'] ?? 1);
    $date = $_GET['DATE'] ?? date('Y-m-d');
    $employeeId = (int)($_GET['EMPLOYEE_ID'] ?? 0);
    
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
                "artmax:schedule.form",
                ".default",
                [
                    "BRANCH_ID" => $branchId,
                    "DATE" => $date,
                    "EMPLOYEE_ID" => $employeeId,
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

    $APPLICATION->SetTitle("Создание расписания");

    // Получаем параметры из URL
    $branchId = (int)($_GET['BRANCH_ID'] ?? 1);
    $date = $_GET['DATE'] ?? date('Y-m-d');
    $employeeId = (int)($_GET['EMPLOYEE_ID'] ?? 0);

    // Вызываем компонент
    $APPLICATION->IncludeComponent(
        "artmax:schedule.form",
        ".default",
        [
            "BRANCH_ID" => $branchId,
            "DATE" => $date,
            "EMPLOYEE_ID" => $employeeId,
        ]
    );

    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
}

