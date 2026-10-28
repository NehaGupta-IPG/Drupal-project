<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Kernel;

use Drupal\Core\Extension\Extension;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests unblocking the anonymous user account.
 */
#[Group('user')]
#[RunTestsInSeparateProcesses]
class UserAnonymousActivateTest extends KernelTestBase {

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
   * Tests that the anonymous user cannot be activated.
   */
  public function testAnonymousActivate(): void {
    $accountAnon = \Drupal::entityTypeManager()->getStorage('user')->load(0);

    // Test that the anonymous user is blocked.
    $this->assertTrue($accountAnon->isBlocked());

    // Test that the anonymous user cannot be activated.
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessageIs('The anonymous user account should remain blocked at all times.');
    $accountAnon->activate();
  }

}
