<?php

namespace Drupal\KernelTests\Core\DependencyInjection;

use Drupal\KernelTests\KernelTestBase;

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
      $actual[$name] = array_map(
        static function ($value) {
          if (is_object($value)) {
            $vars = get_object_vars($value);
            return reset($vars);
          }
          return $value;
        },
        array_values(get_object_vars($service)),
      );
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
      'services_bind_test.test_service.service_bind' => [
        'override',
        'override',
        'service-bound value replacing the parameter',
        'service-bound string value',
        999,
      ],
      'services_bind_test.test_service.service_bind_and_args' => [
        'override',
        'other',
        'test parameter value',
        'overridden string value',
        123,
      ],
      'services_bind_test.test_service.autowire' => [
        'autowire',
        'autowire 1',
        'autowire attr',
      ],
      'services_bind_test.test_service.autowire_and_bind' => [
        'autowire',
        'override autowire',
        'override autowire',
      ],
    ];
    $this->assertSame($expected, $actual);
  }

}
