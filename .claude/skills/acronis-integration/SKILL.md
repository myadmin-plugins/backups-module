---
name: acronis-integration
description: Implements Acronis Cloud Backup API calls using `function_requirements('class.AcronisBackup')` and `new \AcronisBackup($id)` with methods `activate()`, `setCustomer(0/1)`, `deleteCustomer()`. Handles response checking (`isset($response->version)`) and fallback to pending-setup status. Use when user says 'Acronis', 'backup activation', or works with service type `10665`. Do NOT use for DirectAdmin or generic backup queue operations.
---
# Acronis Integration

## Critical

- **Always** gate Acronis calls behind `if ($serviceInfo[$settings['PREFIX'].'_type'] == 10665)` — type `10665` is the Acronis service type; all other types use different code paths.
- **Always** call `function_requirements('class.AcronisBackup')` before `new \AcronisBackup(...)` — the class is lazy-loaded and will not be available otherwise.
- **Never** use PDO. All DB writes use `$db->query("UPDATE ...", __LINE__, __FILE__)`.
- Success check for `activate()` is `!== false`. Success check for `setCustomer()` and `deleteCustomer()` is `isset($response->version)`. These differ — do not swap them.

## Instructions

1. **Obtain settings and service info.**
   ```php
   $settings = get_module_settings(self::$module);
   $db = get_module_db(self::$module);
   // $serviceInfo comes from $service->getServiceInfo() inside a lifecycle closure
   ```
   Verify `$settings['PREFIX']` is `'backup'` and `$settings['TABLE']` is `'backups'` before proceeding.

2. **Branch on service type `10665`.**
   ```php
   if ($serviceInfo[$settings['PREFIX'].'_type'] == 10665) {
       // Acronis path — steps 3–6 go here
   }
   ```

3. **Load the class and instantiate with the service ID.**
   ```php
   function_requirements('class.AcronisBackup');
   $bkp = new \AcronisBackup($serviceInfo[$settings['PREFIX'].'_id']);
   ```

4. **Call the appropriate method based on lifecycle action.**

   | Action | Method | Success condition |
   |---|---|---|
   | Enable (new) | `$bkp->activate()` | `$activate !== false` |
   | Reactivate (deleted) | `$bkp->activate()` | `$activate !== false` |
   | Reactivate (suspended) | `$bkp->setCustomer(1)` | `isset($response->version)` |
   | Disable / deactivate | `$bkp->setCustomer(0)` | `isset($response->version)` |
   | Terminate | `$bkp->deleteCustomer()` | (log response; always update status) |

5. **On success: update DB status and add history.**
   ```php
   // activate() success
   $db->query("UPDATE {$settings['TABLE']} SET {$settings['PREFIX']}_status='active', {$settings['PREFIX']}_server_status='active' WHERE {$settings['PREFIX']}_id='".$serviceInfo[$settings['PREFIX'].'_id']."'", __LINE__, __FILE__);
   $GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'active', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);

   // setCustomer(1) success
   $GLOBALS['tf']->history->add(self::$module, $serviceInfo[$settings['PREFIX'].'_id'], 'enable', '', $serviceInfo[$settings['PREFIX'].'_custid']);

   // setCustomer(0) success
   $GLOBALS['tf']->history->add(self::$module, $serviceInfo[$settings['PREFIX'].'_id'], 'disable', '', $serviceInfo[$settings['PREFIX'].'_custid']);
   ```

6. **On failure of `activate()`: fall back to pending-setup.**
   ```php
   $db->query("UPDATE {$settings['TABLE']} SET {$settings['PREFIX']}_status='pending', {$settings['PREFIX']}_server_status='pending-setup' WHERE {$settings['PREFIX']}_id='".$serviceInfo[$settings['PREFIX'].'_id']."'", __LINE__, __FILE__);
   $GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'pending-setup', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
   ```

7. **For termination: log the raw response and mark server_status deleted.**
   ```php
   $response = $bkp->deleteCustomer();
   myadmin_log('myadmin', 'info', 'Acronis Termination Resposne:'.json_encode($response), __LINE__, __FILE__);
   $db->query("UPDATE {$settings['TABLE']} SET {$settings['PREFIX']}_server_status='deleted' WHERE {$settings['PREFIX']}_id='".$serviceInfo[$settings['PREFIX'].'_id']."'", __LINE__, __FILE__);
   $GLOBALS['tf']->history->add($settings['TABLE'], 'change_server_status', 'deleted', $serviceInfo[$settings['PREFIX'].'_id'], $serviceInfo[$settings['PREFIX'].'_custid']);
   ```

8. **For reactivation, check `_server_status` first.**
   ```php
   if ($serviceInfo[$settings['PREFIX'].'_server_status'] === 'deleted') {
       // must re-create: set pending-setup first, then activate()
   } else {
       // account exists: setCustomer(1) to re-enable
   }
   ```
   Verify this branch exists whenever implementing reactivation.

## Examples

**User says:** "Add Acronis support to the disable hook."

**Actions taken:**
1. Detect type `10665` branch in `setDisable` closure.
2. Load class: `function_requirements('class.AcronisBackup')`
3. `$bkp = new \AcronisBackup($serviceInfo[$settings['PREFIX'].'_id'])`
4. `$response = $bkp->setCustomer(0)`
5. Check `isset($response->version)` and add history on success.

**Result** (matches `src/Plugin.php:200–207`):
```php
if ($serviceInfo[$settings['PREFIX'].'_type'] == 10665) {
    function_requirements('class.AcronisBackup');
    $bkp = new \AcronisBackup($serviceInfo[$settings['PREFIX'].'_id']);
    $response = $bkp->setCustomer(0);
    if (isset($response->version)) {
        $GLOBALS['tf']->history->add(self::$module, $serviceInfo[$settings['PREFIX'].'_id'], 'disable', '', $serviceInfo[$settings['PREFIX'].'_custid']);
    }
}
```

## Common Issues

- **`Class 'AcronisBackup' not found`**: `function_requirements('class.AcronisBackup')` was not called before `new \AcronisBackup(...)`. Add it immediately before the constructor call.
- **`activate()` always returns `false`**: Credentials (`ACRONIS_USERNAME`, `ACRONIS_PASSWORD`, `ACRONIS_API_CLIENT_ID`, `ACRONIS_API_SECRET`) are not defined. Check module settings via admin UI or verify constants are defined in the environment.
- **History added but DB not updated (or vice versa)**: History and DB update must both run inside the success block — do not split them across branches.
- **Wrong success check**: Using `isset($response->version)` on `activate()` (should be `!== false`) or using `!== false` on `setCustomer()` (should be `isset($response->version)`) will silently skip updates. Match the method to the table above in Step 4.
- **`setCustomer` called on a deleted account**: If `_server_status === 'deleted'`, calling `setCustomer(1)` will fail. Always check server_status and use `activate()` for deleted accounts instead.