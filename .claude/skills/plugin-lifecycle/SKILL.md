---
name: plugin-lifecycle
description: Adds or modifies service lifecycle handlers (enable, reactivate, disable, terminate) inside `loadProcessing()` in `src/Plugin.php`. Use when user says 'add lifecycle handler', 'handle termination', 'reactivate service', 'disable service', or modifies service state logic in the backups module. Covers Acronis (type 10665), DirectAdmin, and generic backend branching with correct DB updates, history tracking, and admin email notifications. Do NOT use for settings UI changes (getSettings) or hook registration (getHooks).
---
# Plugin Lifecycle

## Critical

- **Never use PDO.** Always `$db = get_module_db(self::$module)` and pass `__LINE__, __FILE__` to every `$db->query()` call.
- **Always branch on backend type** in this order: `== 10665` (Acronis) → `get_service_define('DIRECTADMIN_STORAGE')` → else (generic queue).
- **All four handlers** (`setEnable`, `setReactivate`, `setDisable`, `setTerminate`) must be chained and end with `->register()`. Removing any breaks the chain.
- **Do not escape** `$serviceInfo` values in DB queries — they come from the ORM, not user input. `$db->real_escape()` is only needed for external/user input.

## Instructions

1. **Open `src/Plugin.php`** and locate `loadProcessing()`. All lifecycle closures live inside the method chain on `$service`.

2. **Declare variables at the top of each closure** — every closure needs its own locals:
   ```php
   $serviceInfo = $service->getServiceInfo();
   $settings    = get_module_settings(self::$module);
   $serviceTypes = run_event('get_service_types', false, self::$module); // only if needed
   $db          = get_module_db(self::$module);                          // only if DB writes needed
   ```
   Access fields via `$serviceInfo[$settings['PREFIX'].'_fieldname']`.

3. **Branch on backend type** using the exact pattern:
   ```php
   if ($serviceInfo[$settings['PREFIX'].'_type'] == 10665) {
       // Acronis path
       function_requirements('class.AcronisBackup');
       $bkp = new \AcronisBackup($serviceInfo[$settings['PREFIX'].'_id']);
   } elseif ($serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_type'] == get_service_define('DIRECTADMIN_STORAGE')) {
       // DirectAdmin path — dispatch subevent via ORM
   } else {
       // Generic queue path
   }
   ```

4. **Update DB status** after any backend call succeeds:
   ```php
   $db->query("UPDATE {$settings['TABLE']} SET {$settings['PREFIX']}_status='active', {$settings['PREFIX']}_server_status='active' WHERE {$settings['PREFIX']}_id='".$serviceInfo[$settings['PREFIX'].'_id']."'", __LINE__, __FILE__);
   ```
   For pending states use `'pending'` / `'pending-setup'`; for termination use `'deleted'`.

5. **Record history** immediately after every DB status change:
   ```php
   // Table-level change (preferred for status transitions):
   $GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'active', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
   // Module-level queue action (generic/enable/start/destroy):
   $GLOBALS['tf']->history->add(self::$module.'queue', $serviceInfo[$settings['PREFIX'].'_id'], 'initial_install', '', $serviceInfo[$settings['PREFIX'].'_custid']);
   ```

