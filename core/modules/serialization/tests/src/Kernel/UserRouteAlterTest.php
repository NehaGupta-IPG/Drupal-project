<?php

declare(strict_types=1);

namespace Drupal\Tests\serialization\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the user routes can be altered.
 */
#[Group('serialization')]
#[RunTestsInSeparateProcesses]
class UserRouteAlterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'serialization',
    'rest',
    'user_route_alter_test',
  ];

  /**
   * Tests the altered 'user.login.http' route.
   */
  public function testUserAlteredRoute(): void {
    /** @var \Drupal\Core\Routing\RouteProviderInterface $route_provider */
    $route_provider = $this->container->get('router.route_provider');

    // Ensure '_format' is set for the rest.login' route.
    $requirements = $route_provider->getRouteByName('rest.login')->getRequirements();
    $this->assertArrayHasKey('_format', $requirements, 'rest.login route has "_format" requirement');
    $this->assertEquals('json|xml', $requirements['_format'], 'rest.login route "_format" requirement is "json|xml"');

    // Ensure the '_access' requirement is set to FALSE for the 'rest.pass'
    // route.
    $requirements = $route_provider->getRouteByName('rest.pass')->getRequirements();
    $this->assertArrayHasKey('_access', $requirements, 'rest.pass route has "_access" requirement');
    $this->assertEquals('FALSE', $requirements['_access'], 'rest.pass route "_access" requirement is "FALSE"');
    // Ensure '_format' is not set for the 'rest.pass' route.
    $this->assertArrayNotHasKey('_format', $requirements, 'rest.pass route does not have "_format" requirement');
  }

}
