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

}
