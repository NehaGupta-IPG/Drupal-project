<?php

declare(strict_types=1);

namespace Drupal\Tests\navigation\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the update functions of the Navigation module.
 */
#[Group('navigation')]
#[RunTestsInSeparateProcesses]
#[IgnoreDeprecations]
class NavigationUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-11.3.0.bare.standard.php.gz',
    ];
  }

  /**
   * Tests the removal of the navigation_shortcuts block.
   */
  public function testShortcutNavigationBlockUpdate(): void {
    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('navigation'));
    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('shortcut'));

    $config = \Drupal::config('navigation.block_layout');
    $sections = $config->get('sections');

    // Confirm the navigation shortcut block exists.
    // UUID of the navigation_shortcuts block.
    $uuid = '2622e40b-8786-4b8c-8883-19e49da53023';
    $this->assertArrayHasKey($uuid, $sections[0]['components']);

    $this->runUpdates();

    // Confirm the navigation shortcut block has been removed.
    $config = \Drupal::config('navigation.block_layout');
    $sections = $config->get('sections');
    $this->assertArrayNotHasKey($uuid, $sections[0]['components']);
  }

  /**
   * Tests that the Navigation Layout module is installed on update.
   */
  public function testNavigationLayoutInstall(): void {
    $module_handler = \Drupal::moduleHandler();
    $this->assertTrue($module_handler->moduleExists('layout_builder'));
    $this->assertFalse($module_handler->moduleExists('navigation_layout'));

    // Grant the permission that moved to Navigation Layout by editing the role
    // config directly. Saving the role entity would drop the permission, as no
    // installed module provides it until the update function runs.
    $permission = 'configure navigation layout';
    $role_config = $this->config('user.role.authenticated');
    $role_config->set('permissions', [...$role_config->get('permissions'), $permission])->save();

    $this->runUpdates();

    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('navigation_layout'));

    // The role kept the permission and now depends on the new module.
    $role = Role::load('authenticated');
    $this->assertContains($permission, $role->getPermissions());
    $this->assertContains('navigation_layout', $role->getDependencies()['module']);

    // The navigation blocks UI is still available.
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('admin/config/user-interface/navigation-block');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->buttonExists('Enable edit mode');
  }

}
