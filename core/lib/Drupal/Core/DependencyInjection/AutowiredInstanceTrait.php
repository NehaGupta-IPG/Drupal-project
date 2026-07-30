<?php

declare(strict_types=1);

namespace Drupal\Core\DependencyInjection;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\AutowiringFailedException;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Defines a base trait for automatically wiring dependency arguments.
 */
trait AutowiredInstanceTrait {

  /**
   * Array of data to use when calling the constructor.
   */
  protected static array $autowireData = [];

  /**
   * Instantiates a new instance of the implementing class using autowiring.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container this instance should use.
   * @param mixed ...$args
   *   Any predefined arguments to pass to the constructor.
   *
   * @return static
   */
  public static function createInstanceAutowired(ContainerInterface $container, mixed ...$args): static {
    $new_args = [];
    if (!static::$autowireData) {
      static::getServices($container, static::class, count($args));
    }

    foreach (static::$autowireData as $type => $entry) {
      if (is_null($entry)) {
        $new_args[] = NULL;
        continue;
      }

      if ($entry == 'args') {
        array_merge($new_args, $args);
        continue;
      }

      [$type, $value] = explode(':', $entry);

      if ($type == 'service') {
        $new_args[] = $container->get($value);
      }
      elseif ($type == 'parameter') {
        $new_args[] = $container->getParameter($value);
      }
    }

    foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
      if (!empty($method->getAttributes(Required::class))) {
        $method->invoke($instance, ...self::getAutowireArguments($container, $method->getParameters(), $method->getName()));
      }
    }

    return $instance;
  }

  /**
   * Gets the services to use for the constructor of the specified class.
   *
   * The services are added to the static array.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param string $class
   *   The class to construct.
   * @param int $num_args
   *   The number of predefined arguments.
   */
  protected static function getServices(ContainerInterface $container, string $class, int $num_args) {
    if (method_exists($class, '__construct')) {
      $constructor = new \ReflectionMethod($class, '__construct');
      $params = $constructor->getParameters();

      if ($num_args && !$constructor->isVariadic()) {
        // Add the predefined arguments to the final base class.
        $params = array_slice($params, $num_args);
        static::$autowireData[] = 'args';
      }

      foreach ($params as $parameter) {
        if ($parameter->isVariadic()) {
          // Continue to parent class.
          $declaring_class = $constructor->getDeclaringClass()->getName();
          static::getServices($container, get_parent_class($declaring_class), $num_args);
          break;
        }

        // Check if the value is a container parameter (wrapped in %).
        if (preg_match('/^%([^%]+)%$/', $argument, $matches)) {
          $argument = $matches[1];
          if ($container->hasParameter($argument)) {
            static::$autowireData[] = "parameter:$argument";
            continue;
          }
        }
        elseif ($container->has($argument)) {
          static::$autowireData[] = "service:$service";
          continue;
        }

        if ($parameter->getType()?->allowsNull() || $parameter->getType() === NULL) {
          $args[] = NULL;
          continue;
        }

        throw new AutowiringFailedException($argument, sprintf('Cannot autowire %s "%s": argument "$%s" of method "%s::%s()". Check that either the argument type is correct or the Autowire attribute is passed a valid identifier. Otherwise configure its value explicitly if possible.', $type, $argument, $parameter->getName(), static::class, $method_name));
      }
    }
  }

}
