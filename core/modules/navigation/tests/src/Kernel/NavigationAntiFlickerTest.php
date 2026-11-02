<?php

declare(strict_types=1);

namespace Drupal\Tests\navigation\Kernel;

use Drupal\Core\Url;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the navigation anti-flicker library placement.
 */
#[Group('navigation')]
#[RunTestsInSeparateProcesses]
class NavigationAntiFlickerTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'navigation',
    'test_page_test',
    'user',
    'system',
    'layout_builder',
    'layout_discovery',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig('navigation');
    $this->installEntitySchema('user');
    $this->setCurrentUser($this->createUser([
      'access navigation',
    ]));
  }

  /**
   * Tests the anti-flicker script is loaded in the document head.
   */
  public function testAntiFlickerLibraryInHead(): void {
    $this->drupalGet(Url::fromRoute('test_page_test.test_page'));
    $this->assertSession()->elementExists(
      'css',
      'head script[src*="navigation.anti-flicker.js"]',
    );
    $this->assertSession()->elementNotExists(
      'xpath',
      '//body//script[contains(., "Drupal.navigation.sidebarExpanded")]',
    );
  }

}
