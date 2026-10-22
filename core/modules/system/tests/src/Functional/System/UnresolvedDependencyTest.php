<?php

namespace Drupal\Tests\system\Functional\System;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests 'Unresolved Dependency' on the admin/modules page'.
 *
 * @group system
 */
class UnresolvedDependencyTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['system_test', 'no_version_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $admin_user = $this->drupalCreateUser([
      'administer site configuration',
      'administer modules',
    ]);
    $this->drupalLogin($admin_user);
  }

  /**
   * Tests that 'Unresolved Dependency' error doesn't show up if module lacks version.
   */
  public function testUnresolvedDependency() {
    $assert = $this->assertSession();

    $dependencies = [
      'no_version_test (>=8.x-5.0)',
    ];

    \Drupal::state()->set('system_test.dependencies', $dependencies);
    $this->drupalGet('admin/modules');
    $assert->elementContains('css', '#edit-modules-module-test-enable-description', 'module_test');
    $assert->elementNotContains('css', '#edit-modules-module-test-enable-description', 'NoVersionTest (&gt;=8.x-5.0) (<span class="admin-missing">incompatible with</span> version )');
  }

}
