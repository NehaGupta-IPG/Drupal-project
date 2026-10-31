<?php

namespace Drupal\Core\ParamConverter;

use Drupal\Core\Routing\RouteObjectInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Contracts\Service\ServiceCollectionInterface;

/**
 * Manages converter services for converting request parameters to full objects.
 *
 * A typical use case for this would be upcasting (converting) a node id to a
 * node entity.
 */
class ParamConverterManager implements ParamConverterManagerInterface {

  /**
   * Constructs a new ParamConverterManager.
   *
   * @param \Symfony\Contracts\Service\ServiceCollectionInterface $converters
   *   The param converter services, keyed by service ID.
   */
  public function __construct(
    #[AutowireLocator('paramconverter')]
    protected ServiceCollectionInterface $converters,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getConverter($converter) {
    if ($this->converters->has($converter)) {
      return $this->converters->get($converter);
    }

    // Routes built while a param converter was lazy: true may still store the
    // proxy original service ID (drupal.proxy_original_service.*) in the
    // {router} table. After lazy was removed (e.g. in 11.4), that ID no longer
    // exists in the locator. Fall back to the plain service ID so upgrades and
    // mid-update route matches do not fail before the router is rebuilt.
    // @see https://www.drupal.org/project/drupal/issues/3612050
    // @see https://www.drupal.org/project/drupal/issues/3436295
    $proxy_prefix = 'drupal.proxy_original_service.';
    if (str_starts_with($converter, $proxy_prefix)) {
      $plain_id = substr($converter, strlen($proxy_prefix));
      if ($this->converters->has($plain_id)) {
        return $this->converters->get($plain_id);
      }
    }

    throw new \InvalidArgumentException(sprintf('No converter has been registered for %s', $converter));
  }

  /**
   * {@inheritdoc}
   */
  public function setRouteParameterConverters(RouteCollection $routes) {
    foreach ($routes->all() as $route) {
      if (!$parameters = $route->getOption('parameters')) {
        // Continue with the next route if no parameters have been defined.
        continue;
      }

      // Loop over all defined parameters and look up the right converter.
      foreach ($parameters as $name => &$definition) {
        // Skip parameters that already have a manually set converter.
        if (!isset($definition['converter'])) {
          foreach ($this->converters->getIterator() as $converter => $service) {
            if ($service->applies($definition, $name, $route)) {
              $definition['converter'] = $converter;
              break;
            }
          }
        }

        if (isset($definition['converter']) && !$route->hasRequirement($name)) {
          $service = $this->getConverter($definition['converter']);
          // If the converter can provide a regex requirement and the route
          // doesn't already have one for this parameter, set it.
          if ($service instanceof ParamConverterRouteRequirementInterface
            && $requirement = $service->getRouteRequirement($definition, $name)) {
            $route->setRequirement($name, $requirement);
          }
        }
      }

      // Override the parameters array.
      $route->setOption('parameters', $parameters);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function convert(array $defaults) {
    /** @var \Symfony\Component\Routing\Route $route */
    $route = $defaults[RouteObjectInterface::ROUTE_OBJECT];

    // Skip this enhancer if there are no parameter definitions.
    if (!$parameters = $route->getOption('parameters')) {
      return $defaults;
    }

    // Invoke the registered converter for each parameter.
    foreach ($parameters as $name => $definition) {
      if (!isset($defaults[$name])) {
        // Do not try to convert anything that is already set to NULL.
        continue;
      }

      if (!isset($definition['converter'])) {
        // Continue if no converter has been specified.
        continue;
      }

      // If a converter returns NULL it means that the parameter could not be
      // converted.
      $value = $defaults[$name];
      $defaults[$name] = $this->getConverter($definition['converter'])->convert($value, $definition, $name, $defaults);
      if (!isset($defaults[$name])) {
        $message = 'The "%s" parameter was not converted for the path "%s" (route name: "%s")';
        $route_name = $defaults[RouteObjectInterface::ROUTE_NAME];
        throw new ParamNotConvertedException(sprintf($message, $name, $route->getPath(), $route_name), 0, NULL, $route_name, [$name => $value]);
      }
    }

    return $defaults;
  }

}
