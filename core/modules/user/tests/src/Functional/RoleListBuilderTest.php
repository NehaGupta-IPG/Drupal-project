<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests \Drupal\user\RoleListBuilder.
 */
#[Group('user')]
#[RunTestsInSeparateProcesses]
class RoleListBuilderTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
  ];

  /**
   * Ensures the rows of the list are built correctly.
   */
  public function testListBuilder(): void {
    // Create a role to view.
    $role = Role::create([
      'id' => 'role_1',
      'label' => 'My Role',
      'description' => 'Lorem ipsum',
    ]);
    $role->save();

    // Log in as an administrator.
    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);

    $this->drupalGet('/admin/people/roles');

    $this->assertSession()->pageTextContains('Name');
    $this->assertSession()->pageTextContains('Description');

    // Check that the role values are displayed correctly.
    $this->assertSession()->pageTextContains('My Role');
    $this->assertSession()->pageTextContains('Lorem ipsum');
  }

}
