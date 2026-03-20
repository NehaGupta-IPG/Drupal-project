<?php

namespace Drupal\Core\Routing;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Access\CheckProviderInterface;
use Drupal\Core\Controller\ControllerResolverInterface;
use Drupal\Core\DestructableInterface;
use Drupal\Core\Discovery\YamlDiscovery;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Managing class for rebuilding the router table.
 */
class RouteBuilder implements RouteBuilderInterface, DestructableInterface {

  /**
   * The dumper to which we should send collected routes.
   *
   * @var \Drupal\Core\Routing\MatcherDumperInterface
   */
  protected $dumper;

  /**
   * The used lock backend instance.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lock;

  /**
   * The event dispatcher to notify of routes.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected $dispatcher;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The controller resolver.
   *
   * @var \Drupal\Core\Controller\ControllerResolverInterface
   */
  protected $controllerResolver;

  /**
   * The route collection during the rebuild.
   *
   * @var \Symfony\Component\Routing\RouteCollection
   */
  protected $routeCollection;

  /**
   * Flag that indicates if we are currently rebuilding the routes.
   *
   * @var bool
   */
  protected $building = FALSE;

  /**
   * Flag that indicates if we should rebuild at the end of the request.
   *
   * @var bool
   */
  protected $rebuildNeeded = FALSE;

  /**
   * The check provider.
   *
   * @var \Drupal\Core\Access\CheckProviderInterface
   */
  protected $checkProvider;

  /**
   * Constructs the RouteBuilder using the passed MatcherDumperInterface.
   *
   * @param \Drupal\Core\Routing\MatcherDumperInterface $dumper
   *   The matcher dumper used to store the route information.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $dispatcher
   *   The event dispatcher to notify of routes.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Controller\ControllerResolverInterface $controller_resolver
   *   The controller resolver.
   * @param \Drupal\Core\Access\CheckProviderInterface $check_provider
   *   The check provider.
   */
  public function __construct(MatcherDumperInterface $dumper, LockBackendInterface $lock, EventDispatcherInterface $dispatcher, ModuleHandlerInterface $module_handler, ControllerResolverInterface $controller_resolver, CheckProviderInterface $check_provider) {
    $this->dumper = $dumper;
    $this->lock = $lock;
    $this->dispatcher = $dispatcher;
    $this->moduleHandler = $module_handler;
    $this->controllerResolver = $controller_resolver;
    $this->checkProvider = $check_provider;
  }

