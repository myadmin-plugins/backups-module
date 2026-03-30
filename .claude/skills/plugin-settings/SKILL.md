---
name: plugin-settings
description: Adds or modifies admin settings in `getSettings(GenericEvent $event)` in `src/Plugin.php`. Covers `add_text_setting()`, `add_password_setting()`, `add_dropdown_setting()`, `add_master_checkbox_setting()`, `add_master_label()`, `add_master_text_setting()` with `setTarget('module')`/`setTarget('global')` framing. Use when user says 'add setting', 'new config option', 'admin panel field', or touches `getSettings`. Do NOT use for lifecycle hooks, `loadProcessing()`, or `getHooks()` changes.
---
# Plugin Settings

## Critical

- `getSettings()` MUST open with `$settings->setTarget('module')` and close with `$settings->setTarget('global')` — omitting either breaks all downstream settings registration.
- All `_('string')` calls are required for i18n — never pass bare string literals as labels or descriptions.
- Default values for credential settings MUST use `(defined('CONST_NAME') ? CONST_NAME : '')` — never hardcode credentials or assume constants exist.
- `add_master_*` methods take the server-level DB field name as the fifth argument — verify the column exists in the master servers table before adding.

## Instructions

1. **Open `src/Plugin.php`** and locate `getSettings(GenericEvent $event)` (around line 260). All edits go inside this method.
   - Verify the method signature is `public static function getSettings(GenericEvent $event)`.

2. **Obtain the `$settings` subject** — already present as:
   ```php
   $settings = $event->getSubject(); // \MyAdmin\Settings
   ```
   The method opens with `$settings->setTarget('module')` and must close with `$settings->setTarget('global')`.

3. **Choose the correct method** based on field type:

   | Field type | Method |
   |---|---|
   | Plain text / URL / hostname | `add_text_setting()` |
   | Secret / token / password | `add_password_setting()` |
   | Enum / yes-no toggle | `add_dropdown_setting()` |
   | Per-server checkbox | `add_master_checkbox_setting()` |
   | Per-server read-only stat | `add_master_label()` |
   | Per-server editable text | `add_master_text_setting()` |

4. **Add a module-level setting** (text, password, or dropdown) using this exact signature order:
   ```php
   // Text
   $settings->add_text_setting(
       self::$module,                  // module slug
       _('Section Label'),             // settings section/group
       'setting_key',                  // snake_case key (stored as SETTING_KEY constant)
       _('Field Label'),               // human label
       _('Field description/hint'),    // help text
       (defined('SETTING_KEY') ? SETTING_KEY : '')
   );

   // Password
   $settings->add_password_setting(
       self::$module, _('Section Label'), 'setting_key',
       _('Field Label'), _('Description'),
       (defined('SETTING_KEY') ? SETTING_KEY : '')
   );

   // Dropdown
   $settings->add_dropdown_setting(
       self::$module, _('General'), 'setting_key',
       _('Field Label'), _('Description'),
       $settings->get_setting('SETTING_KEY'),
       ['0', '1'],          // values array
       ['No', 'Yes']        // labels array
   );
   ```
   - Verify the constant name matches the uppercased key: `acronis_username` → `ACRONIS_USERNAME`.

5. **Add a per-server (master) setting** — these always use `'Server Settings'` as the section and take a DB column expression:
   ```php
   // Checkbox — marks server available/unavailable
   $settings->add_master_checkbox_setting(
       self::$module, 'Server Settings', self::$module,
       'available',          // DB field suffix
       'backup_available',   // full column name
       'Auto-Setup',         // label
       '<p>Description.</p>'
   );

   // Read-only label (aggregate or column)
   $settings->add_master_label(
       self::$module, 'Server Settings', self::$module,
       'active_services',    // display key
       'Active Backups',     // label
       '<p>Description.</p>',
       'count(backups.backup_id) as active_services'  // SQL expression
   );

   // Editable text per server
   $settings->add_master_text_setting(
       self::$module, 'Server Settings', self::$module,
       'max_sites',          // display key
       'backup_max_sites',   // DB column
       'Max Users',          // label
       '<p>Description.</p>'
   );
   ```

6. **Group logically** — place new module-level settings under an existing or new section string (e.g., `_('Acronis Backup')`, `_('General')`). Place all `add_master_*` calls together under `'Server Settings'`.

7. **Verify** by running:
   ```bash
   vendor/bin/phpunit --bootstrap tests/bootstrap.php tests/ -v
   ```
   A settings parse error will surface as a fatal in `PluginTest`.

## Examples

**User says:** "Add an Acronis API endpoint URL setting and a retry count dropdown."

**Actions taken:**
```php
// Inside getSettings(), after existing acronis_api_secret line:
$settings->add_text_setting(
    self::$module, _('Acronis Backup'), 'acronis_api_endpoint',
    _('Acronis API Endpoint'), _('Base URL for the Acronis Cloud API'),
    (defined('ACRONIS_API_ENDPOINT') ? ACRONIS_API_ENDPOINT : '')
);
$settings->add_dropdown_setting(
    self::$module, _('Acronis Backup'), 'acronis_retry_count',
    _('API Retry Count'), _('Number of times to retry failed API calls'),
    $settings->get_setting('ACRONIS_RETRY_COUNT'),
    ['1', '2', '3', '5'],
    ['1', '2', '3', '5']
);
```

**Result:** Two new fields appear in the Acronis Backup section of the admin settings panel.

## Common Issues

- **Setting not appearing in admin panel:** Confirm `setTarget('module')` is called before the `add_*` call and `setTarget('global')` closes the method. Both are required.
- **`Use of undefined constant ACRONIS_FOO`:** The constant isn't defined in the environment config yet. Wrap with `(defined('ACRONIS_FOO') ? ACRONIS_FOO : '')` — never assume the constant exists.
- **Dropdown shows wrong selected value:** `add_dropdown_setting()`'s 6th argument must be `$settings->get_setting('UPPER_SNAKE_KEY')`, not the raw `$_GET`/`$_POST` value.
- **PHPUnit fatal on `PluginTest`:** Usually a missing `_()` wrapper or wrong argument count. Count arguments against the signatures in Step 4 — all six are required for text/password/dropdown.
- **`add_master_label()` shows wrong data:** The 7th argument is a raw SQL expression (`'column as alias'`). Ensure the alias matches the 4th argument (display key) exactly.