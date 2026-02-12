<?php

declare(strict_types=1);

namespace Drupal\Tests\toolbar\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests height calculation of toolbar.
 */
#[Group('toolbar')]
#[RunTestsInSeparateProcesses]
class ToolbarHeightCalculationTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'filter',
    'toolbar',
    'toolbar_test_toolbar_items',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'toolbar_test_theme';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->config('system.site')
      ->set('page.front', '/filter/tips')
      ->save();

    $test_user = $this->drupalCreateUser([
      'access content',
      'access toolbar',
      'administer modules',
    ]);
    $this->drupalLogin($test_user);
  }

  /**
   * Tests that the toolbar does not overlap elements inside the main canvas.
   *
   * @param array $additional_toolbar_items
   *   Additional toolbar items to create.
   * @param array $additional_menu_items
   *   Additional menu items to create.
   */
  #[DataProvider('toolbarHeightCalculationTestCases')]
  public function testToolbarHeightCalculation(array $additional_toolbar_items, array $additional_menu_items): void {
    if (!empty($additional_toolbar_items)) {
      \Drupal::state()->set('toolbar_test_toolbar_items', $additional_toolbar_items);
    }
    if (!empty($additional_menu_items)) {
      \Drupal::state()->set('toolbar_test_menu_items', $additional_menu_items);
    }
    if (!empty($additional_toolbar_items) || !empty($additional_menu_items)) {
      \Drupal::service('cache.render')->deleteAll();
    }

    $this->drupalGet('user');
    $page = $this->getSession()->getPage();
    $body = $this->assertSession()->waitForElement('css', 'body.toolbar-horizontal.toolbar-fixed:not(.toolbar-tray-open)[style*="padding-top"]');

    $this->assertNotEmpty($body);
    $this->assertFalse($body->getAttribute('style') === 'padding-top: 0px;');

    // Test that the Home link can be clicked.
    $home_link = $page->find('css', 'a.js-toolbar-test-home-link');
    $home_link->click();
    $this->assertSession()->addressEquals('');

    // Go back to the modules page, and try to click the Home breadcrumb after
    // the toolbar tray expanded.
    $this->drupalGet('user');
    $this->assertNotEmpty($this->assertSession()->waitForElement('css', 'body.toolbar-horizontal.toolbar-fixed:not(.toolbar-tray-open)[style*="padding-top"]'));
    $toolbar_tray_extend_link = $page->findLink('Extend');
    $this->assertFalse($toolbar_tray_extend_link->isVisible(), 'Toolbar tray is closed after clicking the "Manage" link.');
    $page->clickLink('Manage');
    $this->assertTrue($toolbar_tray_extend_link->isVisible(), 'Toolbar tray is open.');
    // Test that the Home link can be clicked.
    $home_link->click();
    $this->assertSession()->addressEquals('');

    // Go back to the modules page. (Toolbar will be expanded.)
    // Try to click the Home breadcrumb after
    // the toolbar tray was collapsed, and then expanded, again.
    $this->drupalGet('user');
    $this->assertNotEmpty($this->assertSession()->waitForElement('css', 'body.toolbar-horizontal.toolbar-fixed.toolbar-tray-open[style*="padding-top"]'));
    $this->assertNotEmpty($this->assertSession()->waitForElement('css', '[data-toolbar-tray="toolbar-item-administration-tray"].is-active'));
    $page->clickLink('Manage');
    $this->assertNotEmpty($this->assertSession()->waitForElement('css', '[data-toolbar-tray="toolbar-item-administration-tray"]:not(.is-active)'));
    $page->clickLink('Manage');
    $this->assertNotEmpty($this->assertSession()->waitForElement('css', '[data-toolbar-tray="toolbar-item-administration-tray"].is-active'));
    // Test that the Home link can be clicked.
    $home_link->click();
    $this->assertSession()->addressEquals('');
  }

  /**
   * Test cases for ::testToolbarHeightCalculation.
   *
   * @return array
   *   An array of test cases.
   */
  public static function toolbarHeightCalculationTestCases(): array {
    return [
      'No extra modules' => [
        'additional_toolbar_items' => [],
        'additional_menu_items' => [],
      ],
      'Additional toolbar items follow Manage tab' => [
        'additional_toolbar_items' => range(0, 2),
        'additional_menu_items' => [],
      ],
      'Additional toolbar items precede Manage tab' => [
        'additional_toolbar_items' => [
          ['weight' => -102],
          ['weight' => -101],
          ['weight' => -100],
        ],
        'additional_menu_items' => [],
      ],
      'Additional menu items for toolbar' => [
        'additional_toolbar_items' => [],
        'additional_menu_items' => range(0, 5),
      ],
    ];
  }

}
