<?php
/* TODO:
 - service type, category, and services  adding
 - dealing with the SERVICE_TYPES_backups define
 - add way to call/hook into install/uninstall
*/
return [
	'name' => 'Backup Services Module',
	'description' => 'Allows selling of Backup Services Module',
	'help' => '',
	'module' => 'backups',
	'author' => 'detain@interserver.net',
	'home' => 'https://github.com/detain/myadmin-backups-module',
	'repo' => 'https://github.com/detain/myadmin-backups-module',
	'version' => '1.0.0',
	'type' => 'module',
	'hooks' => [
		'backups.load_processing' => ['Detain\MyAdminBackups\Plugin', 'Load'],
		'backups.settings' => ['Detain\MyAdminBackups\Plugin', 'Settings'],
		/* 'function.requirements' => ['Detain\MyAdminBackups\Plugin', 'Requirements'],
		'backups.activate' => ['Detain\MyAdminBackups\Plugin', 'Activate'],
		'backups.change_ip' => ['Detain\MyAdminBackups\Plugin', 'ChangeIp'],
		'ui.menu' => ['Detain\MyAdminBackups\Plugin', 'Menu'] */
	],
];
