<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Kernel;

use Drupal\Core\Extension\Extension;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests user_install().
 */
#[Group('user')]
#[RunTestsInSeparateProcesses]
class UserInstallTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    require_once \DRUPAL_ROOT . '/core/includes/install.inc';
    $this->container->get('module_handler')->loadInclude('user', 'install');
    $this->installEntitySchema('user');
    $name = 'user';
    $module_path = \Drupal::service('extension.list.module')
      ->getPath($name);
    $pathname = "$module_path/$name.info.yml";
    $extension = new Extension($this->root, 'module', $pathname, NULL);
    $class_name = install_get_fixed_class_name($extension, 'Install/Installer');
    $install_class = \Drupal::classResolver($class_name);
    /** @var \Drupal\Core\Extension\Install\InstallInterface $install_class */
    $install_class->doInstall(FALSE);
  }

  /**
   * Tests that the initial users have correct values.
   */
  public function testUserInstall(): void {
    $user_ids = \Drupal::entityQuery('user')->sort('uid')->accessCheck(FALSE)->execute();
    $users = \Drupal::entityTypeManager()->getStorage('user')->loadMultiple($user_ids);
    $anon = $users[0];
    $admin = $users[1];
    $this->assertNotEmpty($anon->uuid(), 'Anon user has a UUID');
    $this->assertNotEmpty($admin->uuid(), 'Admin user has a UUID');

    // Test that the anonymous and administrators languages are equal to the
    // site's default language.
    $this->assertEquals('en', $anon->language()->getId());
    $this->assertEquals('en', $admin->language()->getId());

    // Test that the administrator is active.
    $this->assertTrue($admin->isActive());
    // Test that the anonymous user is blocked.
    $this->assertTrue($anon->isBlocked());
  }

}