6. **DirectAdmin path** must use ORM + dispatcher + try/catch + admin mail on failure:
   ```php
   $class = '\\MyAdmin\\Orm\\'.get_orm_class_from_table($settings['TABLE']);
   $serviceClass = new $class();
   $serviceClass->load_real($serviceInfo[$settings['PREFIX'].'_id']);
   $subevent = new GenericEvent($serviceClass, [
       'field1'   => $serviceTypes[$serviceClass->getType()]['services_field1'],
       'field2'   => $serviceTypes[$serviceClass->getType()]['services_field2'],
       'type'     => $serviceTypes[$serviceClass->getType()]['services_type'],
       'category' => $serviceTypes[$serviceClass->getType()]['services_category'],
       'email'    => $GLOBALS['tf']->accounts->cross_reference($serviceClass->getCustid())
   ]);
   $success = true;
   try {
       $GLOBALS['tf']->dispatcher->dispatch($subevent, self::$module.'.terminate');
   } catch (\Exception $e) {
       myadmin_log('myadmin', 'error', 'Got Exception '.$e->getMessage(), __LINE__, __FILE__, self::$module, $serviceClass->getId());
       $serverData = get_service_master($serviceClass->getServer(), self::$module);
       $subject = 'Cant Connect to Backups Server to Suspend';
       $email = $subject.'<br>Username '.$serviceClass->getUsername().'<br>Server '.$serverData[$settings['PREFIX'].'_name'].'<br>'.$e->getMessage();
       (new \MyAdmin\Mail())->adminMail($subject, $email, false, 'admin/website_connect_error.tpl');
       $success = false;
   }
   if ($success == true && !$subevent->isPropagationStopped()) {
       myadmin_log(self::$module, 'error', 'Dont know how to deactivate ...', __LINE__, __FILE__, self::$module, $serviceClass->getId());
       $success = false;
   }
   ```

7. **Add admin email** on `setEnable` (generic path) and `setReactivate` (all paths) using Smarty:
   ```php
   $smarty = new \TFSmarty();
   $smarty->assign('backup_name', $serviceTypes[$serviceInfo[$settings['PREFIX'].'_type']]['services_name']);
   $email = $smarty->fetch('email/admin/backup_pending_setup.tpl');
   $subject = 'Backup '.$serviceInfo[$settings['TITLE_FIELD']].' Is Pending Setup';
   (new \MyAdmin\Mail())->adminMail($subject, $email, false, 'admin/backup_pending_setup.tpl');
   ```

8. **Log with module context** for all non-trivial actions:
   ```php
   myadmin_log(self::$module, 'info', 'message text', __LINE__, __FILE__, self::$module, $serviceId);
   ```

9. **Verify the chain ends with `->register()`** — the last method in `loadProcessing()` must be `->register();`. Confirm `setEnable → setReactivate → setDisable → setTerminate → register` order is intact.

10. Run tests: `vendor/bin/phpunit --bootstrap tests/bootstrap.php tests/ -v` — `testSourceContainsServiceLifecycleMethods` must pass.

## Examples

**User says:** "Add termination support for a new backend type `99999`"

**Actions taken:**
1. Read `src/Plugin.php`, locate `setTerminate` closure.
2. Add a new `elseif` branch before the generic `else` for type `99999`:
   ```php
   } elseif ($serviceInfo[$settings['PREFIX'].'_type'] == 99999) {
       $db = get_module_db(self::$module);
       // call new backend API
       $db->query("UPDATE {$settings['TABLE']} SET {$settings['PREFIX']}_server_status='deleted' WHERE {$settings['PREFIX']}_id='".$serviceInfo[$settings['PREFIX'].'_id']."'", __LINE__, __FILE__);
       $GLOBALS['tf']->history->add($settings['TABLE'], 'change_server_status', 'deleted', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
   }
   ```
3. Confirm `->register()` is still the last call.
4. Run `vendor/bin/phpunit --bootstrap tests/bootstrap.php tests/ -v`.

**Result:** Terminate closure handles type `99999`, DB updated to `deleted`, history recorded.

## Common Issues

- **`Call to undefined function get_module_settings()`** — you are running outside the MyAdmin framework. Tests mock these via `tests/bootstrap.php`. Verify bootstrap is passed: `--bootstrap tests/bootstrap.php`.
- **`->register()` missing / PHP fatal on load** — the chain was broken by adding a handler without returning `$service`. Each `set*()` call must return `$service` (framework guarantee); just ensure `->register()` terminates the chain.
- **`Undefined index: services_type`** — `$serviceTypes` was not fetched before the `elseif` branch. Add `$serviceTypes = run_event('get_service_types', false, self::$module);` at the top of the closure.
- **History not recorded** — check `$settings['TABLE']` vs `self::$module.'queue'`: status changes go to `$settings['TABLE']`; queue actions (install, start, destroy) go to `self::$module.'queue'`.
- **Acronis `activate()` returns `false` silently** — this is expected on provisioning failure; the code must set status to `pending-setup` and add a `change_status` history entry rather than leaving state undefined.