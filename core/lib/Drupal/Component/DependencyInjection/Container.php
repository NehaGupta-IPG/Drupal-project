<?php

namespace Drupal\Component\DependencyInjection;

use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Provides a container optimized for Drupal's needs.
 *
 * This container implementation is compatible with the default Symfony
 * dependency injection container and similar to the Symfony ContainerBuilder
 * class, but optimized for speed.
 *
 * It is based on a PHP array container definition dumped as a
 * performance-optimized machine-readable format. To minimize object
 * allocation during bootstrap, service arguments are encoded as prefixed
 * strings:
 *
 *   '@service_id'    -> service reference
 *   '&service_id'    -> service closure (lazy-resolved)
 *   '%parameter'     -> container parameter reference
 *   'literal string' -> literal string, passed through unchanged
 *
 * Plain arrays hold collections of arguments; collections are no longer
 * wrapped in stdClass. Rare cases (non-default invalid behaviors, private
 * services, iterators, and strings containing embedded parameter references)
 * still use stdClass encoding and are handled by the resolver's cold path.
 *
 * The best way to initialize this container is to use a Container Builder,
 * compile it and then retrieve the definition via
 * \Drupal\Component\DependencyInjection\Dumper\OptimizedPhpArrayDumper::getArray().
 *
 * The retrieved array can be cached safely and then passed to this container
 * via the constructor.
 *
 * As the container is unfrozen by default, a second parameter can be passed to
 * the container to "freeze" the parameter bag.
 *
 * This container is different in behavior from the default Symfony container in
 * the following ways:
 *
 * - It only allows lowercase service and parameter names, though it does only
 *   enforce it via assertions for performance reasons.
 * - The following functions, that are not part of the interface, are explicitly
 *   not supported: getParameterBag(), isFrozen(), compile(),
 *   getAServiceWithAnIdByCamelCase().
 * - The function getServiceIds() was added as it has a use-case in core and
 *   contrib.
 *
 * @ingroup container
 */
class Container implements ContainerInterface, ResetInterface {

  /**
   * The parameters of the container.
   *
   * @var array
   */
  protected $parameters = [];

  /**
   * The aliases of the container.
   *
   * @var array
   */
  protected $aliases = [];

  /**
   * The service definitions of the container.
   *
   * @var array
   */
  protected $serviceDefinitions = [];

  /**
   * The instantiated services.
   *
   * @var array
   */
  protected $services = [];

  /**
   * The instantiated private services.
   *
   * @var array
   */
  protected $privateServices = [];

  /**
   * The currently loading services.
   *
   * @var array
   */
  protected $loading = [];

  /**
   * Whether the container parameters can still be changed.
   *
   * For testing purposes the container needs to be changed.
   *
   * @var bool
   */
  protected $frozen = TRUE;

  /**
   * The list of deprecated services (service id => deprecation message).
   *
   * Cached from parameters for fast lookup on the get() hot path.
   *
   * @var array
   */
  protected $deprecatedServices = [];

