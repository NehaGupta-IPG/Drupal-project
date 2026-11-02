<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Kernel;

// cspell:ignore UASG

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\EmailTestDataTrait;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests user email validation and storage for all internationalized mailbox examples.
 */
#[Group('user')]
#[RunTestsInSeparateProcesses]
class UserEmailInternationalizationTest extends KernelTestBase {

  use EmailTestDataTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['user']);
  }

  /**
   * Tests user validation independently of persistence.
   */
  public function testMailboxValidation(): void {
    $expected = $actual = [];
    foreach (self::allMailboxProvider() as $label => [$mailbox, $valid]) {
      $account = User::create(['name' => 'mailbox', 'mail' => $mailbox]);
      $violations = $account->validate();
      $expected[$label] = $valid;
      $actual[$label] = count($violations) === 0;
      foreach ($violations as $violation) {
        $this->assertSame('mail.0.value', $violation->getPropertyPath(), $label);
      }
    }
    $this->assertSame($expected, $actual);
  }

  /**
   * Tests exact storage and reload independently of validation.
   */
  public function testMailboxStorage(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('user');
    $index = 0;
    foreach (self::allMailboxProvider() as $label => [$mailbox]) {
      // Direct saves do not validate, including the overlong historical example.
      $account = User::create(['name' => 'mailbox-' . $index++, 'mail' => $mailbox]);
      $account->save();
      $storage->resetCache();
      $this->assertSame($mailbox, $storage->load($account->id())->getEmail(), $label);
    }
  }

  /**
   * Tests that an accent in the domain identifies a different mailbox.
   *
   * @see https://www.drupal.org/files/issues/2022-05-10/3045516-24-Email-Address-Internationalization.patch
   */
  public function testAccentedDomainUniqueness(): void {
    // cspell:disable
    User::create(['name' => 'User1', 'mail' => 'sanduhrs@exâmple.eu'])->save();
    $account = User::create(['name' => 'User2', 'mail' => 'sanduhrs@example.eu']);
    // cspell:enable
    $this->assertCount(0, $account->validate());
  }

}
