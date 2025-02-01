<?php

declare(strict_types=1);

namespace Drupal\Tests\navigation\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\AxeCoreTestTrait;
use Drupal\user\UserInterface;

/**
 * Performs accessibility tests for the navigation module.
 *
 * @group navigation
 * @group accessibility
 */
class AccessibilityTest extends WebDriverTestBase {

  use AxeCoreTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['navigation'];

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
   * Performs accessibility tests for the navigation module.
   *
   * @param string $uri
   *   The path to be tested.
   * @param ?array $options
   *   (optional) Associative array of Axe options.
   *
   * @dataProvider providerTestNavigationModule
   */
  public function testNavigationModule(string $uri, ?array $options = NULL): void {
    $this->drupalGet($uri);
    $this->executeAxe($options);
  }

  /**
   * Data provider for testNavigationModule.
   *
   * @return array
   *   Test cases.
   */
  public static function providerTestNavigationModule(): array {
    return [
      'Claro page' => [
        '/user/1/edit',
      ],
    ];
  }

}
