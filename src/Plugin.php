<?php

namespace Detain\MyAdminBackups;

use Symfony\Component\EventDispatcher\GenericEvent;
use \AcronisBackup;

/**
 * Class Plugin
 *
 * @package Detain\MyAdminBackups
 */
class Plugin
{
	public static $name = 'Backup Services';
	public static $description = 'Allows selling of Backups';
	public static $help = '';
	public static $module = 'backups';
	public static $type = 'module';
	public static $settings = [
		'SERVICE_ID_OFFSET' => 2000,
		'USE_REPEAT_INVOICE' => true,
		'USE_PACKAGES' => true,
		'BILLING_DAYS_OFFSET' => 0,
		'IMGNAME' => 'network-drive.png',
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

	/**
	 * Plugin constructor.
	 */
	public function __construct()
	{
	}

	/**
	 * @return array
	 */
	public static function getHooks()
	{
		return [
			self::$module.'.load_processing' => [__CLASS__, 'loadProcessing'],
			self::$module.'.settings' => [__CLASS__, 'getSettings'],
			self::$module.'.deactivate' => [__CLASS__, 'getDeactivate']
		];
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getDeactivate(GenericEvent $event)
	{
		myadmin_log(self::$module, 'info', self::$name.' Deactivation', __LINE__, __FILE__);
		$serviceClass = $event->getSubject();
		$GLOBALS['tf']->history->add(self::$module.'queue', $serviceClass->getId(), 'delete', '', $serviceClass->getCustid());
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function loadProcessing(GenericEvent $event)
	{
		/**
		 * @var \ServiceHandler $service
		 */
		$service = $event->getSubject();
		$service->setModule(self::$module)
			->setEnable(function ($service) {
				$serviceInfo = $service->getServiceInfo();
				$settings = get_module_settings(self::$module);
				$serviceTypes = run_event('get_service_types', false, self::$module);
				$db = get_module_db(self::$module);
				if ($serviceInfo[$settings['PREFIX'].'_type'] == 10665) {
					function_requirements('class.AcronisBackup');
					$bkp = new \AcronisBackup($serviceInfo[$settings['PREFIX'].'_id']);
					$activate = $bkp->activate();
					if ($activate !== false) {
						$db->query("UPDATE {$settings['TABLE']} SET {$settings['PREFIX']}_status='active', {$settings['PREFIX']}_server_status='active' WHERE {$settings['PREFIX']}_id='".$serviceInfo[$settings['PREFIX'].'_id']."'", __LINE__, __FILE__);
						$GLOBALS['tf']->history->add($settings['PREFIX'], 'change_status', 'active', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
					} else {
						$db->query("UPDATE {$settings['TABLE']} SET {$settings['PREFIX']}_status='pending-setup', {$settings['PREFIX']}_server_status='pending-setup' WHERE {$settings['PREFIX']}_id='".$serviceInfo[$settings['PREFIX'].'_id']."'", __LINE__, __FILE__);
						$GLOBALS['tf']->history->add($settings['PREFIX'], 'change_status', 'pending-setup', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
					}
				} else {
					$db->query('update '.$settings['TABLE'].' set '.$settings['PREFIX']."_status='pending-setup' where ".$settings['PREFIX']."_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
					$GLOBALS['tf']->history->add($settings['PREFIX'], 'change_status', 'pending-setup', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
					$GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'initial_install', '', $serviceInfo[$settings['PREFIX'].'_custid']);
					$smarty = new \TFSmarty;
					$smarty->assign('backup_name', $serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name']);
					$email = $smarty->fetch('email/admin/backup_pending_setup.tpl');
					$headers = '';
					$headers .= 'MIME-Version: 1.0'.PHP_EOL;
					$headers .= 'Content-type: text/html; charset=UTF-8'.PHP_EOL;
					$headers .= 'From: '.TITLE.' <'.EMAIL_FROM.'>'.PHP_EOL;
					$subject = 'Backup '.$serviceInfo[$settings['TITLE_FIELD']].' Is Pending Setup';
					admin_mail($subject, $email, $headers, false, 'admin/backup_pending_setup.tpl');
				}
			})->setReactivate(function ($service) {
				$serviceTypes = run_event('get_service_types', false, self::$module);
				$serviceInfo = $service->getServiceInfo();
				$settings = get_module_settings(self::$module);
				$db = get_module_db(self::$module);
				if ($serviceInfo[$settings['PREFIX'].'_server_status'] === 'deleted' || $serviceInfo[$settings['PREFIX'].'_ip'] == '') {
					$GLOBALS['tf']->history->add($settings['PREFIX'], 'change_status', 'pending-setup', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
					$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='pending-setup' where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
					$GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'initial_install', '', $serviceInfo[$settings['PREFIX'].'_custid']);
				} else {
					$GLOBALS['tf']->history->add($settings['PREFIX'], 'change_status', 'active', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
					$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='active' where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
					$GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'enable', '', $serviceInfo[$settings['PREFIX'].'_custid']);
					$GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'start', '', $serviceInfo[$settings['PREFIX'].'_custid']);
				}
				$smarty = new \TFSmarty;
				$smarty->assign('backup_name', $serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name']);
				$email = $smarty->fetch('email/admin/backup_reactivated.tpl');
				$subject = $serviceInfo[$settings['TITLE_FIELD']].' '.$serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name'].' '.$settings['TBLNAME'].' Re-Activated';
				$headers = '';
				$headers .= 'MIME-Version: 1.0'.PHP_EOL;
				$headers .= 'Content-type: text/html; charset=UTF-8'.PHP_EOL;
				$headers .= 'From: '.TITLE.' <'.EMAIL_FROM.'>'.PHP_EOL;
				admin_mail($subject, $email, $headers, false, 'admin/backup_reactivated.tpl');
			})->setDisable(function ($service) {
				if ($serviceInfo[$settings['PREFIX'].'_type'] == 10665) {
					function_requirements('class.AcronisBackup');
					$bkp = new \AcronisBackup($serviceInfo[$settings['PREFIX'].'_id']);
					$disable = $bkp->setCustomer(0);
				}
			})->setTerminate(function ($service) {
				$serviceInfo = $service->getServiceInfo();
				$settings = get_module_settings(self::$module);
				$GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'destroy', '', $serviceInfo[$settings['PREFIX'].'_custid']);
			})->register();
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getSettings(GenericEvent $event)
	{
		$settings = $event->getSubject();
		$settings->add_dropdown_setting(self::$module, 'General', 'outofstock_backups', 'Out Of Stock Backups', 'Enable/Disable Sales Of This Type', $settings->get_setting('OUTOFSTOCK_BACKUPS'), ['0', '1'], ['No', 'Yes']);
		$settings->add_text_setting('API', 'AcronisBackup', 'acronis_login', 'Login Name', 'Login Name', (defined('ACRONIS_USERNAME') ? ACRONIS_USERNAME : ''));
		$settings->add_text_setting('API', 'AcronisBackup', 'acronis_password', 'Password', 'Password', (defined('ACRONIS_PASSWORD') ? ACRONIS_PASSWORD : ''));
	}
}
