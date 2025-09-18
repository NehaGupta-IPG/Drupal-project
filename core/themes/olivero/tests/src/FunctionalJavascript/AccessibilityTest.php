<?php

declare(strict_types=1);

namespace Drupal\Tests\olivero\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\AxeCoreTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Run a basic axe-core test on some pages.
 */
#[Group('olivero')]
#[Group('accessibility')]
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
   * @param string $uri
   *   The path to be tested.
   * @param ?array $options
   *   (optional) Associative array of Axe options.
   */
  #[DataProvider('providerTestAnonymousPages')]
  public function testAnonymousPages(string $uri, ?array $options = NULL): void {
    $this->drupalGet($uri);
    $this->executeAxe($options);
  }

  /**
   * Data provider for testAnonymousPages.
   *
   * @return array
   *   Test cases.
   */
  public static function providerTestAnonymousPages(): array {
    return [
      'Homepage' => [
        '<front>',
        // @todo remove the disabled 'region' rule in https://drupal.org/i/3318396.
        [
          'rules' => [
            'region' => ['enabled' => FALSE],
          ],
        ],
      ],
      'Login' => [
        '/user/login',
        // @todo remove the disabled 'region' rule in https://drupal.org/i/3318396.
        [
          'rules' => [
            'region' => ['enabled' => FALSE],
          ],
        ],
      ],
      'Search' => [
        '/search/node',
        // @todo remove the heading and duplicate id rules below in
        //   https://drupal.org/i/3318398.
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