  /**
   * {@inheritdoc}
   */
  public function setRebuildNeeded() {
    $this->rebuildNeeded = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function rebuild() {
    if ($this->building) {
      throw new \RuntimeException('Recursive router rebuild detected.');
    }

    if (!$this->lock->acquire('router_rebuild')) {
      // Wait for another request that is already doing this work.
      // We choose to block here since otherwise the routes might not be
      // available, resulting in a 404.
      $this->lock->wait('router_rebuild');
      return FALSE;
    }

    $this->building = TRUE;

    // @todo `route_callbacks` alter.
    // Special yml file for the `route_callbacks` list altering.
    // Name: <module>.routing.alter.yml
    // Structure:
    // . <alter_module_name1>
    // .  route_callbacks_alter:
    // .    - <route_callback_name1>: <new_route_callback_name1>
    // .    - <route_callback_name2>: <new_route_callback_name2>
    // .    - ...
    // .    - <route_callback_nameN>: <new_route_callback_nameN>
    // .  weight: <route_callbacks_alter_weight_number>
    // . <alter_module_name2>
    // Take all definitions.
    $route_definitions = $this->getRouteDefinitions();
    $route_definitions_alter = [];
    // '*.routing.alter.yml' calls from all modules.
    foreach ($this->getRoutingAlterDefinitions() as $this_module) {
      // Take full list of routes alter for every module
      // every *.routing.alter.yml file.
      foreach ($this_module as $altering_module_name => $altering_module) {
        // If exists `route_callbacks` *.routing.yml for $altering_module.
        if (isset($route_definitions[$altering_module_name]['route_callbacks'])) {
          $route_callbacks = $route_definitions[$altering_module_name]['route_callbacks'];

          // If exists 'route_callbacks_alter' list for $altering_module_name.
          if (isset($this_module[$altering_module_name]['route_callbacks_alter'])) {
            $route_callbacks_alter = $this_module[$altering_module_name]['route_callbacks_alter'];

            // Check `route_callbacks` list from $altering_module.
            foreach ($route_callbacks as $route_callback) {
              // Check `route_callbacks_alter` list from $this_module.
              foreach ($route_callbacks_alter as $route_callback_alter) {
                if (isset($this_module[$altering_module_name]['weight'])) {
                  $weight = $this_module[$altering_module_name]['weight'];

                  // Check and add/change weight for this route_callback.
                  $route_alter_key = "$altering_module_name:$route_callback";

                  if (isset($route_definitions_alter[$route_alter_key]['weight'])) {
                    if ($weight > $route_definitions_alter[$route_alter_key]['weight']) {
                      $route_definitions_alter[$route_alter_key] = $route_callback_alter;
                      $route_definitions_alter[$route_alter_key]['weight'] = $weight;
                    }
                  }
                  // No weight - initialization.
                  else {
                    $route_definitions_alter[$route_alter_key] = $route_callback_alter;
                    $route_definitions_alter[$route_alter_key]['weight'] = $weight;
                  }
                }
              }
            }
          }
        }
      }
    }

    $collection = new RouteCollection();
    foreach ($route_definitions as $route_name => $routes) {
      // The top-level 'routes_callback' is a list of methods in controller
      // syntax, see \Drupal\Core\Controller\ControllerResolver. These methods
      // should return a set of \Symfony\Component\Routing\Route objects, either
      // in an associative array keyed by the route name, which will be iterated
      // over and added to the collection for this provider, or as a new
      // \Symfony\Component\Routing\RouteCollection object, which will be added
      // to the collection.
      if (isset($routes['route_callbacks'])) {
        foreach ($routes['route_callbacks'] as $route_callback) {

          // @todo take alter route info from $route_definitions_alter.
          $route_alter_key = "$route_name:$route_callback";
          if (isset($route_definitions_alter[$route_alter_key])) {
            $route_callback_alter = $route_definitions_alter[$route_alter_key][$route_callback];
          }
          else {
            $route_callback_alter = $route_callback;
          }

          $callback = $this->controllerResolver->getControllerFromDefinition($route_callback_alter);
          if ($callback_routes = call_user_func($callback)) {
            // If a RouteCollection is returned, add the whole collection.
            if ($callback_routes instanceof RouteCollection) {
              $collection->addCollection($callback_routes);
            }
            // Otherwise, add each Route object individually.
            else {
              foreach ($callback_routes as $name => $callback_route) {
                $collection->add($name, $callback_route);
              }
            }
          }
        }
        unset($routes['route_callbacks']);
      }
      foreach ($routes as $name => $route_info) {
        if (isset($route_info['alias'])) {
          $alias = $collection->addAlias($name, $route_info['alias']);
          $deprecation = $route_info['deprecated'] ?? NULL;
          if (isset($deprecation)) {
            $alias->setDeprecated(
              $deprecation['package'],
              $deprecation['version'],
              $deprecation['message'] ?? ''
            );
          }
          continue;
        }
        $route_info += [
          'defaults' => [],
          'requirements' => [],
          'options' => [],
          'host' => NULL,
          'schemes' => [],
          'methods' => [],
          'condition' => '',
        ];
        // Ensure routes default to using Drupal's route compiler instead of
        // Symfony's.
        $route_info['options'] += [
          'compiler_class' => RouteCompiler::class,
        ];

        $route = new Route($route_info['path'], $route_info['defaults'], $route_info['requirements'], $route_info['options'], $route_info['host'], $route_info['schemes'], $route_info['methods'], $route_info['condition']);
        $collection->add($name, $route);
      }
    }

    // DYNAMIC is supposed to be used to add new routes based upon all the
    // static defined ones.
    $this->dispatcher->dispatch(new RouteBuildEvent($collection), RoutingEvents::DYNAMIC);

    // ALTER is the final step to alter all the existing routes. We cannot stop
    // people from adding new routes here, but we define two separate steps to
    // make it clear.
    $this->dispatcher->dispatch(new RouteBuildEvent($collection), RoutingEvents::ALTER);

    $this->checkProvider->setChecks($collection);

    $this->dumper->addRoutes($collection);
    $this->dumper->dump();

    $this->lock->release('router_rebuild');
    $this->dispatcher->dispatch(new Event(), RoutingEvents::FINISHED);
    $this->building = FALSE;

    $this->rebuildNeeded = FALSE;

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function rebuildIfNeeded() {
    if ($this->rebuildNeeded) {
      return $this->rebuild();
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function destruct() {
    // Rebuild routes only once at the end of the request lifecycle to not
    // trigger multiple rebuilds and also make the page more responsive for the
    // user.
    $this->rebuildIfNeeded();
  }

  /**
   * Retrieves all defined routes from .routing.yml files.
   *
   * @return array
   *   The defined routes, keyed by provider.
   */
  protected function getRouteDefinitions() {
    // Always instantiate a new YamlDiscovery object so that we always search on
    // the up-to-date list of modules.
    $discovery = new YamlDiscovery('routing', $this->moduleHandler->getModuleDirectories());

    return $discovery->findAll();
  }

  /**
   * Retrieves all defined routes alter from .routing.alter.yml files.
   *
   * @todo New *.routing.alter.yml file - need for *.routing.yml alter
   * from other modules.
   *
   * Special yml file for the `route_callbacks` list altering.
   * Name: <module>.routing.alter.yml
   * Structure:
   * <alter_module_name1>
   *   route_callbacks_alter:
   *     - <route_callback_name1>: <new_route_callback_name1>
   *     - <route_callback_name2>: <new_route_callback_name2>
   *     - ...
   *     - <route_callback_nameN>: <new_route_callback_nameN>
   *   weight: <route_callbacks_alter_weight_number>
   * <alter_module_name2>
   *   ...
   *
   * @return array
   *   The defined routes, keyed by provider.
   */
  protected function getRoutingAlterDefinitions() {
    // Always instantiate a new YamlDiscovery object so that we always search on
    // the up-to-date list of modules.
    if ($module_directories = $this->moduleHandler->getModuleDirectories()) {
      $discovery = new YamlDiscovery('routing.alter', $module_directories);

      return $discovery->findAll();
    }

    return [];
  }

}
