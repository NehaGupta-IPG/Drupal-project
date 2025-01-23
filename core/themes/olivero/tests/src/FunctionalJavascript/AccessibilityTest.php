<?php

declare(strict_types=1);

namespace Drupal\Tests\olivero\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\AxeCoreTestTrait;

/**
 * Run a basic axe-core test on some pages.
 *
 * @group olivero
 * @group accessibility
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
  protected $profile = 'nightwatch_a11y_testing';

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
      'Homepage' => [
        '/',
        [
          'rules' => [
            'region' => ['enabled' => FALSE],
          ],
        ],
      ],
      'Login' => [
        '/user/login',
        [
          'rules' => [
            'region' => ['enabled' => FALSE],
          ],
        ],
      ],
      'Search' => [
        '/search/node',
        [
          'rules' => [
            'heading-order' => ['enabled' => FALSE],
            'duplicate-id-aria' => ['enabled' => FALSE],
          ],
        ],
      ],
    ];
  }

}
