<?php

namespace Drupal\KernelTests\Core\DependencyInjection;

use Drupal\KernelTests\KernelTestBase;
use Drupal\services_bind_test\TestService;

/**
 * Tests autoconfiguration of services.
 *
 * @group DependencyInjection
 */
class BindAndNamedArgumentsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['services_bind_test'];

  /**
   * Tests 'bind' and named arguments.
   */
  public function testBindAndNamedArguments(): void {
    $names = preg_grep('@^services_bind_test.test_service($|\.)@', $this->container->getServiceIds());
    $actual = [];
    foreach ($names as $name) {
      try {
        $service = $this->container->get($name);
      }
      catch (\Throwable $e) {
        throw new \Exception("Failed to get service '$name'.", 0, $e);
      }
      $this->assertInstanceOf(TestService::class, $service);
      $actual[$name] = $service->export();
    }
    $expected = [
      'services_bind_test.test_service.bind' => [
        'default',
        'other',
        'test parameter value',
        'test string value',
        123,
      ],
      'services_bind_test.test_service.override_arg_0' => [
        'override',
        'other',
        'test parameter value',
        'test string value',
        123,
      ],
      'services_bind_test.test_service.override_arg_named' => [
        'override',
        'override',
        'overridden parameter',
        'overridden string value',
        456,
      ],
      'services_bind_test.test_service.override_arg_typed' => [
        'override',
        'override',
        'test parameter value',
        'test string value',
        123,
      ],
    ];
    $this->assertSame($expected, $actual);
  }

}
