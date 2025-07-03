<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

return [
	'css' => 'dist/add_menu_item.bundle.css',
	'js' => 'dist/add_menu_item.bundle.js',
	'rel' => [
		'main.core',
	],
	'skip_core' => false,
];
