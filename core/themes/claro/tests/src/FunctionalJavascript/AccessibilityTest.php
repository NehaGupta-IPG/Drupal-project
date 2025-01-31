<?php

declare(strict_types=1);

namespace Drupal\Tests\claro\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\AxeCoreTestTrait;
use Drupal\user\UserInterface;

/**
 * Run a basic axe-core test on some pages.
 *
 * @group claro
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
   * @param ?array $options
   *   (optional) Associative array of Axe options.
   *
   * @dataProvider providerTestAdminPages
   */
  public function testAdminPages(string $uri, ?array $options = NULL): void {
    $this->drupalGet($uri);
    $this->executeAxe($options);
  }

  /**
   * Data provider for testAdminPages.
   *
   * @return array
   *   Test cases.
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
    ];
  }

}
