<?php
$branchId = (int)($_REQUEST['BRANCH_ID'] ?? 0);
$redirectUrl = $branchId > 0
    ? "/page/artmax_calendar/calendar_branch_{$branchId}/"
    : "/page/artmax_calendar/";

// Редирект на календарь, если нет параметра IFRAME=Y
if (!isset($_REQUEST["IFRAME"]) || $_REQUEST["IFRAME"] !== "Y") {
    header('Location: ' . $redirectUrl);
    exit;
}

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
            // Предотвращаем редирект при закрытии SidePanel
            window._isClosingSidePanel = false;
            
            // Prevent loading page without header and footer
            // Проверяем, что мы не в iframe (SidePanel)
            // Используем более надежную проверку с несколькими условиями
            (function() {
                try {
                    var isInIframe = window.self !== window.top;
                    var hasIframeParam = window.location.search.indexOf('IFRAME=Y') !== -1;
                    var isClosing = window._isClosingSidePanel;
                    
                    // Редирект только если:
                    // 1. Мы не в iframe И
                    // 2. Нет параметра IFRAME=Y И
                    // 3. Не идет процесс закрытия SidePanel
                if (!isInIframe && !hasIframeParam && !isClosing) {
                        var redirectUrl = "<?=CUtil::JSEscape($redirectUrl); ?>";
                        // Добавляем небольшую задержку, чтобы избежать конфликтов с закрытием SidePanel
                        setTimeout(function() {
                            if (!window._isClosingSidePanel) {
                                window.location = redirectUrl;
                            }
                        }, 100);
                    }
                } catch(e) {
                    // Если есть ошибка при проверке (например, из-за cross-origin), значит мы в iframe
                    // Ничего не делаем
                }
            })();
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

