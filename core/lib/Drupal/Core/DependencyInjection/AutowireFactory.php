<?php

declare(strict_types=1);

namespace Drupal\Core\DependencyInjection;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\AutowiringFailedException;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Defines a factory class for automatically wiring dependency arguments.
 */
class AutowireFactory {

  /**
   * Array of cached class data.
   *
   * The key is the class name and the value is as returned by loadClass().
   */
  protected static array $cache = [];

  /**
   * Instantiates a new instance of the specified class using autowiring.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container this instance should use.
   * @param string $className
   *   The name of the class.
   * @param mixed ...$args
   *   Any predefined arguments to pass to the constructor.
   *
   * @return static
   */
  public static function createInstanceAutowired(ContainerInterface $container, string $className, mixed ...$args): object {
    $data = static::loadClass($container, $className, $args);

    $args = static::getArguments($container, $data['__construct'], $args);
    $instance = new $className(...$args);

    foreach ($data as $methodName => $args) {
      if ($methodName != '__construct') {
        $args = static::getArguments($container, $data[$methodName]);
        call_user_func([$instance, $methodName], ...$args);
      }
    }

    return $instance;
  }

  /**
   * Clears the cache of autowiring data for classes.
   */
  public static function clearCache(): void {
    static::$cache = [];
  }

  /**
   * Resolves arguments for a method using autowiring.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param array $data
   *   Data for the method as returned by loadMethod().
   * @param array $args
   *   Any predefined arguments to pass.
   *
   * @return array
   *   The resolved arguments.
   */
  protected static function getArguments(ContainerInterface $container, array $data, array $args = []): array {
    $new_args = [];

    foreach ($data as $entry) {
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
   * Gets data for autowiring a class.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param string $className
   *   The name of the class.
   * @param array $args
   *   Any predefined arguments to pass to the constructor.
   *
   * @return array
   *   Array with an entry for each method, as defined in loadMethod().
   *
   * @throws \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
   *   When a service cannot be resolved.
   */
  protected static function loadClass(ContainerInterface $container, string $className, array $args): array {
    if ($cached = static::$cache[$className] ?? NULL) {
      return $cached;
    }

    $reflection = new \ReflectionClass($className);

    foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
      if (!empty($method->getAttributes(Required::class))) {
        $method_name = $method->getName();
        $data[$method_name] = static::loadMethod($container, $method->getParameters(), $method_name);
      }
    }

    $data['__construct'] = [];

    if ($reflection->hasMethod('__construct')) {
      $method = $reflection->getMethod('__construct');
      $parameters = $method->getParameters();

      if ($method->isVariadic()) {
        array_pop($parameters);
      }
      else {
        $parameters = array_slice($parameters, count($args));
        $data['__construct'][] = 'args';
        $stop = TRUE;
      }

      $data['__construct'] = array_merge($data['__construct'], static::loadMethod($container, $parameters, '__construct'));
    }

    if (empty($stop) && $parent_name = get_parent_class($className)) {
      $parent_data = static::loadClass($container, $parent_name, $args);
      $data['__construct'] = array_merge($data['__construct'], $parent_data['__construct']);
    }

    static::$cache[$className] = $data;
    return $data;
  }

  /**
   * Gets data for autowiring a method.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param \ReflectionParameter[] $parameters
   *   The parameters to resolve.
   * @param string $methodName
   *   The name of the method being called.
   *
   * @return array
   *   Array of method arguments, either parameters or types.
   *
   * @throws \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
   *   When a service cannot be resolved.
   */
  protected static function loadMethod(ContainerInterface $container, array $parameters, string $methodName): array {
    $data = [];

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
          $data[] = "$type:$argument";
          continue;
        }
      }
      elseif ($container->has($argument)) {
        $data[] = "$type:$argument";
        continue;
      }

      if ($parameter->getType()?->allowsNull() || $parameter->getType() === NULL) {
        $data[] = NULL;
        continue;
      }

      throw new AutowiringFailedException($argument, sprintf('Cannot autowire %s "%s": argument "$%s" of method "%s::%s()". Check that either the argument type is correct or the Autowire attribute is passed a valid identifier. Otherwise configure its value explicitly if possible.', $type, $argument, $parameter->getName(), static::class, $methodName));
    }

    return $data;
  }

}
