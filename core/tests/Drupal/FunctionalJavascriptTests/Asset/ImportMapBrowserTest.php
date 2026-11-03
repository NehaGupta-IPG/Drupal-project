<?php

declare(strict_types=1);

namespace Drupal\FunctionalJavascriptTests\Asset;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that bare imports in attached module scripts are resolved.
 *
 * The import map is emitted by core; the importmap_test module provides
 * import map entries, and module scripts that use the bare import specifiers
 * of those entries.
 */
#[Group('javascript')]
#[Group('browser')]
#[RunTestsInSeparateProcesses]
class ImportMapBrowserTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The preloaded external entry fails to load, and must not fail the test.
   *
   * The import map of the importmap_test module contains an external entry
   * whose URL cannot be fetched in the test environment. The resulting
   * JavaScript console errors must not fail this test.
   *
   * @var bool
   */
  protected $failOnJavascriptConsoleErrors = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['importmap_test'];

  /**
   * Tests that the bare import is resolved with the import map entry.
   */
  public function testBareImportsAreResolved(): void {
    $this->drupalGet('/importmap-test/consumer');
    $this->assertJSCondition('window.importmapTestValue === "foo"');
  }

  /**
   * Tests that the bare import is resolved with the scoped import map entry.
   */
  public function testScopedBareImportsAreResolved(): void {
    $this->drupalGet('/importmap-test/scoped-consumer');
    $this->assertJSCondition('window.importmapTestScopedValue === "foo-scoped"');
  }

  /**
   * Tests that bare imports are resolved in a module script added by HTMX.
   *
   * The import map was sent with the main HTML document, and contains the
   * entries of all known libraries. The HTMX response adds the consumer
   * module script and module preloads for its imports, but no second import
   * map. The HTMX client adds the preloads to the head. See
   * core/misc/htmx/htmx-assets.js.
   */
  public function testHtmxResponseLoadsModuleScript(): void {
    $this->drupalGet('/importmap-test/htmx-trigger');
    // The main HTML document contains a single import map, and no module
    // preloads, since no library with import map entries is attached.
    $this->assertJSCondition("document.querySelectorAll('script[type=\"importmap\"]').length === 1");
    $this->assertJSCondition("document.querySelectorAll('link[rel=\"modulepreload\"]').length === 0");
    $this->assertJSCondition('window.importmapTestValue === undefined');
    $this->assertSession()->buttonExists('Load consumer')->click();
    // The consumer module script was loaded by the HTMX response, and its
    // bare import was resolved with the import map entry.
    $this->assertJSCondition('window.importmapTestValue === "foo"');
    // The HTMX response did not add a second import map.
    $this->assertJSCondition("document.querySelectorAll('script[type=\"importmap\"]').length === 1");
    // The HTMX response added module preloads to the head.
    $this->assertJSCondition("document.querySelectorAll('link[rel=\"modulepreload\"]').length > 0");
  }

}
