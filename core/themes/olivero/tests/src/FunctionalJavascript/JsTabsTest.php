<?php

declare(strict_types=1);

namespace Drupal\Tests\olivero\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests tabs.js functionality.
 */
#[Group('olivero')]
#[RunTestsInSeparateProcesses]
class JsTabsTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'js_tabs_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'olivero';

  /**
   * Tests tabs.js functionality.
   */
  public function testTabs(): void {
    // Request a path that renders the tabs without an active tab.
    $this->drupalGet('js_tabs_test/baz');
  }

}
