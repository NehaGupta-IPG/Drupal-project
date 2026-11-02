<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the legacy render_item() method for deprecation in multiple classes.
 *
 * @group user
 * @group legacy
 */
class UserRenderItemLegacyTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['views', 'user'];

  /**
   * Tests the render_item() method deprecation for the Roles class.
   */
  public function testRenderItemDeprecationForRoles(): void {
    $this->expectDeprecation('MultiItemsFieldHandlerInterface::render_item() is deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use renderItem() instead. See https://www.drupal.org/node/3467146');

    /** @var \Drupal\user\Plugin\views\field\Roles $rolesField */
    $rolesField = $this->container->get('plugin.manager.views.field')->createInstance('user_roles', []);

    // Call the deprecated render_item() method.
    $rolesField->render_item(0, ['role' => 'administrator']);
  }

  /**
   * Tests the render_item() method deprecation for the Permissions class.
   */
  public function testRenderItemDeprecationForPermissions(): void {
    $this->expectDeprecation('MultiItemsFieldHandlerInterface::render_item() is deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Use renderItem() instead. See https://www.drupal.org/node/3467146');

    /** @var \Drupal\user\Plugin\views\field\Permissions $permissionsField */
    $permissionsField = $this->container->get('plugin.manager.views.field')->createInstance('user_permissions', []);

    // Call the deprecated render_item() method.
    $permissionsField->render_item(0, ['permission' => 'administer users']);
  }

}
