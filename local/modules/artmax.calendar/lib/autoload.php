<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

// Автозагрузка классов модуля
spl_autoload_register(function ($class) {
    // Проверяем, что класс принадлежит нашему модулю
    if (strpos($class, 'Artmax\\Calendar\\') !== 0) {
        return;
    }

    // Преобразуем namespace в путь к файлу
    $path = str_replace('\\', '/', $class);
    $path = str_replace('Artmax/Calendar/', '', $path);
    $file = __DIR__ . '/' . $path . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
}); 