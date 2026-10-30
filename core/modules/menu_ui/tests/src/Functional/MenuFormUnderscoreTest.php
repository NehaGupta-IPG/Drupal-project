<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_ui\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests creating a menu with an underscore in the machine name.
 *
 * @group menu_ui
 */
class MenuFormUnderscoreTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'menu_ui',
    'menu_ui_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with permission to administer menus.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create and log in an admin user with menu permissions.
    $this->adminUser = $this->drupalCreateUser([
      'administer menu',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests that a menu can be created with an underscore in the machine name.
   */
  public function testMenuMachineNameWithUnderscore(): void {
    $this->drupalGet('admin/structure/menu/add');
    $this->assertSession()->statusCodeEquals(200);

    // Fill and submit the form.
    $edit = [
      'label' => 'My Custom Menu',
      'id' => 'custom_menu_test',
      'description' => 'Testing underscores in menu ID',
    ];
    $this->submitForm($edit, 'Save');

    // Check for confirmation message.
    $this->assertSession()->pageTextContains('Menu My Custom Menu has been added.');

    $this->drupalGet('admin/structure/menu/manage/custom_menu_test');
    $this->assertSession()->statusCodeEquals(200);

    // Verify the menu exists.
    $menu_storage = \Drupal::entityTypeManager()->getStorage('menu');
    $menu = $menu_storage->load('custom_menu_test');
    $this->assertNotNull($menu);
    $this->assertEquals('My Custom Menu', $menu->label());
  }

}
