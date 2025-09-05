<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

// Подключаем автозагрузку классов
require_once __DIR__ . '/lib/autoload.php';

// Регистрируем обработчики событий
\Artmax\Calendar\Events::registerEvents();

// Регистрируем обработчик OnPageStart для динамической регистрации событий
\Artmax\Calendar\EventHandlers::registerPageStartHandler(); 