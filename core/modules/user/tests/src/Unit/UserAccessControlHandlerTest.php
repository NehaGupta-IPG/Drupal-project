<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\DependencyInjection\Container;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\PhpunitCompatibilityTrait;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserAccessControlHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the user access controller.
 */
#[CoversClass(UserAccessControlHandler::class)]
#[Group('Drupal')]
#[Group('User')]
class UserAccessControlHandlerTest extends UnitTestCase {

  use PhpunitCompatibilityTrait;

  /**
   * The user access controller to test.
   *
   * @var \Drupal\user\UserAccessControlHandler
   */
  protected $accessControlHandler;

  /**
   * The mock user account with view access.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $viewer;

  /**
   * The mock user account with 'view user email addresses' permission.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $emailViewer;

  /**
   * The mock user account that is able to change their own account name.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $owner;

  /**
   * A user with 'administer users' permission.
   *
   * @var \Drupal\Core\Session\AccountInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $account_with_admin_users_perm;

  /**
   * A user with 'administer permissions' permission.
   *
   * @var \Drupal\Core\Session\AccountInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $account_with_admin_perms_perm;

  /**
   * The mocked test field items.
   *
   * @var \Drupal\Core\Field\FieldItemList
   */
  protected $items;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $cache_contexts_manager = $this->prophesize(CacheContextsManager::class);
    $cache_contexts_manager->assertValidTokens()->willReturn(TRUE);
    $cache_contexts_manager->reveal();
    $container = new Container();
    $container->set('cache_contexts_manager', $cache_contexts_manager);
    \Drupal::setContainer($container);

    $this->viewer = $this->createMock('\Drupal\user\UserInterface');
    $this->viewer
      ->expects($this->any())
      ->method('hasPermission')
      ->willReturn(FALSE);
    $this->viewer
      ->expects($this->any())
      ->method('id')
      ->willReturn(1);

    $this->owner = $this->createMock('\Drupal\user\UserInterface');
    $this->owner
      ->expects($this->any())
      ->method('hasPermission')
      ->willReturnMap([
        ['administer users', FALSE],
        ['change own username', TRUE],
      ]);

    $this->owner
      ->expects($this->any())
      ->method('id')
      ->willReturn(2);

    $this->admin = $this->createMock('\Drupal\user\UserInterface');
    $this->admin
      ->expects($this->any())
      ->method('hasPermission')
      ->willreturnValueMap([
        ['administer users', TRUE],
        ['administer permissions', FALSE],
      ]);

    $this->account_with_admin_perms_perm = $this->createMock(AccountInterface::class);
    $this->account_with_admin_perms_perm
      ->expects($this->any())
      ->method('hasPermission')
      ->willreturnValueMap([
        ['administer users', FALSE],
        ['administer permissions', TRUE],
      ]);

    $this->emailViewer = $this->createMock('\Drupal\user\UserInterface');
    $this->emailViewer
      ->expects($this->any())
      ->method('hasPermission')
      ->willReturnMap([
        ['view user email addresses', TRUE],
      ]);
    $this->emailViewer
      ->expects($this->any())
      ->method('id')
      ->willReturn(3);

    $entity_type = $this->createMock('Drupal\Core\Entity\EntityTypeInterface');

    $this->accessControlHandler = new UserAccessControlHandler($entity_type);
    $module_handler = $this->createMock('Drupal\Core\Extension\ModuleHandlerInterface');
    $this->accessControlHandler->setModuleHandler($module_handler);

