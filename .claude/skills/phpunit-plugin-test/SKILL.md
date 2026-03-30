---
name: phpunit-plugin-test
description: Creates or extends PHPUnit tests in `tests/PluginTest.php` following the bootstrap pattern in `tests/bootstrap.php` (dual autoloader, `PRORATE_BILLING` constant). Namespace `Detain\MyAdminBackups\Tests\`. Run with `vendor/bin/phpunit --bootstrap tests/bootstrap.php tests/ -v`. Use when user says 'add test', 'write unit test', 'test plugin', or modifies `tests/`. Do NOT use for integration tests requiring the full MyAdmin platform.
---
# phpunit-plugin-test

## Critical

- **Never** mock `$GLOBALS['tf']`, the DB, or framework functions — the test suite deliberately avoids the MyAdmin runtime. Use `ReflectionClass` and `file_get_contents` instead.
- `PRORATE_BILLING` must be defined before `Plugin` is loaded. It is defined in `tests/bootstrap.php`; do not redefine it in test files.
- All tests go in `tests/PluginTest.php`, namespace `Detain\MyAdminBackups\Tests\`, class `PluginTest extends TestCase`.
- Always run via `vendor/bin/phpunit --bootstrap tests/bootstrap.php tests/ -v` — not via `composer test` (that targets the parent project's phpunit config).

## Instructions

1. **Read `tests/bootstrap.php`** to confirm the dual-autoloader chain (`../../../../autoload.php` → manual `spl_autoload_register`). Do not modify this file.

2. **Open `tests/PluginTest.php`**. The class structure is fixed:
   ```php
   namespace Detain\MyAdminBackups\Tests;

   use PHPUnit\Framework\TestCase;
   use ReflectionClass;
   use ReflectionMethod;

   class PluginTest extends TestCase
   {
       private $reflection;
       private $sourceFile;

       protected function setUp(): void
       {
           $this->reflection = new ReflectionClass(\Detain\MyAdminBackups\Plugin::class);
           $this->sourceFile = dirname(__DIR__) . '/src/Plugin.php';
       }
   }
   ```
   Verify `$this->reflection` and `$this->sourceFile` are available before writing any new test method.

3. **Choose a test category** and add the method in the matching section (delimited by `// ---` comments):
   - **Class structure** — `ReflectionClass` assertions (`isInstantiable`, `getParentClass`, `getInterfaceNames`)
   - **Static properties** — `\Detain\MyAdminBackups\Plugin::$propertyName` direct access
   - **Settings array** — `Plugin::$settings['KEY']` with `assertSame`, `assertIsInt`, `assertIsBool`, `assertIsString`
   - **getHooks()** — call `Plugin::getHooks()`, assert keys/values/callability
   - **Method signatures** — `$this->reflection->getMethod('name')` → `isPublic()`, `isStatic()`, `getParameters()`
   - **Source-level** — `file_get_contents($this->sourceFile)` + `assertStringContainsString` / `assertMatchesRegularExpression`

4. **Write the test method** following this exact pattern:
   ```php
   /**
    * Tests that <what>.
    *
    * <one sentence explaining why this matters at runtime>.
    */
   public function test<PascalCaseName>(): void
   {
       // single focused assertion block
   }
   ```
   Each test must be `public`, return `void`, and have a docblock explaining the runtime consequence.

5. **Source-level pattern tests** use this form (no runtime execution of Plugin methods):
   ```php
   $source = file_get_contents($this->sourceFile);
   $this->assertStringContainsString('expected_string', $source);
   ```

6. **Verify** by running:
   ```bash
   vendor/bin/phpunit --bootstrap tests/bootstrap.php tests/ -v
   ```
   All existing tests must still pass. New test must appear in output as `OK`.

## Examples

**User says:** "Add a test that verifies `getSettings` calls `add_password_setting` in the source."

**Actions taken:**
1. Read `tests/PluginTest.php` — identify the `// Source-level` section.
2. Add method to that section:
   ```php
   /**
    * Tests that getSettings registers a password field via add_password_setting.
    *
    * Without this call the Acronis credentials would render as plain text in the UI.
    */
   public function testSourceCallsAddPasswordSetting(): void
   {
       $source = file_get_contents($this->sourceFile);
       $this->assertStringContainsString('add_password_setting', $source);
   }
   ```
3. Run `vendor/bin/phpunit --bootstrap tests/bootstrap.php tests/ -v`.

**Result:** New test `testSourceCallsAddPasswordSetting` passes; test count increments by 1.

---

**User says:** "Test that `loadProcessing` is static and takes exactly one `$event` parameter."

```php
public function testLoadProcessingSignature(): void
{
    $method = $this->reflection->getMethod('loadProcessing');
    $this->assertTrue($method->isPublic());
    $this->assertTrue($method->isStatic());
    $params = $method->getParameters();
    $this->assertCount(1, $params);
    $this->assertSame('event', $params[0]->getName());
}
```

## Common Issues

- **`Class 'Detain\MyAdminBackups\Plugin' not found`**: The autoloader chain failed. Run `composer install` in the package root. If running inside the parent project, confirm `../../../../autoload.php` resolves to the parent's autoloader.

- **`Use of undefined constant PRORATE_BILLING`**: You loaded `Plugin.php` before `tests/bootstrap.php` ran. Always pass `--bootstrap tests/bootstrap.php` — never omit it.

- **`Cannot access protected property`** when using `$this->reflection->getProperty()`: Call `$rp->setAccessible(true)` before `$rp->getValue()` (only needed for protected/private; all Plugin properties are public).

- **Test count mismatch in `testClassMethodCount`**: You added a new public method to `src/Plugin.php` without updating the assertion from `assertCount(5, $methods)`. Update the expected count to match the new method total.

- **`testSettingsArrayCount` fails after adding a settings key**: Update `assertCount(17, ...)` to the new count and add a corresponding key to the `$expected` array in `testSettingsContainsRequiredKeys`.

- **PHPUnit version mismatch (`assertMatchesRegularExpression` not found`)**: Requires PHPUnit ≥ 9. Check `composer.json` requires `phpunit/phpunit: ^9`. Run `composer install` to enforce it.
