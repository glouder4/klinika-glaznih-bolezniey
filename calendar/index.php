<?
$hasIframe = isset($_GET['IFRAME']) && $_GET['IFRAME'] === 'Y';
$hasSlider = isset($_GET['IFRAME_TYPE']) && $_GET['IFRAME_TYPE'] === 'SIDE_SLIDER';
if ($hasIframe || $hasSlider) {
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: /calendar/');
    exit;
}

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
IncludeModuleLangFile($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/intranet/public/about/calendar.php");
$APPLICATION->SetTitle(GetMessage("ABOUT_TITLE"));

?>

<?
if (!$hasIframe && !$hasSlider) {
	$APPLICATION->IncludeComponent(
		"art-max:calendar.grid",
		"",
		array(
			"CALENDAR_TYPE" => "company_calendar",
			"ALLOW_SUPERPOSE" => "Y",
			"ALLOW_RES_MEETING" => "Y"
		)
	);
}
else{ ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .message-container {
            max-width: 500px;
            width: 100%;
            animation: fadeIn 0.5s ease-out;
        }

        .message-card {
            background: white;
            border-radius: 20px;
            padding: 48px 40px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            transform: translateY(0);
            transition: transform 0.3s ease;
        }

        .message-card:hover {
            transform: translateY(-5px);
        }

        .icon {
            font-size: 64px;
            margin-bottom: 24px;
            display: inline-block;
            animation: pulse 2s infinite;
        }

        h1 {
            font-size: 28px;
            color: #2d3748;
            margin-bottom: 12px;
            font-weight: 600;
        }

        .subtitle {
            font-size: 16px;
            color: #718096;
            margin-bottom: 32px;
            line-height: 1.5;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: rgba(47, 198, 246, 0.95);
            color: white;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 50px;
            font-weight: 500;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(47, 198, 246, 0.3);
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            background: rgba(47, 198, 246, 1);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(47, 198, 246, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-icon {
            font-size: 18px;
            transition: transform 0.3s ease;
        }

        .btn:hover .btn-icon {
            transform: translateX(4px);
        }

        .help-text {
            margin-top: 24px;
            font-size: 13px;
            color: #a0aec0;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        /* Адаптивность */
        @media (max-width: 640px) {
            .message-card {
                padding: 32px 24px;
            }

            h1 {
                font-size: 24px;
            }

            .btn {
                padding: 12px 28px;
                font-size: 14px;
            }
        }
    </style>
    <div class="message-container">
        <div class="message-card">
            <a href="/calendar/" class="btn" target="_blank">
                <span>Перейти в календарь</span>
                <span class="btn-icon">→</span>
            </a>
            <div class="help-text">
                Неправильный способ доступа. Используйте кнопку выше
            </div>
        </div>
    </div>
<?php }

?>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
