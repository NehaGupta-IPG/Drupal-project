<?php

declare(strict_types=1);

namespace Drupal\Core\Hook;

use Drupal\Component\FileCache\FileCacheFactory;
use Drupal\Core\Hook\OrderOperation\OrderOperation;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Collects and registers hook implementations in registered services.
 *
 * Any service registered in a module's *.services.yml or by a module's service
 * provider/modifier will have its hook implementations discovered, if its class
 * or the class's methods have a #[Hook] attribute.
 *
 * Test classes can also register themselves as services to have hook
 * implementations discovered similarly using the #[Hook] attribute. In test
 * classes, the 'module' property on the #[Hook] attribute must be set to a
 * module installed during the test run, in order for the hook implementation to
 * be invoked.
 *
 * @internal
 */
class HookCollectorRegisteredServicesPass extends HookCollectorPass {

  /**
   * Map of class names to matching service IDs.
   *
   * @var array{class-string, string}
   */
  protected static array $classToServiceIdMap = [];

  /**
   * Constructs a new HookCollectorRegisteredServicesPass object.
   *
   * @param \Drupal\Core\Hook\HookCollectorPass|null $previousCollector
   *   When compiling the container, if a HookCollectorPass is added to run
   *   before this HookCollectorRegisteredServicesPass, it should be passed as
   *   an argument here so that the previously discovered implementations stay
   *   intact when writing to container.
   */
  public function __construct(
    protected readonly ?HookCollectorPass $previousCollector = NULL,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void {
    if ($this->previousCollector) {
      foreach (get_object_vars($this->previousCollector) as $property => $value) {
        $this->$property = $value;
      }
    }
    else {
      $this->modules = array_keys($container->getParameter('container.modules'));
    }

    $containerNamespaces = $container->getParameter('container.namespaces');
    $cache = FileCacheFactory::get('hook_implementations');
    foreach ($container->getDefinitions() as $id => $definition) {
      if ($definition->isAbstract() || $definition->isSynthetic() || !($class = $definition->getClass())) {
        continue;
      }

      $parts = explode('\\', $class);
      $filename = array_pop($parts) . '.php';
      $namespace = implode('\\', $parts);
      $root = array_shift($parts);
      if ($root !== 'Drupal') {
        continue;
      }

      $provider = array_shift($parts) ?? '';
      // Ignore Component and Core services. Also ignore any services in a
      // module's "Hook" namespace, because they will be picked up by
      // ::collectModuleHookImplementations().
      if (in_array($provider, ['Component', 'Core']) ||
          (reset($parts) === 'Hook')) {
        continue;
      }

      $subPath = implode('/', $parts);
      $path = isset($containerNamespaces["Drupal\\$provider"]) ? $containerNamespaces["Drupal\\$provider"] . '/' . $subPath . '/' : '';
      // Allow an empty path to be passed if it can not be retrieved from the
      // container namespaces. This allows test classes to register themselves
      // as services implementing hooks.
      if ($this->collectHookImplementationsFromClassFile($path ? $path . $filename : '', $namespace, $provider, $cache, $class)) {
        // If hook implementations are found in the class, add to the class to
        // service ID map, so that the service can be looked up when writing
        // to container.
        // @see ::getServiceDefinitionFromClass()
        static::$classToServiceIdMap[$class] = $id;
      }
    }

    $this->writeToContainer($container);
  }

  /**
   * {@inheritdoc}
   */
  protected function writeToContainer(ContainerBuilder $container): void {
    $implementationsByHook = $this->calculateImplementations();
    static::writeImplementationsToContainer($container, $implementationsByHook);
    $container->setParameter('preprocess_for_suggestions', $this->preprocessForSuggestions ?? []);

    $packed_order_operations = [];
    $order_operations = $this->getOrderOperations();
    foreach (preg_grep('@_alter$@', array_keys($order_operations)) as $alter_hook) {
      $packed_order_operations[$alter_hook] = array_map(
        fn (OrderOperation $operation) => $operation->pack(),
        $order_operations[$alter_hook],
      );
    }
    $container->getDefinition('module_handler')->setArgument('$packedOrderOperations', $packed_order_operations);
  }

  /**
   * {@inheritdoc}
   */
  protected static function getServiceDefinitionFromClass(string $class, ContainerBuilder $container): ?Definition {
    if (isset(static::$classToServiceIdMap[$class])) {
      return $container->findDefinition(static::$classToServiceIdMap[$class]);
    }
    return $container->hasDefinition($class) ? $container->findDefinition($class) : NULL;
  }

}
