<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Kernel;

use Drupal\Core\Database\Database;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests deleting of user accounts.
 */
#[Group('user')]
#[RunTestsInSeparateProcesses]
class UserDeleteTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
  ];

  /**
   * Tests deleting multiple users.
   */
  public function testUserDeleteMultiple(): void {
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('user');

    // Create a few users with permissions, so roles will be created.
    $user_a = $this->createUser(['access user profiles']);
    $user_b = $this->createUser(['access user profiles']);
    $user_c = $this->createUser(['access user profiles']);

    $uids = [$user_a->id(), $user_b->id(), $user_c->id()];

    // These users should have a role.
    $connection = Database::getConnection();
    $roles_created = $this->countRoleAssignments($connection, $uids);

    $this->assertGreaterThan(0, $roles_created);
    // We should be able to load one of the users.
    $this->assertNotNull(User::load($user_a->id()));
    // Delete the users.
    $storage = $this->container->get('entity_type.manager')->getStorage('user');
    $users = $storage->loadMultiple($uids);
    $storage->delete($users);
    // Test if the roles assignments are deleted.
    $roles_after_deletion = $this->countRoleAssignments($connection, $uids);
    $this->assertEquals(0, $roles_after_deletion);
    // Test if the users are deleted, User::load() will return NULL.
    $this->assertNull(User::load($user_a->id()));
    $this->assertNull(User::load($user_b->id()));
    $this->assertNull(User::load($user_c->id()));
  }

  /**
   * Counts the role assignments for the given users.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param array $uids
   *   The user IDs to count role assignments for.
   *
   * @return int
   *   The number of role assignments held by the given users.
   */
  protected function countRoleAssignments($connection, array $uids): int {
    if ($connection->supportsJsonEntityStorage()) {
      // Under JSON entity storage is the 'user__roles' table is not physical:
      // the roles are embedded in each user's 'users_translations' documents
      // (nested as 'users_translations__roles') inside the base 'users' table
      // JSON column.
      $result = $connection->select('users', 'u')
        ->fields('u', ['users_translations'])
        ->execute()
        ->fetchCol();

      $count = 0;
      foreach ($result as $translations) {
        if (is_string($translations)) {
          $translations = json_decode($translations, TRUE);
        }
        foreach ($translations ?? [] as $translation) {
          foreach ($translation['users_translations__roles'] ?? [] as $role) {
            if (in_array((string) $role['entity_id'], array_map('strval', $uids), TRUE)) {
              $count++;
            }
          }
        }
      }
      return $count;
    }
    else {
      return (int) $connection->select('user__roles', 'r')
        ->fields('r', ['entity_id'])
        ->condition('entity_id', $uids, 'IN')
        ->countQuery()
        ->execute()
        ->fetchField();
    }
  }

}