    $this->items = $this->getMockBuilder('Drupal\Core\Field\FieldItemList')
      ->disableOriginalConstructor()
      ->getMock();
    $this->items
      ->expects($this->any())
      ->method('defaultAccess')
      ->willReturn(AccessResult::allowed());
  }

  /**
   * Asserts correct field access grants for a field.
   *
   * @internal
   */
  public function assertFieldAccess(string $field, string $viewer, string $target, bool $view, bool $edit): void {
    $field_definition = $this->createMock('Drupal\Core\Field\FieldDefinitionInterface');
    $field_definition->expects($this->any())
      ->method('getName')
      ->willReturn($field);

    $this->items
      ->expects($this->any())
      ->method('getEntity')
      ->willReturn($this->{$target});

    foreach (['view' => $view, 'edit' => $edit] as $operation => $result) {
      $result_text = !isset($result) ? 'null' : ($result ? 'true' : 'false');
      $message = "User '$field' field access returns '$result_text' with operation '$operation' for '$viewer' accessing '$target'";
      $this->assertSame($result, $this->accessControlHandler->fieldAccess($operation, $field_definition, $this->{$viewer}, $this->items), $message);
    }
  }

  /**
   * Ensures user name access is working properly.
   */
  #[DataProvider('userNameProvider')]
  public function testUserNameAccess($viewer, $target, $view, $edit): void {
    $this->assertFieldAccess('name', $viewer, $target, $view, $edit);
  }

  /**
   * Provides test data for testUserNameAccess().
   */
  public static function userNameProvider() {
    $name_access = [
      // The viewer user is allowed to see user names on all accounts.
      [
        'viewer' => 'viewer',
        'target' => 'viewer',
        'view' => TRUE,
        'edit' => FALSE,
      ],
      [
        'viewer' => 'owner',
        'target' => 'viewer',
        'view' => TRUE,
        'edit' => FALSE,
      ],
      [
        'viewer' => 'viewer',
        'target' => 'owner',
        'view' => TRUE,
        'edit' => FALSE,
      ],
      // The owner user is allowed to change its own user name.
      [
        'viewer' => 'owner',
        'target' => 'owner',
        'view' => TRUE,
        'edit' => TRUE,
      ],
      // The users-administrator user has full access.
      [
        'viewer' => 'account_with_admin_users_perm',
        'target' => 'owner',
        'view' => TRUE,
        'edit' => TRUE,
      ],
    ];
    return $name_access;
  }

  /**
   * Tests that private user settings cannot be viewed by other users.
   */
  #[DataProvider('hiddenUserSettingsProvider')]
  public function testHiddenUserSettings($field, $viewer, $target, $view, $edit): void {
    $this->assertFieldAccess($field, $viewer, $target, $view, $edit);
  }

  /**
   * Provides test data for testHiddenUserSettings().
   */
  public static function hiddenUserSettingsProvider() {
    $access_info = [];

    $fields = [
      'preferred_langcode',
      'preferred_admin_langcode',
      'timezone',
      'mail',
    ];

    foreach ($fields as $field) {
      $access_info[] = [
        'field' => $field,
        'viewer' => 'viewer',
        'target' => 'viewer',
        'view' => TRUE,
        'edit' => TRUE,
      ];
      $access_info[] = [
        'field' => $field,
        'viewer' => 'viewer',
        'target' => 'owner',
        'view' => FALSE,
        // Anyone with edit access to the user can also edit these fields. In
        // reality edit access will already be checked on entity level and the
        // user without view access will typically not be able to edit.
        'edit' => TRUE,
      ];
      $access_info[] = [
        'field' => $field,
        'viewer' => 'owner',
        'target' => 'owner',
        'view' => TRUE,
        'edit' => TRUE,
      ];
      $access_info[] = [
        'field' => $field,
        'viewer' => 'account_with_admin_users_perm',
        'target' => 'owner',
        'view' => TRUE,
        'edit' => TRUE,
      ];
      $access_info[] = [
        'field' => $field,
        'viewer' => 'account_with_admin_perms_perm',
        'target' => 'owner',
        'view' => FALSE,
        'edit' => TRUE,
      ];
    }

    return $access_info;
  }

  /**
   * Tests that private user settings cannot be viewed by other users.
   */
  #[DataProvider('adminFieldAccessProvider')]
  public function testAdminFieldAccess($field, $viewer, $target, $view, $edit): void {
    $this->assertFieldAccess($field, $viewer, $target, $view, $edit);
  }

  /**
   * Provides test data for testAdminFieldAccess().
   */
  public static function adminFieldAccessProvider() {
    $access_info = [];

    $fields = [
      'status',
      'access',
      'login',
      'init',
    ];

    foreach ($fields as $field) {
      $access_info[] = [
        'field' => $field,
        'viewer' => 'viewer',
        'target' => 'viewer',
        'view' => FALSE,
        'edit' => FALSE,
      ];
      $access_info[] = [
        'field' => $field,
        'viewer' => 'viewer',
        'target' => 'owner',
        'view' => FALSE,
        'edit' => FALSE,
      ];
      $access_info[] = [
        'field' => $field,
        'viewer' => 'account_with_admin_users_perm',
        'target' => 'owner',
        'view' => TRUE,
        'edit' => TRUE,
      ];
    }

    return $access_info;
  }

  /**
   * Tests that passwords cannot be viewed, just edited.
   */
  #[DataProvider('passwordAccessProvider')]
  public function testPasswordAccess($viewer, $target, $view, $edit): void {
    $this->assertFieldAccess('pass', $viewer, $target, $view, $edit);
  }

  /**
   * Provides test data for testPasswordAccess().
   */
  public static function passwordAccessProvider() {
    $pass_access = [
      [
        'viewer' => 'viewer',
        'target' => 'viewer',
        'view' => FALSE,
        'edit' => TRUE,
      ],
      [
        'viewer' => 'viewer',
        'target' => 'owner',
        'view' => FALSE,
        // Anyone with edit access to the user can also edit these fields. In
        // reality edit access will already be checked on entity level and the
        // user without view access will typically not be able to edit.
        'edit' => TRUE,
      ],
      [
        'viewer' => 'owner',
        'target' => 'viewer',
        'view' => FALSE,
        'edit' => TRUE,
      ],
      [
        'viewer' => 'account_with_admin_users_perm',
        'target' => 'owner',
        'view' => FALSE,
        'edit' => TRUE,
      ],
      [
        'viewer' => 'account_with_admin_perms_perm',
        'target' => 'owner',
        'view' => FALSE,
        'edit' => TRUE,
      ],
    ];
    return $pass_access;
  }

  /**
   * Tests that roles can be only be edited by users with permission.
   *
   * @dataProvider rolesAccessProvider
   */
  public function testRolesAccess($viewer, $target, $view, $edit) {
    $this->assertFieldAccess('roles', $viewer, $target, $view, $edit);
  }

  /**
   * Provides test data for testRolesAccess().
   */
  public function rolesAccessProvider()
  {
    $role_access = [
      [
        'viewer' => 'viewer',
        'target' => 'viewer',
        'view' => FALSE,
        'edit' => FALSE,
      ],
      [
        'viewer' => 'viewer',
        'target' => 'owner',
        'view' => FALSE,
        'edit' => FALSE,
      ],
      [
        'viewer' => 'owner',
        'target' => 'viewer',
        'view' => FALSE,
        'edit' => FALSE,
      ],
      [
        'viewer' => 'account_with_admin_users_perm',
        'target' => 'owner',
        'view' => FALSE,
        'edit' => FALSE,
      ],
      [
        'viewer' => 'account_with_admin_perms_perm',
        'target' => 'owner',
        'view' => TRUE,
        'edit' => TRUE,
      ],
    ];
    return $role_access;
  }

  /**
   * Tests the user created field access.
   */
  #[DataProvider('createdAccessProvider')]
  public function testCreatedAccess($viewer, $target, $view, $edit): void {
    $this->assertFieldAccess('created', $viewer, $target, $view, $edit);
  }

  /**
   * Provides test data for testCreatedAccess().
   */
  public static function createdAccessProvider() {
    $created_access = [
      [
        'viewer' => 'viewer',
        'target' => 'viewer',
        'view' => TRUE,
        'edit' => FALSE,
      ],
      [
        'viewer' => 'owner',
        'target' => 'viewer',
        'view' => TRUE,
        'edit' => FALSE,
      ],
      [
        'viewer' => 'account_with_admin_users_perm',
        'target' => 'owner',
        'view' => TRUE,
        'edit' => TRUE,
      ],
      [
        'viewer' => 'account_with_admin_perms_perm',
        'target' => 'owner',
        'view' => TRUE,
        'edit' => FALSE,
      ],
    ];
    return $created_access;
  }

  /**
   * Tests access to a non-existing base field.
   */
  #[DataProvider('NonExistingFieldAccessProvider')]
  public function testNonExistingFieldAccess($viewer, $target, $view, $edit): void {
    // By default everyone has access to all fields that do not have explicit
    // access control.
    // @see EntityAccessControlHandler::checkFieldAccess()
    $this->assertFieldAccess('some_non_existing_field', $viewer, $target, $view, $edit);
  }

  /**
   * Provides test data for testNonExistingFieldAccess().
   */
  public static function NonExistingFieldAccessProvider() {
    $created_access = [
      [
        'viewer' => 'viewer',
        'target' => 'viewer',
        'view' => TRUE,
        'edit' => TRUE,
      ],
      [
        'viewer' => 'owner',
        'target' => 'viewer',
        'view' => TRUE,
        'edit' => TRUE,
      ],
      [
        'viewer' => 'account_with_admin_users_perm',
        'target' => 'owner',
        'view' => TRUE,
        'edit' => TRUE,
      ],
      [
        'viewer' => 'account_with_admin_perms_perm',
        'target' => 'owner',
        'view' => TRUE,
        'edit' => TRUE,
      ],
    ];
    return $created_access;
  }

}
