<?php

declare(strict_types=1);

namespace Drupal\Tests\system\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests deprecation of drupalSettings.path.currentQuery.
 *
 * @see https://www.drupal.org/node/3620505
 */
#[Group('system')]
#[IgnoreDeprecations]
#[RunTestsInSeparateProcesses]
class PathCurrentQueryDeprecationTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['js_deprecation_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that reading drupalSettings.path.currentQuery triggers a notice.
   */
  public function testCurrentQueryDeprecation(): void {
    $this->expectUserDeprecationMessage('Javascript Deprecation: drupalSettings.path.currentQuery is deprecated in drupal:12.0.0 and is removed from drupal:13.0.0. It has no cache context of its own and can leak query string values into cached pages. Read the current query from window.location instead. See https://www.drupal.org/node/3620505');
    $this->drupalGet('js_deprecation_test', ['query' => ['foo' => 'bar']]);
    $this->getSession()->evaluateScript('return drupalSettings.path.currentQuery;');
    // Ensure that deprecation message from previous page loads will be
    // detected.
    $this->drupalGet('user');
  }

  /**
   * Tests that other drupalSettings.path properties remain unaffected.
   */
  public function testOtherPathPropertiesNotDeprecated(): void {
    $this->drupalGet('js_deprecation_test', ['query' => ['foo' => 'bar']]);
    $current_path = $this->getSession()->evaluateScript('return drupalSettings.path.currentPath;');
    $this->assertSame('js_deprecation_test', $current_path);
  }

}
