<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\DependencyInjection\Container;
use Drupal\Core\Entity\EntityCreateAccessCheck;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\Routing\Route;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityAccessControlHandlerInterface;

/**
 * Tests Drupal\Core\Entity\EntityCreateAccessCheck.
 */
#[CoversClass(EntityCreateAccessCheck::class)]
#[Group('Access')]
#[Group('Entity')]
class EntityCreateAccessCheckTest extends UnitTestCase {

  /**
   * The mocked entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  public $entityTypeManager;

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

    $this->entityTypeManager = $this->createMock('Drupal\Core\Entity\EntityTypeManagerInterface');
  }

  /**
   * Provides test data for testAccess.
   *
   * @return array
   *   An array of test data for testAccess.
   */
  public static function providerTestAccess(): array {
    $no_access = FALSE;
    $access = TRUE;

    return [
      ['', 'entity_test', $no_access, $no_access],
      ['', 'entity_test', $access, $access],
      ['test_entity', 'entity_test:test_entity', $access, $access],
      ['test_entity', 'entity_test:test_entity', $no_access, $no_access],
      ['test_entity', 'entity_test:{bundle_argument}', $access, $access],
      ['test_entity', 'entity_test:{bundle_argument}', $no_access, $no_access],
      ['', 'entity_test:{bundle_argument}', $no_access, $no_access, FALSE],
      // When the bundle is not provided, access should be denied even if the
      // access control handler would allow access.
      ['', 'entity_test:{bundle_argument}', $access, $no_access, FALSE],
    ];
  }

  /**
   * Tests the method for checking access to routes.
   */
  #[DataProvider('providerTestAccess')]
  public function testAccess(string $entity_bundle, string $requirement, bool $access, bool $expected, bool $expect_permission_context = TRUE): void {

    // Set up the access result objects for allowing or denying access.
    $access_result = $access ? AccessResult::allowed()->cachePerPermissions() : AccessResult::neutral()->cachePerPermissions();
    $expected_access_result = $expected ? AccessResult::allowed() : AccessResult::neutral();
    if ($expect_permission_context) {
      $expected_access_result->cachePerPermissions();
    }
    if (!$entity_bundle && !$expect_permission_context) {
      $expected_access_result->setReason("Could not find '{bundle_argument}' request argument, therefore cannot check create access.");
    }

    // Don't expect a call to the access control handler when we have a bundle
    // argument requirement but no bundle is provided.
    if ($entity_bundle || !str_contains($requirement, '{')) {
      $access_control_handler = $this->createMock('Drupal\Core\Entity\EntityAccessControlHandlerInterface');
      $access_control_handler->expects($this->once())
        ->method('createAccess')
        ->with($entity_bundle)
        ->willReturn($access_result);

      $this->entityTypeManager->expects($this->any())
        ->method('getAccessControlHandler')
        ->willReturn($access_control_handler);
    }

    $applies_check = new EntityCreateAccessCheck($this->entityTypeManager);

    $route = $this->getMockBuilder('Symfony\Component\Routing\Route')
      ->disableOriginalConstructor()
      ->getMock();
    $route->expects($this->any())
      ->method('getRequirement')
      ->with('_entity_create_access')
      ->willReturn($requirement);

    $raw_variables = new InputBag();
    if ($entity_bundle) {
      $raw_variables->set('bundle_argument', $entity_bundle);
    }

    $route_match = $this->createMock('Drupal\Core\Routing\RouteMatchInterface');
    $route_match->expects($this->any())
      ->method('getRawParameters')
      ->willReturn($raw_variables);

    $account = $this->createMock('Drupal\Core\Session\AccountInterface');
    $this->assertEquals($expected_access_result, $applies_check->access($route, $route_match, $account));
  }

  /**
   * Tests an exception is thrown if no bundle is specified but is required.
   *
   * @group legacy
   */
  public function testAccessThrowsExceptionWhenNoBundleSpecified(): void {
    // Mock the entity type manager to return an entity type definition that
    // requires a bundle.
    $entityType = $this->createMock(EntityTypeInterface::class);
    $entityType
      ->method('hasKey')
      ->willReturn(TRUE);
    $this->entityTypeManager
      ->method('getDefinition')
      ->willReturn($entityType);

    // Mock the AccessControlHandler.
    $access_control_handler = $this->createMock(EntityAccessControlHandlerInterface::class);
    $access_control_handler
      ->method('createAccess')
      ->willReturn(AccessResult::allowed()->cachePerPermissions());
    $this->entityTypeManager
      ->method('getAccessControlHandler')
      ->willReturn($access_control_handler);

    // Mock the route to return a _entity_create_access requirement
    // that has no bundle set.
    $route = $this->createMock(Route::class);
    $route
      ->method('getRequirement')
      ->with('_entity_create_access')
      ->willReturn('entity_test');

    $route_match = $this->createMock(RouteMatchInterface::class);
    $account = $this->createMock(AccountInterface::class);

    // Expect an exception to be thrown.
    $this->expectDeprecation(
      sprintf(
        'Defining a \'%s\' route requirement without a bundle for an entity type which has bundles (%s) is deprecated in drupal:11.2.0 and will be disallowed in drupal:12.0.0. Specify a bundle, either as a string or as a route parameter placeholder, in the route requirement. See https://www.drupal.org/node/3505093',
        '_entity_create_access',
        'entity_test',
      )
    );
    $accessCheck = new EntityCreateAccessCheck($this->entityTypeManager);
    $accessCheck->access($route, $route_match, $account);
  }

}
