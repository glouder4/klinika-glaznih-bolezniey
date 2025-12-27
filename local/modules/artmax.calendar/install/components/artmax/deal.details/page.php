<?php
$dealId = (int)($_REQUEST['DEAL_ID'] ?? 0);
$eventId = (int)($_REQUEST['EVENT_ID'] ?? 0);
$branchId = (int)($_REQUEST['BRANCH_ID'] ?? 0);
$redirectUrl = '/page/artmax_calendar/calendar_branch_1/';

if (!isset($_REQUEST["IFRAME"]) || $_REQUEST["IFRAME"] !== "Y") {
    header('Location: ' . $redirectUrl);
    exit;
}

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

$APPLICATION->SetTitle("Сделка");
CJSCore::Init("sidepanel");
?>
<!DOCTYPE html>
<html>
<head>
    <?$APPLICATION->ShowHead();?>
    <?$APPLICATION->ShowHeadStrings();?>
</head>
<body class="artmax-sidepanel-body">
    <div class="artmax-sidepanel-content">
        <?php
        $APPLICATION->IncludeComponent(
            "artmax:deal.details",
            ".default",
            [
                'DEAL_ID' => $dealId,
                'EVENT_ID' => $eventId,
                'BRANCH_ID' => $branchId
            ]
        );
        ?>
    </div>
</body>
</html>
<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php');

