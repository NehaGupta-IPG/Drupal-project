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
    $data = static::autowireLoadClass($container, static::class, $args);

    $args = static::autowireGetArguments($container, $data['__construct'], $args);
    $instance = new static(...$args);

    foreach ($data as $methodName => $args) {
      if ($methodName != '__construct') {
        $args = static::autowireGetArguments($container, $data[$methodName]);
        call_user_func([$instance, $methodName], ...$args);
      }
    }

    return $instance;
  }

  /**
   * Resolves arguments for a method using autowiring.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param array $data
   *   Data for the method as returned by autowireLoadMethod().
   * @param array $args
   *   Array of predefined arguments to pass.
   *
   * @return array
   *   The resolved arguments.
   */
  private static function autowireGetArguments(ContainerInterface $container, array $data, array $args = []): array {
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
   *   Array with an entry for each method, as defined in autowireLoadMethod().
   *
   * @throws \Symfony\Component\DependencyInjection\Exception\AutowiringFailedException
   *   When a service cannot be resolved.
   */
  private static function autowireLoadClass(ContainerInterface $container, string $className, array $args): array {
    $cache = $container->get('cache.memory');
    $cid = 'autowire__' . static::class;

    if ($cached = $cache->get($cid)) {
      $data = $cached->data;
    }
    else {
      $reflection = new \ReflectionClass($className);

      foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
        if (!empty($method->getAttributes(Required::class))) {
          $mathod_name = $method->getName();
          $data[$methodName] = static::autowireLoadMethod($container, $method->getParameters(), $methodName);
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

        $data['__construct'] = array_merge($data['__construct'], static::autowireLoadMethod($container, $parameters, '__construct'));
      }

      if (empty($stop) && $parent_name = get_parent_class($className)) {
        $parent_data = static::autowireLoadClass($container, $parent_name, $args);
        $data['__construct'] = array_merge($data['__construct'], $parent_data['__construct']);
      }

      // Choose a cache tag that will be cleared if new modules are loaded.
      $cache->set($cid, $data, tags: ['library_info']);
    }

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
  private static function autowireLoadMethod(ContainerInterface $container, array $parameters, string $methodName): array {
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
