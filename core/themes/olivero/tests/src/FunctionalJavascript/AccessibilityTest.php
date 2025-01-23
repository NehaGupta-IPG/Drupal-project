<?php

declare(strict_types=1);

namespace Drupal\Tests\olivero\FunctionalJavascriptTests;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\AxeCoreTestTrait;

/**
 * Run a basic axe-core test on some pages.
 *
 * @group olivero
 */
class AccessibilityTest extends WebDriverTestBase {

  use AxeCoreTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'olivero';

  /**
   * Test some pages.
   *
   * @dataProvider providerTestAnonymousPages
   */
  public function testAnonymousPages(string $uri, ?array $options = NULL): void {
    $this->drupalGet($uri);
    $this->executeAxe($options);
  }

  /**
   * Data provider for testPages.
   *
   * @return array
   *   Test cases.
   */
  public static function providerTestAnonymousPages(): array {
    return [
      [''],
      ['user/login'],
      ['user/register'],
      ['user/password'],
      ['search/node'],
    ];
  }

}
