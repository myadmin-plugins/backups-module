<?php

namespace Detain\MyAdminBackups;

use Symfony\Component\EventDispatcher\GenericEvent;

class Plugin {

	public static $name = 'Backup Services Module';
	public static $description = 'Allows selling of Backup Services Module';
	public static $help = '';
	public static $module = 'backups';
	public static $type = 'module';


	public function __construct() {
	}

	public static function Hooks() {
		return [
			'backups.load_processing' => ['Detain\MyAdminBackups\Plugin', 'Load'],
			'backups.settings' => ['Detain\MyAdminBackups\Plugin', 'Settings'],
		];
	}

	public static function Load(GenericEvent $event) {

	}

	public static function Settings(GenericEvent $event) {
		$settings = $event->getSubject();
		$settings->add_dropdown_setting('backups', 'General', 'outofstock_backups', 'Out Of Stock Backups', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_BACKUPS'), array('0', '1'), array('No', 'Yes', ));
	}
}
