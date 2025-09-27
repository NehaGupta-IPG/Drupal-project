<?php

declare(strict_types=1);

namespace Drupal\Tests\claro\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\AxeCoreTestTrait;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Run a basic axe-core test on some pages.
 */
#[Group('claro')]
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
   * A user with permission to access all admin pages & functionality.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create an admin user and log in.
    $this->user = $this->drupalCreateUser(admin: TRUE);
    $this->drupalLogin($this->user);
  }

  /**
   * Test some pages.
   *
   * @param string $uri
   *   The path to be tested.
   * @param ?array $axe_options
   *   (optional) Associative array of Axe options.
   * @param ?array $browser_options
   *   (optional) Associative array of browser options.
   */
  #[DataProvider('providerTestAdminPages')]
  public function testAdminPages(string $uri, ?array $axe_options = NULL, ?array $browser_options = NULL): void {
    $this->drupalGet($uri);
    if (isset($browser_options['window_size'])) {
      $this->getSession()->resizeWindow($browser_options['window_size']['width'], $browser_options['window_size']['height']);
    }
    $this->executeAxe($axe_options);
  }

  /**
   * Data provider for testAdminPages.
   *
   * @return array
   *   Test cases. Each case is a key-value pair. The key is the test name
   *   and the value is an array containing:
   *    - Path to be tested.
   *    - Optional associative array of Axe options.
   *    - Optional associative array of browser options.
   */
  public static function providerTestAdminPages(): array {
    return [
      'User Edit' => [
        '/user/1/edit',
      ],
      'Create Article' => [
        '/node/add/article',
      ],
      'Create Page' => [
        '/node/add/page',
      ],
      'Content Page' => [
        '/admin/content',
      ],
      'Structure Page' => [
        '/admin/structure',
      ],
      'Add content type' => [
        '/admin/structure/types/add',
      ],
      'Add vocabulary' => [
        '/admin/structure/taxonomy/add',
      ],
      // @todo remove the skipped rules below in https://drupal.org/i/3318394.
      'Structure | Block' => [
        '/admin/structure/block',
        [
          'rules' => [
            'color-contrast' => ['enabled' => FALSE],
            'duplicate-id-active' => ['enabled' => FALSE],
            'region' => ['enabled' => FALSE],
          ],
        ],
      ],
      // Tests long breadcrumb for https://drupal.org/i/3223147.
      'Manage text format, mobile' => [
        '/admin/config/content/formats/manage/restricted_html',
        [
          // This is the only way to add WCAG 2.2 tags to defaults.
          // See https://github.com/dequelabs/axe-core/issues/4717.
          'runOnly' => [
            'type' => 'tag',
            'values' => [
              'wcag2a',
              'wcag2aa',
              'wcag21a',
              'wcag21aa',
              'best-practice',
              'wcag22a',
              'wcag22aa',
            ],
          ],
        ],
        [
          'window_size' => [
            // Minimum window size seems to be 500px.
            'width' => 500,
            // Height used by Lighthouse for mobile.
            'height' => 823,
          ],
        ],
      ],
    ];
  }

}
