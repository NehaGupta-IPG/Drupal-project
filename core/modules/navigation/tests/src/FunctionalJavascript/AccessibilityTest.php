<?php

declare(strict_types=1);

namespace Drupal\Tests\navigation\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\AxeCoreTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Performs accessibility tests for the navigation module.
 */
#[Group('navigation')]
#[Group('accessibility')]
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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create an admin user and log in.
    $this->drupalLogin($this->drupalCreateUser(admin: TRUE));
  }

  /**
   * Performs accessibility tests for the navigation module.
   *
   * @param string $uri
   *   The path to be tested.
   * @param array|null $options
   *   (optional) Associative array of Axe options.
   */
  #[DataProvider('providerTestNavigationModule')]
  public function testNavigationModule(string $uri, array $options = []): void {
    $this->drupalGet($uri);
    $this->executeAxe($options);
  }

  /**
   * Data provider for testNavigationModule.
   */
  public static function providerTestNavigationModule(): array {
    return [
      'Claro page' => [
        '/user/1/edit',
      ],
    ];
  }

}
