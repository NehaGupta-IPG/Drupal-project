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
    if (empty(static::$autowireData[static::class])) {
      $reflection = new \ReflectionClass(static::class);

      foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
        if (!empty($method->getAttributes(Required::class))) {
          static::getAutowireData($container, $method->getParameters(), $method->getName());
        }
      }

      while ($reflection->hasMethod('__construct')) {
        $method = $reflection->getMethod('__construct');
        $parameters = $method->getParameters();

        if ($method->isVariadic()) {
          array_pop($parameters);
          static::getAutowireData($container, $parameters, '__construct');
        }
        else {
          $parameters = array_slice($parameters, count($args));
          static::$autowireData[static::class]['__construct'][] = 'args';
          static::getAutowireData($container, $parameters, '__construct');
          break;
        }

        $reflection = $reflection->getParentClass();
      }
    }

    $args = static::getAutowireArguments($container, '__construct', $args);
    $instance = new static(...$args);

    foreach (static::$autowireData[static::class] as $method_name => $args) {
      if ($method_name != '__construct') {
        $args = static::getAutowireArguments($container, $method_name);
        call_user_func([$instance, $method_name], ...$args);
      }
    }

    return $instance;
  }

  /**
   * Resolves arguments for a method using autowiring.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param string $method_name
   *   The name of the method being called.
   * @param array $args
   *   Array of predefined arguments to pass.
   *
   * @return array
   *   The resolved arguments.
   */
  private static function getAutowireArguments(ContainerInterface $container, string $method_name, array $args = []): array {
    $new_args = [];

    foreach (static::$autowireData[static::class][$method_name] as $entry) {
      if (is_null($entry)) {
        $new_args[] = NULL;
        continue;
      }

      if ($entry == 'args') {
        $new_args = array_merge($new_args, $args);
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

    return $new_args;
  }

  /**
   * Analyses arguments for a method using autowiring.
   *
   * The results are added to the static array.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param \ReflectionParameter[] $parameters
   *   The parameters to resolve.
   * @param string $method_name
   *   The name of the method being called.
   *
   * @throws \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
   *   When a service cannot be resolved.
   */
  private static function getAutowireData(ContainerInterface $container, array $parameters, string $method_name): void {
    foreach ($parameters as $parameter) {
      $type = 'service';
      $argument = ltrim((string) $parameter->getType(), '?');
      foreach ($parameter->getAttributes(Autowire::class) as $attribute) {
        $argument = (string) $attribute->newInstance()->value;
      }

      // Check if the value is a container parameter (wrapped in %).
      if (preg_match('/^%([^%]+)%$/', $argument, $matches)) {
        $type = 'parameter';
        $argument = $matches[1];
        if ($container->hasParameter($argument)) {
          static::$autowireData[static::class][$method_name][] = "$type:$argument";
          continue;
        }
      }
      elseif ($container->has($argument)) {
        static::$autowireData[static::class][$method_name][] = "$type:$argument";
        continue;
      }

      if ($parameter->getType()?->allowsNull() || $parameter->getType() === NULL) {
        static::$autowireData[static::class][$method_name][] = NULL;
        continue;
      }

      throw new AutowiringFailedException($argument, sprintf('Cannot autowire %s "%s": argument "$%s" of method "%s::%s()". Check that either the argument type is correct or the Autowire attribute is passed a valid identifier. Otherwise configure its value explicitly if possible.', $type, $argument, $parameter->getName(), static::class, $method_name));
    }
  }

}
