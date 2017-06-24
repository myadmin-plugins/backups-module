<?php

namespace Detain\MyAdminBackups;

use Symfony\Component\EventDispatcher\GenericEvent;

class Plugin {

	public static $name = 'Backup Services Module';
	public static $description = 'Allows selling of Backup Services Module';
	public static $help = '';
	public static $module = 'backups';
	public static $type = 'module';
	public static $settings = [
		'SERVICE_ID_OFFSET' => 2000,
		'USE_REPEAT_INVOICE' => true,
		'USE_PACKAGES' => true,
		'BILLING_DAYS_OFFSET' => 0,
		'IMGNAME' => 'servers_48.png',
		'REPEAT_BILLING_METHOD' => PRORATE_BILLING,
		'DELETE_PENDING_DAYS' => 45,
		'SUSPEND_DAYS' => 14,
		'SUSPEND_WARNING_DAYS' => 7,
		'TITLE' => 'Backup Services',
		'MENUNAME' => 'Backups',
		'EMAIL_FROM' => 'support@interserver.net',
		'TBLNAME' => 'Backups',
		'TABLE' => 'backups',
		'TITLE_FIELD' => 'backup_username',
		'TITLE_FIELD2' => 'backup_ip',
		'PREFIX' => 'backup'];


	public function __construct() {
	}

	public static function getHooks() {
		return [
			self::$module.'.load_processing' => [__CLASS__, 'loadProcessing'],
			self::$module.'.settings' => [__CLASS__, 'getSettings'],
		];
	}

	public static function loadProcessing(GenericEvent $event) {

	}

	public static function getSettings(GenericEvent $event) {
		$settings = $event->getSubject();
		$settings->add_dropdown_setting(self::$module, 'General', 'outofstock_backups', 'Out Of Stock Backups', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_BACKUPS'), array('0', '1'), array('No', 'Yes', ));
	}
}
