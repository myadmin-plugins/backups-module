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
		$serviceClass = $event->getSubject();
		myadmin_log(self::$module, 'info', self::$name.' Deactivation', __LINE__, __FILE__, self::$module, $serviceClass->getId());
		if ($serviceClass->getType()  == 10665) {
			function_requirements('class.AcronisBackup');
			$bkp = new \AcronisBackup($serviceClass->getId());
			$response = $bkp->setCustomer(0);
			if (isset($response->version)) {
				$GLOBALS['tf']->history->add(self::$module, $serviceClass->getId(), 'disable', '', $serviceClass->getCustid());
			}
		} elseif (!in_array($serviceClass->getType(), [get_service_define('DIRECTADMIN_STORAGE')])) {
			$GLOBALS['tf']->history->add(self::$module.'queue', $serviceClass->getId(), 'delete', '', $serviceClass->getCustid());
		}
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
						$GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'active', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
					} else {
						$db->query("UPDATE {$settings['TABLE']} SET {$settings['PREFIX']}_status='pending', {$settings['PREFIX']}_server_status='pending-setup' WHERE {$settings['PREFIX']}_id='".$serviceInfo[$settings['PREFIX'].'_id']."'", __LINE__, __FILE__);
						$GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'pending-setup', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
					}
				} elseif ($serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_type'] == get_service_define('DIRECTADMIN_STORAGE')) {
					$db->query("UPDATE {$settings['TABLE']} SET {$settings['PREFIX']}_status='active', {$settings['PREFIX']}_server_status='active' WHERE {$settings['PREFIX']}_id='".$serviceInfo[$settings['PREFIX'].'_id']."'", __LINE__, __FILE__);
					$GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'active', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
				} else {
					$db->query('update '.$settings['TABLE'].' set '.$settings['PREFIX']."_status='pending-setup' where ".$settings['PREFIX']."_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
					$GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'pending-setup', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
					$GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'initial_install', '', $serviceInfo[$settings['PREFIX'].'_custid']);
					$smarty = new \TFSmarty;
					$smarty->assign('backup_name', $serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name']);
					$email = $smarty->fetch('email/admin/backup_pending_setup.tpl');
					$subject = 'Backup '.$serviceInfo[$settings['TITLE_FIELD']].' Is Pending Setup';
					(new \MyAdmin\Mail())->adminMail($subject, $email, false, 'admin/backup_pending_setup.tpl');
				}
			})->setReactivate(function ($service) {
				$serviceTypes = run_event('get_service_types', false, self::$module);
				$serviceInfo = $service->getServiceInfo();
				$settings = get_module_settings(self::$module);
				$db = get_module_db(self::$module);
				if ($serviceInfo[$settings['PREFIX'].'_type'] == 10665) {
					function_requirements('class.AcronisBackup');
					if ($serviceInfo[$settings['PREFIX'].'_server_status'] === 'deleted') {
						$db->query("UPDATE {$settings['TABLE']} SET {$settings['PREFIX']}_status='pending', {$settings['PREFIX']}_server_status='pending-setup' WHERE {$settings['PREFIX']}_id='".$serviceInfo[$settings['PREFIX'].'_id']."'", __LINE__, __FILE__);
						$GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'pending-setup', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
						$bkp = new \AcronisBackup($serviceInfo[$settings['PREFIX'].'_id']);
						$activate = $bkp->activate();
						if ($activate !== false) {
							$GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'active', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
							$db->query("UPDATE {$settings['TABLE']} SET {$settings['PREFIX']}_status='active', {$settings['PREFIX']}_server_status='active' WHERE {$settings['PREFIX']}_id='".$serviceInfo[$settings['PREFIX'].'_id']."'", __LINE__, __FILE__);
						}
					} else {
						$bkp = new \AcronisBackup($serviceInfo[$settings['PREFIX'].'_id']);
						$response = $bkp->setCustomer(1);
						if (isset($response->version)) {
							$db->query("UPDATE {$settings['TABLE']} SET {$settings['PREFIX']}_status='active', {$settings['PREFIX']}_server_status='active' WHERE {$settings['PREFIX']}_id='".$serviceInfo[$settings['PREFIX'].'_id']."'", __LINE__, __FILE__);
							$GLOBALS['tf']->history->add(self::$module, $serviceInfo[$settings['PREFIX'].'_id'], 'enable', '', $serviceInfo[$settings['PREFIX'].'_custid']);
						} else {
							$db->query("UPDATE {$settings['TABLE']} SET {$settings['PREFIX']}_status='pending', {$settings['PREFIX']}_server_status='pending-setup' WHERE {$settings['PREFIX']}_id='".$serviceInfo[$settings['PREFIX'].'_id']."'", __LINE__, __FILE__);
							$GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'pending-setup', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
						}
					}
				} elseif ($serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_type'] == get_service_define('DIRECTADMIN_STORAGE')) {
					$class = '\\MyAdmin\\Orm\\'.get_orm_class_from_table($settings['TABLE']);
					/** @var \MyAdmin\Orm\Product $class **/
					$serviceClass = new $class();
					$serviceClass->load_real($serviceInfo[$settings['PREFIX'].'_id']);
					$subevent = new GenericEvent($serviceClass, [
						'field1' => $serviceTypes[$serviceClass->getType()]['services_field1'],
						'field2' => $serviceTypes[$serviceClass->getType()]['services_field2'],
						'type' => $serviceTypes[$serviceClass->getType()]['services_type'],
						'category' => $serviceTypes[$serviceClass->getType()]['services_category'],
						'email' => $GLOBALS['tf']->accounts->cross_reference($serviceClass->getCustid())
					]);
					$success = true;
					try {
						$GLOBALS['tf']->dispatcher->dispatch($subevent, self::$module.'.reactivate');
					} catch (\Exception $e) {
						myadmin_log('myadmin', 'error', 'Got Exception '.$e->getMessage(), __LINE__, __FILE__, self::$module, $serviceClass->getId());
						$serverData = get_service_master($serviceClass->getServer(), self::$module);
						$subject = 'Cant Connect to Webhosting Server to Suspend';
						$email = $subject.'<br>Username '.$serviceClass->getUsername().'<br>Server '.$serverData[$settings['PREFIX'].'_name'].'<br>'.$e->getMessage();
						(new \MyAdmin\Mail())->adminMail($subject, $email, false, 'admin/website_connect_error.tpl');
						$success = false;
					}
					if ($success == true && !$subevent->isPropagationStopped()) {
						myadmin_log(self::$module, 'error', 'Dont know how to deactivate '.$settings['TBLNAME'].' '.$serviceInfo[$settings['PREFIX'].'_id'].' Type '.$serviceTypes[$serviceClass->getType()]['services_type'].' Category '.$serviceTypes[$serviceClass->getType()]['services_category'], __LINE__, __FILE__, self::$module, $serviceClass->getId());
						$success = false;
					}
					if ($success == true) {
						$serviceClass->setServerStatus('deleted')->save();
						$GLOBALS['tf']->history->add($settings['TABLE'], 'change_server_status', 'deleted', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
					}
					
				} else {
					if ($serviceInfo[$settings['PREFIX'].'_server_status'] === 'deleted' || $serviceInfo[$settings['PREFIX'].'_ip'] == '') {
						$GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'pending-setup', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
						$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='pending-setup' where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
						$GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'initial_install', '', $serviceInfo[$settings['PREFIX'].'_custid']);
					} else {
						$GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'active', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
						$db->query("update {$settings['TABLE']} set {$settings['PREFIX']}_status='active' where {$settings['PREFIX']}_id='{$serviceInfo[$settings['PREFIX'].'_id']}'", __LINE__, __FILE__);
						$GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'enable', '', $serviceInfo[$settings['PREFIX'].'_custid']);
						$GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'start', '', $serviceInfo[$settings['PREFIX'].'_custid']);
					}
				}
				$smarty = new \TFSmarty;
				$smarty->assign('backup_name', $serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name']);
				$email = $smarty->fetch('email/admin/backup_reactivated.tpl');
				$subject = $serviceInfo[$settings['TITLE_FIELD']].' '.$serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name'].' '.$settings['TBLNAME'].' Reactivated';
				(new \MyAdmin\Mail())->adminMail($subject, $email, false, 'admin/backup_reactivated.tpl');
			})->setDisable(function ($service) {
				$serviceInfo = $service->getServiceInfo();
				$settings = get_module_settings(self::$module);
				if ($serviceInfo[$settings['PREFIX'].'_type'] == 10665) {
					function_requirements('class.AcronisBackup');
					$bkp = new \AcronisBackup($serviceInfo[$settings['PREFIX'].'_id']);
					$response = $bkp->setCustomer(0);
					if (isset($response->version)) {
						$GLOBALS['tf']->history->add(self::$module, $serviceInfo[$settings['PREFIX'].'_id'], 'disable', '', $serviceInfo[$settings['PREFIX'].'_custid']);
					}
				}
			})->setTerminate(function ($service) {
				$serviceInfo = $service->getServiceInfo();
				$settings = get_module_settings(self::$module);
				$serviceTypes = run_event('get_service_types', false, self::$module);
				if ($serviceInfo[$settings['PREFIX'].'_type'] == 10665) {
					$db = get_module_db(self::$module);
					function_requirements('class.AcronisBackup');
					$bkp = new \AcronisBackup($serviceInfo[$settings['PREFIX'].'_id']);
					$bkp->deleteCustomer();
					$db->query("UPDATE {$settings['TABLE']} SET {$settings['PREFIX']}_server_status='deleted' WHERE {$settings['PREFIX']}_id='".$serviceInfo[$settings['PREFIX'].'_id']."'", __LINE__, __FILE__);
					$GLOBALS['tf']->history->add($settings['TABLE'], 'change_server_status', 'deleted', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
				} elseif ($serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_type'] == get_service_define('DIRECTADMIN_STORAGE')) {
					$class = '\\MyAdmin\\Orm\\'.get_orm_class_from_table($settings['TABLE']);
					/** @var \MyAdmin\Orm\Product $class **/
					$serviceClass = new $class();
					$serviceClass->load_real($serviceInfo[$settings['PREFIX'].'_id']);
					$subevent = new GenericEvent($serviceClass, [
						'field1' => $serviceTypes[$serviceClass->getType()]['services_field1'],
						'field2' => $serviceTypes[$serviceClass->getType()]['services_field2'],
						'type' => $serviceTypes[$serviceClass->getType()]['services_type'],
						'category' => $serviceTypes[$serviceClass->getType()]['services_category'],
						'email' => $GLOBALS['tf']->accounts->cross_reference($serviceClass->getCustid())
					]);
					$success = true;
					try {
						$GLOBALS['tf']->dispatcher->dispatch($subevent, self::$module.'.terminate');
					} catch (\Exception $e) {
						myadmin_log('myadmin', 'error', 'Got Exception '.$e->getMessage(), __LINE__, __FILE__, self::$module, $serviceClass->getId());
						$serverData = get_service_master($serviceClass->getServer(), self::$module);
						$subject = 'Cant Connect to Webhosting Server to Suspend';
						$email = $subject.'<br>Username '.$serviceClass->getUsername().'<br>Server '.$serverData[$settings['PREFIX'].'_name'].'<br>'.$e->getMessage();
						(new \MyAdmin\Mail())->adminMail($subject, $email, false, 'admin/website_connect_error.tpl');
						$success = false;
					}
					if ($success == true && !$subevent->isPropagationStopped()) {
						myadmin_log(self::$module, 'error', 'Dont know how to deactivate '.$settings['TBLNAME'].' '.$serviceInfo[$settings['PREFIX'].'_id'].' Type '.$serviceTypes[$serviceClass->getType()]['services_type'].' Category '.$serviceTypes[$serviceClass->getType()]['services_category'], __LINE__, __FILE__, self::$module, $serviceClass->getId());
						$success = false;
					}
					if ($success == true) {
						$serviceClass->setServerStatus('deleted')->save();
						$GLOBALS['tf']->history->add($settings['TABLE'], 'change_server_status', 'deleted', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
					}
					
				} else {
					$GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'destroy', '', $serviceInfo[$settings['PREFIX'].'_custid']);
				}
			})->register();
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getSettings(GenericEvent $event)
	{
		/**
		 * @var \MyAdmin\Settings $settings
		 **/
		$settings = $event->getSubject();
		$settings->add_dropdown_setting(self::$module, _('General'), 'outofstock_backups', _('Out Of Stock Backups'), _('Enable/Disable Sales Of This Type'), $settings->get_setting('OUTOFSTOCK_BACKUPS'), ['0', '1'], ['No', 'Yes']);
		$settings->add_text_setting(_('API'), _('AcronisBackup'), 'acronis_username', _('Login Name'), _('Login Name'), (defined('ACRONIS_USERNAME') ? ACRONIS_USERNAME : ''));
		$settings->add_text_setting(_('API'), _('AcronisBackup'), 'acronis_password', _('Password'), _('Password'), (defined('ACRONIS_PASSWORD') ? ACRONIS_PASSWORD : ''));
	}
}
