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
 * Tests user email validation and storage for historical UASG examples.
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
  public function testUasgValidation(): void {
    foreach (self::uasgMailboxProvider() as $label => [$mailbox, $valid]) {
      $account = User::create(['name' => 'mailbox', 'mail' => $mailbox]);
      $violations = $account->validate();
      $this->assertSame($valid, count($violations) === 0, $label . ': ' . $violations);
      foreach ($violations as $violation) {
        $this->assertSame('mail.0.value', $violation->getPropertyPath());
      }
    }
  }

  /**
   * Tests exact storage and reload independently of validation.
   */
  public function testUasgStorage(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('user');
    $index = 0;
    foreach (self::uasgMailboxProvider() as $label => [$mailbox]) {
      // Direct saves do not validate, including the overlong historical example.
      $account = User::create(['name' => 'mailbox-' . $index++, 'mail' => $mailbox]);
      $account->save();
      $storage->resetCache();
      $this->assertSame($mailbox, $storage->load($account->id())->getEmail(), $label);
    }
  }

}
