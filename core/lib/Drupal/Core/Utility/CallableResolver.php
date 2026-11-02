<?php

namespace Drupal\Core\Utility;

use Drupal\Core\DependencyInjection\ClassResolverInterface;

/**
 * Resolves PHP callables.
 *
 * The callable resolver service aims to provide a standardized approach to how
 * callables are resolved and invoked from various subsystems.
 *
 * Anything that is already a native PHP callable is returned immediately, such
 * as static methods, object calls with an instantiated object or closures.
 *
 * @code
 * '\FooClass::staticMethod';
 * ['\FooClass', 'staticMethod'];
 * [$object, 'method'];
 * @end code
 *
 * If it is not already a callable, then the callable definition must be defined
 * as a string, the first part is either a service ID or a a class name. Note
 * that services IDs can also be class or interface names. This is resolved
 * using \Drupal\Core\DependencyInjection\ClassResolverInterface.
 *
 * The second, optional part is the method name. If none is provided, the
 * returned object must implement the __invoke() method.
 *
 * For better readability, it is recommended to use a single : to differentiate
 * the callable definition as the separator from a regular static method,
 * although a double :: may be used as well.
 *
 * @code
 * 'service_name:method';
 * ClassName::class . ':method';
 * static::class . ':method';
 * 'ClassWithInvokeMethod'
 * @endcode
 *
 * Note: Form classes may define their callables as '::methodName', this is
 * resolved by \Drupal\Core\Form\FormStateInterface::prepareCallback() and
 * reaches this as a native PHP callable as [$form_object, 'methodName']. This
 * is only supported by form classes.
 */
class CallableResolver {

  /**
   * Constructs a CallableResolver object.
   *
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $classResolver
   *   The class resolver.
   */
  public function __construct(
    protected readonly ClassResolverInterface $classResolver,
  ) {
  }

  /**
   * Gets a callable from a definition.
   *
   * @param callable|array|string $definition
   *   A callable definition. See the class documentation for more information
   *   on supported formats.
   *
   * @return callable
   *   A callable.
   *
   * @throws \InvalidArgumentException
   *   Thrown when no valid callable could be resolved from the definition.
   */
  public function getCallableFromDefinition(callable|array|string $definition): callable {
    // If the definition is natively a callable, we can return it immediately.
    if (is_callable($definition)) {
      return $definition;
    }

    if (is_array($definition)) {
      throw new \InvalidArgumentException(sprintf('The callable definition provided "[%s]" is not a valid callable.', implode(",", $definition)));
    }

    if (!is_string($definition)) {
      throw new \InvalidArgumentException(sprintf('The callable definition provided was invalid. Invalid format of type: %s', gettype($definition)));
    }

    // Callable with __invoke().
    if (!str_contains($definition, ':')) {
      $instance = $this->classResolver->getInstanceFromDefinition($definition);
      if (!is_callable($instance)) {
        throw new \InvalidArgumentException(sprintf('The callable definition provided was invalid. Class "%s" does not have a method "__invoke" and is not callable.', $instance::class));
      }
      return $instance;
    }

    // Callable in the service:method notation.
    $class_or_service = NULL;
    $method = NULL;
    $count = substr_count($definition, ':');
    if ($count == 1) {
      [$class_or_service, $method] = explode(':', $definition, 2);
    }
    // Callable in the class::method notation.
    if (str_contains($definition, '::')) {
      [$class_or_service, $method] = explode('::', $definition, 2);
    }
    if (empty($class_or_service) || empty($method)) {
      throw new \InvalidArgumentException(sprintf('The callable definition provided was invalid. Could not get class and method from definition "%s".', $definition));
    }

    $instance = $this->classResolver->getInstanceFromDefinition($class_or_service);
    if (!is_callable([$instance, $method])) {
      throw new \InvalidArgumentException(sprintf('The callable definition provided was invalid. Either class "%s" does not have a method "%s", or it is not callable.', $instance::class, $method));
    }
    return [$instance, $method];
  }

}
