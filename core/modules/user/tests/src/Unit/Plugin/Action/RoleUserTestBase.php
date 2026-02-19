<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Unit\Plugin\Action;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Provides a base class for user role action tests.
 */
abstract class RoleUserTestBase extends UnitTestCase {

  /**
   * The mocked account.
   *
   * @var \Drupal\user\UserInterface|MockObject
   */
  protected $account;

  /**
   * The user role entity type.
   *
   * @var EntityTypeInterface|Stub
   */
  protected $userRoleEntityType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->account = $this
      ->getMockBuilder('Drupal\user\Entity\User')
      ->disableOriginalConstructor()
      ->getMock();
    $this->userRoleEntityType = $this->createStub(EntityTypeInterface::class);
  }

}
