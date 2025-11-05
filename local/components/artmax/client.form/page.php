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
    
    $APPLICATION->SetTitle("Добавить или выбрать клиента");
    
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
                        // Редиректим на главную страницу календаря
                        var calendarUrl = '/page/artmax_calendar/calendar_branch_1/';
                        // Добавляем небольшую задержку, чтобы избежать конфликтов с закрытием SidePanel
                        setTimeout(function() {
                            if (!window._isClosingSidePanel) {
                                window.location = calendarUrl;
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
                "artmax:client.form",
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

    $APPLICATION->SetTitle("Добавить или выбрать клиента");

    // Вызываем компонент
    $APPLICATION->IncludeComponent(
        "artmax:client.form",
        ".default",
        [
            'EVENT_ID' => $eventId
        ]
    );

    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
}

