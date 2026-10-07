<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\TestTools\Attribute\ShareEnvironment;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests account saving for arbitrary new uid.
 */
#[Group('user')]
#[RunTestsInSeparateProcesses]
#[ShareEnvironment]
class UserSaveTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUpEnvironment(): void {
    parent::setUpEnvironment();
    $this->installEntitySchema('user');
  }

  /**
   * Ensures that an existing password is unset after the user was saved.
   */
  public function testExistingPasswordRemoval(): void {
    /** @var \Drupal\user\Entity\User $user */
    $user = User::create(['name' => $this->randomMachineName()]);
    $user->save();
    $user->setExistingPassword('existing password');
    $this->assertNotNull($user->pass->existing);
    $user->save();
    $this->assertNull($user->pass->existing);
  }

}
