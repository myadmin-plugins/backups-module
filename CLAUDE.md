# myadmin-backups-module

MyAdmin plugin module providing backup service lifecycle management. Namespace: `Detain\MyAdminBackups\` → `src/`. Backends: Acronis Cloud Backup, DirectAdmin storage.

## Commands

```bash
composer install                          # install deps
vendor/bin/phpunit --bootstrap tests/bootstrap.php tests/ -v   # run tests
vendor/bin/phpunit --coverage-text        # coverage report
```

## Architecture

- **Plugin entry**: `src/Plugin.php` · class `Plugin` with static `$module = 'backups'`, `$settings[]`
- **Hook registration**: `getHooks()` returns map of `backups.load_processing`, `backups.settings`, `backups.deactivate`
- **Service lifecycle**: `loadProcessing()` chains `setEnable()` / `setReactivate()` / `setDisable()` / `setTerminate()` closures, then `->register()`
- **Settings UI**: `getSettings(GenericEvent $event)` calls `$settings->add_text_setting()`, `add_password_setting()`, `add_dropdown_setting()`, `add_master_*` methods
- **CI**: `.github/workflows/` · GitHub Actions CI pipelines for automated testing and static analysis
- **IDE config**: `.idea/` · IDE settings including `inspectionProfiles/`, `deployment.xml`, `encodings.xml`
- **Tests**: `tests/PluginTest.php` · bootstrap `tests/bootstrap.php` (defines `PRORATE_BILLING`, handles dual autoloader paths)
- **Autoload dev**: `Detain\MyAdminBackups\Tests\` → `tests/`

## Key Patterns

**Module helpers:**
```php
$settings = get_module_settings(self::$module); // keys: PREFIX, TABLE, TBLNAME, TITLE, TITLE_FIELD
$db = get_module_db(self::$module);
$serviceTypes = run_event('get_service_types', false, self::$module);
```

**DB queries** (never PDO, always pass `__LINE__, __FILE__`):
```php
$db->query("UPDATE {$settings['TABLE']} SET {$settings['PREFIX']}_status='active' WHERE {$settings['PREFIX']}_id='".$id."'", __LINE__, __FILE__);
```

**Logging:**
```php
myadmin_log(self::$module, 'info', 'message', __LINE__, __FILE__, self::$module, $serviceId);
```

**History tracking:**
```php
$GLOBALS['tf']->history->add($settings['TABLE'], 'change_status', 'active', $serviceId, $custId);
```

**Acronis backend** (type `10665`):
```php
function_requirements('class.AcronisBackup');
$bkp = new \AcronisBackup($serviceId);
$bkp->activate();        // returns false on failure
$bkp->setCustomer(0);   // disable
$bkp->setCustomer(1);   // enable
$bkp->deleteCustomer(); // terminate
```

**DirectAdmin backend**: dispatch via `$GLOBALS['tf']->dispatcher->dispatch($subevent, self::$module.'.terminate')` · wrap in try/catch · use `(new \MyAdmin\Mail())->adminMail()` on failure

**Email notifications** (Smarty templates):
```php
$smarty = new \TFSmarty();
$smarty->assign('backup_name', $serviceTypes[$type]['services_name']);
$email = $smarty->fetch('email/admin/backup_pending_setup.tpl');
(new \MyAdmin\Mail())->adminMail($subject, $email, false, 'admin/backup_pending_setup.tpl');
```

**Settings registration** pattern in `getSettings()`:
```php
$settings->setTarget('module');
$settings->add_dropdown_setting(self::$module, _('General'), 'outofstock_backups', ...);
$settings->add_text_setting(self::$module, _('Acronis Backup'), 'acronis_username', ...);
$settings->add_password_setting(self::$module, _('Acronis Backup'), 'acronis_password', ...);
$settings->setTarget('global');
```

## Conventions

- Commit messages: lowercase, descriptive (`fix for docker`, `backups updates`)
- Tabs for indentation (see `.scrutinizer.yml` coding style)
- `camelCase` for parameters and properties; `UPPER_CASE` for constants
- Never use PDO — always `get_module_db()`
- `$settings['PREFIX']` prefix on all column names (e.g. `backup_id`, `backup_status`)
- `$settings['TABLE']` = `'backups'`; `$settings['PREFIX']` = `'backup'`
- Service type `10665` = Acronis; check `services_type` against `get_service_define('DIRECTADMIN_STORAGE')` for DA
- Static analysis: `.scrutinizer.yml` enforces argument type checks, no trailing whitespace, alphabetical use imports
- Exclude `tests/` from `.codeclimate.yml` and `.scrutinizer.yml` path filters

<!-- caliber:managed:pre-commit -->
## Before Committing

**IMPORTANT:** Before every git commit, you MUST ensure Caliber syncs agent configs with the latest code changes.

First, check if the pre-commit hook is already installed:
```bash
grep -q "caliber" .git/hooks/pre-commit 2>/dev/null && echo "hook-active" || echo "no-hook"
```

- If **hook-active**: the hook handles sync automatically — just commit normally. Tell the user: "Caliber will sync your agent configs automatically via the pre-commit hook."
- If **no-hook**: run Caliber manually before committing:
  1. Tell the user: "Caliber: Syncing agent configs with your latest changes..."
  2. Run: `caliber refresh && git add CLAUDE.md .claude/ .cursor/ .cursorrules .github/copilot-instructions.md .github/instructions/ AGENTS.md CALIBER_LEARNINGS.md .agents/ .opencode/ 2>/dev/null`
  3. After it completes, briefly tell the user what Caliber updated. Then proceed with the commit.

If `caliber` is not found, tell the user: "This project uses Caliber for agent config sync. Run /setup-caliber to get set up."
<!-- /caliber:managed:pre-commit -->

<!-- caliber:managed:learnings -->
## Session Learnings

Read `CALIBER_LEARNINGS.md` for patterns and anti-patterns learned from previous sessions.
These are auto-extracted from real tool usage — treat them as project-specific rules.
<!-- /caliber:managed:learnings -->