  /**
   * Constructs a new Container instance.
   *
   * @param array $container_definition
   *   An array containing the following keys:
   *   - aliases: The aliases of the container.
   *   - parameters: The parameters of the container.
   *   - services: The service definitions of the container.
   *   - frozen: Whether the container definition came from a frozen
   *     container builder or not.
   *   - machine_format: Whether this container definition uses the optimized
   *     machine-readable container format.
   */
  public function __construct(array $container_definition = []) {
    if (!empty($container_definition) && (!isset($container_definition['machine_format']) || $container_definition['machine_format'] !== TRUE)) {
      throw new InvalidArgumentException('The non-optimized format is not supported by this class. Use an optimized machine-readable format instead, e.g. as produced by \Drupal\Component\DependencyInjection\Dumper\OptimizedPhpArrayDumper.');
    }

    $this->aliases = $container_definition['aliases'] ?? [];
    $this->parameters = $container_definition['parameters'] ?? [];
    $this->serviceDefinitions = $container_definition['services'] ?? [];
    $this->frozen = $container_definition['frozen'] ?? FALSE;
    $this->deprecatedServices = $this->parameters['_deprecated_service_list'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function get(string $id, int $invalid_behavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE): ?object {
    if (isset($this->aliases[$id])) {
      $alias = $id;
      $id = $this->aliases[$id];
    }

    // Re-use shared service instance if it exists.
    if (isset($this->services[$id]) || ($invalid_behavior === ContainerInterface::NULL_ON_INVALID_REFERENCE && array_key_exists($id, $this->services))) {
      return $this->services[$id];
    }

    if ($id === 'service_container') {
      return $this;
    }

    if (isset($this->loading[$id])) {
      throw new ServiceCircularReferenceException($id, array_keys($this->loading));
    }

    $definition = $this->serviceDefinitions[$id] ?? NULL;

    if (!$definition && $invalid_behavior === ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE) {
      if (!$id) {
        throw new ServiceNotFoundException('');
      }

      throw new ServiceNotFoundException($id, NULL, NULL, $this->getServiceAlternatives($id));
    }

    // In case something else than ContainerInterface::NULL_ON_INVALID_REFERENCE
    // is used, the actual wanted behavior is to re-try getting the service at a
    // later point.
    if (!$definition) {
      return NULL;
    }

    // Definition is a keyed array, so [0] is only defined when it is a
    // serialized string.
    if (isset($definition[0])) {
      $definition = unserialize($definition);
    }

    // Now create the service.
    $this->loading[$id] = TRUE;

    try {
      $service = $this->createService($definition, $id);
    }
    catch (\Exception $e) {
      unset($this->loading[$id]);
      unset($this->services[$id]);

      if (ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE !== $invalid_behavior) {
        return NULL;
      }

      throw $e;
    }

    unset($this->loading[$id]);

    if (isset($this->deprecatedServices[$id])) {
      @trigger_error($this->deprecatedServices[$id], E_USER_DEPRECATED);
    }
    if (isset($alias, $this->deprecatedServices[$alias])) {
      @trigger_error($this->deprecatedServices[$alias], E_USER_DEPRECATED);
    }

    return $service;
  }

  /**
   * Resets shared services from the container.
   *
   * The container is not intended to be used again after being reset in a
   * normal workflow. This method is meant as a way to release references for
   * ref-counting. A subsequent call to ContainerInterface::get() will recreate
   * a new instance of the shared service.
   */
  public function reset(): void {
    $this->services = [];
  }

  /**
   * Creates a service from a service definition.
   *
   * @param array $definition
   *   The service definition to create a service from.
   * @param string $id
   *   The service identifier, necessary so it can be shared if its public.
   *
   * @return object
   *   The service described by the service definition.
   *
   * @throws \Symfony\Component\DependencyInjection\Exception\RuntimeException
   *   Thrown when the service is a synthetic service.
   * @throws \ReflectionException
   *   Thrown when the service class takes more than 10 parameters to construct,
   *   and cannot be instantiated.
   */
  protected function createService(array $definition, $id) {
    if (isset($definition['synthetic']) && $definition['synthetic'] === TRUE) {
      throw new RuntimeException(sprintf('You have requested a synthetic service ("%s"). The service container does not know how to construct this service. The service will need to be set before it is first used.', $id));
    }

    $arguments = [];
    if (isset($definition['arguments'])) {
      $arguments = $this->resolveServicesAndParameters($definition['arguments']);
    }

    if (isset($definition['file'])) {
      require_once $definition['file'];
    }

    if (isset($definition['factory'])) {
      $factory = $definition['factory'];
      if (is_array($factory)) {
        $factory = $this->resolveServicesAndParameters([$factory[0], $factory[1]]);
      }
      elseif (!is_string($factory)) {
        throw new RuntimeException(sprintf('Cannot create service "%s" because of invalid factory', $id));
      }

      $service = $factory(...$arguments);
    }
    else {
      $class = $definition['class'];
      $service = new $class(...$arguments);
    }

    if (!isset($definition['shared'])) {
      $this->services[$id] = $service;
    }

    if (isset($definition['calls'])) {
      foreach ($definition['calls'] as [$method, $call_args]) {
        $service->{$method}(...$this->resolveServicesAndParameters($call_args ?? []));
      }
    }

    if (isset($definition['properties'])) {
      foreach ($this->resolveServicesAndParameters($definition['properties']) as $key => $value) {
        $service->{$key} = $value;
      }
    }

    if (isset($definition['configurator'])) {
      $callable = $definition['configurator'];
      if (is_array($callable)) {
        $callable = $this->resolveServicesAndParameters($callable);
      }
      $callable($service);
    }

    return $service;
  }

  /**
   * {@inheritdoc}
   */
  public function set(string $id, ?object $service): void {
    $this->services[$id] = $service;
  }

  /**
   * {@inheritdoc}
   */
  public function has(string $id): bool {
    return isset($this->aliases[$id]) || isset($this->services[$id]) || isset($this->serviceDefinitions[$id]) || $id === 'service_container';
  }

  /**
   * {@inheritdoc}
   */
  public function getParameter(string $name): array|bool|string|int|float|\UnitEnum|null {
    if (!\array_key_exists($name, $this->parameters)) {
      throw new ParameterNotFoundException($name, NULL, NULL, NULL, $this->getParameterAlternatives($name));
    }

    return $this->parameters[$name];
  }

  /**
   * {@inheritdoc}
   */
  public function hasParameter(string $name): bool {
    return \array_key_exists($name, $this->parameters);
  }

  /**
   * {@inheritdoc}
   */
  public function setParameter(string $name, array|bool|string|int|float|\UnitEnum|null $value): void {
    if ($this->frozen) {
      throw new LogicException('Impossible to call set() on a frozen ParameterBag.');
    }

    $this->parameters[$name] = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function initialized(string $id): bool {
    if (isset($this->aliases[$id])) {
      $id = $this->aliases[$id];
    }

    return \array_key_exists($id, $this->services);
  }

  /**
   * Resolves arguments that represent services or parameters to real values.
   *
   * See the class docblock for the prefix-string encoding this recognizes.
   *
   * @param array $arguments
   *   The arguments to resolve.
   *
   * @return array
   *   The resolved arguments.
   *
   * @throws \Symfony\Component\DependencyInjection\Exception\RuntimeException
   *   If a parameter/service could not be resolved.
   * @throws \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
   *   If an unknown type is met while resolving parameters and services.
   */
  protected function resolveServicesAndParameters(array $arguments): array {
    foreach ($arguments as $key => $argument) {
      if (is_string($argument) && isset($argument[0])) {
        $first = $argument[0];

        // Service reference: '@id'.
        if ($first === '@') {
          $svc_id = substr($argument, 1);
          if (isset($this->aliases[$svc_id])) {
            $svc_id = $this->aliases[$svc_id];
          }
          $arguments[$key] = $this->services[$svc_id] ?? $this->get($svc_id);
          continue;
        }

        // Service closure: '&id'.
        if ($first === '&') {
          $svc_id = substr($argument, 1);
          $arguments[$key] = fn() => $this->get($svc_id);
          continue;
        }

        // Parameter reference: '%name'.
        if ($first === '%') {
          $name = substr($argument, 1);
          if (!array_key_exists($name, $this->parameters)) {
            // getParameter() throws when the parameter is missing.
            $arguments[$key] = $this->getParameter($name);
            continue;
          }
          $value = $this->parameters[$name];
          // A parameter's value may itself be a prefix-encoded reference or a
          // stdClass wrapper. Recurse to resolve those cases.
          if ((is_string($value) && isset($value[0]) && ($value[0] === '@' || $value[0] === '&' || $value[0] === '%'))
            || $value instanceof \stdClass) {
            $arguments[$key] = $this->resolveServicesAndParameters([$value])[0];
          }
          else {
            $arguments[$key] = $value;
          }
          continue;
        }

        // Literal string, pass through.
        continue;
      }

      if (is_array($argument)) {
        $arguments[$key] = $this->resolveServicesAndParameters($argument);
        continue;
      }

      if ($argument instanceof \stdClass) {
        $arguments[$key] = $this->resolveStdClassArgument($argument);
      }
    }

    return $arguments;
  }

  /**
   * Resolves a stdClass-encoded argument (cold path).
   *
   * @param \stdClass $arg
   *   The argument to resolve.
   *
   * @return mixed
   *   The resolved value.
   *
   * @throws \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
   *   If the type is unknown.
   */
  protected function resolveStdClassArgument(\stdClass $arg): mixed {
    return match ($arg->type) {
      'raw' => $arg->value,
      'service' => $this->get($arg->id, $arg->invalidBehavior),
      'service_closure' => (function () use ($arg) {
        $id = $arg->id;
        $behavior = $arg->invalidBehavior;
        return fn() => $this->get($id, $behavior);
      })(),
      'private_service' => $this->resolvePrivateService($arg),
      'iterator' => $this->resolveIterator($arg->value),
      'parameter' => $this->parameters[$arg->name] ?? $this->getParameter($arg->name),
      'collection' => $this->resolveServicesAndParameters($arg->value),
      default => throw new InvalidArgumentException(sprintf('Undefined type "%s" while resolving parameters and services.', $arg->type)),
    };
  }

  /**
   * Resolves a private service stdClass argument.
   */
  private function resolvePrivateService(\stdClass $arg): object {
    if (isset($this->privateServices[$arg->id])) {
      return $this->privateServices[$arg->id];
    }
    $instance = $this->createService($arg->value, $arg->id);
    if ($arg->shared) {
      $this->privateServices[$arg->id] = $instance;
    }
    return $instance;
  }

  /**
   * Resolves an iterator of service references.
   */
  private function resolveIterator(array $services): RewindableGenerator {
    return new RewindableGenerator(function () use ($services) {
      foreach ($services as $key => $service) {
        yield $key => $this->resolveServicesAndParameters([$service])[0];
      }
    }, count($services));
  }

  /**
   * Provides alternatives for a given array and key.
   *
   * @param string $search_key
   *   The search key to get alternatives for.
   * @param array $keys
   *   The search space to search for alternatives in.
   *
   * @return string[]
   *   An array of strings with suitable alternatives.
   */
  protected function getAlternatives($search_key, array $keys) {
    $alternatives = [];
    foreach ($keys as $key) {
      $lev = levenshtein($search_key, $key);
      if ($lev <= strlen($search_key) / 3 || str_contains($key, $search_key)) {
        $alternatives[] = $key;
      }
    }

    return $alternatives;
  }

  /**
   * Provides alternatives in case a service was not found.
   *
   * @param string $id
   *   The service to get alternatives for.
   *
   * @return string[]
   *   An array of strings with suitable alternatives.
   */
  protected function getServiceAlternatives($id) {
    $all_service_keys = array_unique(array_merge(array_keys($this->services), array_keys($this->serviceDefinitions)));
    return $this->getAlternatives($id, $all_service_keys);
  }

  /**
   * Provides alternatives in case a parameter was not found.
   *
   * @param string $name
   *   The parameter to get alternatives for.
   *
   * @return string[]
   *   An array of strings with suitable alternatives.
   */
  protected function getParameterAlternatives($name) {
    return $this->getAlternatives($name, array_keys($this->parameters));
  }

  /**
   * {@inheritdoc}
   */
  public function getServiceIds() {
    return array_merge(['service_container'], array_keys($this->serviceDefinitions + $this->services));
  }

  /**
   * Ensure that cloning doesn't work.
   */
  private function __clone() {
  }

}
