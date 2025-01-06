<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Unit\Entity;

use Drupal\Tests\UnitTestCase;
use Drupal\user\Entity\Role;

/**
 * @group user
 * @coversDefaultClass \Drupal\user\Entity\Role
 */
class RoleTest extends UnitTestCase {

  /**
   * Tests getting the role's description.
   *
   * @covers ::getDescription
   */
  public function testGetDescription(): void {
    $role = new Role([
      'id' => 'test_role',
      'description' => 'Lorem ipsum.',
    ], 'user_role');
    $this->assertSame('Lorem ipsum.', $role->getDescription());
  }

  /**
   * Tests getting the role's description when it is not set.
   *
   * @covers ::getDescription
   */
  public function testGetEmptyDescription(): void {
    $role = new Role([
      'id' => 'test_role',
    ], 'user_role');
    $this->assertSame('', $role->getDescription());
  }

  /**
   * Tests setting and getting the role's description.
   *
   * @covers ::getDescription
   * @covers ::setDescription
   */
  public function testSetDescription(): void {
    $role = new Role([
      'id' => 'test_role',
    ], 'user_role');

    $this->assertSame($role, $role->setDescription('Lorem ipsum.'));
    $this->assertSame('Lorem ipsum.', $role->getDescription());
  }

}
