<?php

declare(strict_types=1);

namespace Drupal\Core\Hook;

use Drupal\Component\FileCache\FileCacheFactory;
use Drupal\Component\Utility\OpCodeCache;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Attribute\HookAttributeInterface;
use Drupal\Core\Hook\Attribute\HookDependsOnModule;
use Drupal\Core\Hook\Attribute\RemoveHook;
use Drupal\Core\Hook\Attribute\ReorderHook;
use Drupal\Core\Hook\OrderOperation\OrderOperation;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Collects and registers hook implementations.
 *
 * A hook implementation is a class in a Drupal\modulename\Hook namespace
 * where either the class itself or the methods have a #[Hook] attribute.
 * These classes are automatically registered as autowired services.
 *
 * Services for procedural implementation of hooks are also registered.
 *
 * Finally, a temporary .hook_data container parameter is added. This
 * contains:
 *  - hook_list a mapping from [hook,class,method] to the module name.
 *  - preprocess_for_suggestions preprocess hooks with double underscores.
 *  - packed_order_operations ordering rules for runtime evaluation
 *
 * The parameter hook_data is processed in HookCollectorKeyValueWritePass and
 * removed automatically.
 *
 * @internal
 */
class HookCollectorPass extends HookCollectorBase implements CompilerPassInterface {

  /**
   * OOP implementation module names keyed by hook name and "$class::$method".
   *
   * @var array<string, array<string, string>>
   */
  protected array $oopImplementations = [];

  /**
   * Order operations grouped by hook name and weight.
   *
   * Operations with higher weight are applied last, which means they can
   * override the changes from previous operations.
   *
   * @var array<string, array<int, list<\Drupal\Core\Hook\OrderOperation\OrderOperation>>>
   *
   * @todo Review how to combine operations from different hooks.
   */
  protected array $orderOperations = [];

  /**
   * Lists of implementation identifiers to remove, keyed by hook name.
   *
   * An identifier can be a function name or a "$class::$method" string.
   *
   * @var array<string, list<string>>
   */
  protected array $removeHookIdentifiers = [];

  /**
   * Preprocess suggestions discovered in modules.
   *
   * These are stored to prevent adding preprocess suggestions to the invoke map
   * that are not discovered in modules.
   *
   * @var array<string, true>
   */
  protected array $preprocessForSuggestions;

  /**
   * Constructor.
   *
   * @param list<string> $modules
   *   Names of installed modules.
   *   When used as a compiler pass, this parameter should be omitted.
   */
  public function __construct(
    protected readonly array $modules = [],
  ) {}

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void {
    $collector = static::collectAllHookImplementations($container);

    $collector->writeToContainer($container);
  }

  /**
   * Writes collected definitions to the container builder.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *   Container builder.
   */
  protected function writeToContainer(ContainerBuilder $container): void {
    $implementationsByHook = $this->calculateImplementations();
    static::registerHookServices($container, $implementationsByHook);

    $packed_order_operations = [];
    $order_operations = $this->getOrderOperations();
    foreach (preg_grep('@_alter$@', array_keys($order_operations)) as $alter_hook) {
      $packed_order_operations[$alter_hook] = array_map(
        fn (OrderOperation $operation) => $operation->pack(),
        $order_operations[$alter_hook],
      );
    }

    // Write aggregated data about hooks into a temporary parameter.
    // We use a dot prefixed parameter so it will automatically get cleaned up.
    // This will be stored in a keyvalue store in
    // \Drupal\Core\Hook\HookCollectorKeyValueWritePass.
    $container->setParameter('.hook_data', [
      'hook_list' => $implementationsByHook,
      'preprocess_for_suggestions' => $this->preprocessForSuggestions ?? [],
      'packed_order_operations' => $packed_order_operations,
    ]);
  }

  /**
   * Gets implementation lists with removals already applied.
   *
   * @return array<string, list<string>>
   *   Implementations, as module names keyed by hook name and
   *   "$class::$method".
   */
  protected function getFilteredImplementations(): array {
    $implementationsByHook = [];
    foreach ($this->oopImplementations as $hook => $oopImplementations) {
      if (!isset($implementationsByHook[$hook])) {
        $implementationsByHook[$hook] = $oopImplementations;
      }
      else {
        $implementationsByHook[$hook] += $oopImplementations;
      }
    }
    foreach ($this->removeHookIdentifiers as $hook => $identifiers_to_remove) {
      foreach ($identifiers_to_remove as $identifier_to_remove) {
        unset($implementationsByHook[$hook][$identifier_to_remove]);
      }
      if (empty($implementationsByHook[$hook])) {
        unset($implementationsByHook[$hook]);
      }
    }
    return $implementationsByHook;
  }

  /**
   * Calculates the ordered implementations.
   *
   * @return array<string, array<string, string>>
   *   Implementations, as module names keyed by hook name and "$class::$method"
   *   identifier.
   */
  protected function calculateImplementations(): array {
    $implementationsByHookOrig = $this->getFilteredImplementations();

    // List of hooks and modules formatted for hook_module_implements_alter().
    $moduleImplementsMap = [];
    foreach ($implementationsByHookOrig as $hook => $hookImplementations) {
      foreach (array_intersect($this->modules, $hookImplementations) as $module) {
        $moduleImplementsMap[$hook][$module] = '';
      }
    }

    $implementationsByHook = [];
    foreach ($moduleImplementsMap as $hook => $moduleImplements) {
      foreach ($moduleImplements as $module => $v) {
        if (is_string($hook) && str_starts_with($hook, 'preprocess_') && str_contains($hook, '__')) {
          $this->preprocessForSuggestions[$module . '_' . $hook] = 'module';
        }
        foreach (array_keys($implementationsByHookOrig[$hook], $module, TRUE) as $identifier) {
          $implementationsByHook[$hook][$identifier] = $module;
        }
      }
    }

    foreach ($this->getOrderOperations() as $hook => $order_operations) {
      self::applyOrderOperations($implementationsByHook[$hook], $order_operations);
    }

    return $implementationsByHook;
  }

  /**
   * Gets order operations by hook.
   *
   * @return array<string, list<\Drupal\Core\Hook\OrderOperation\OrderOperation>>
   *   Order operations by hook name.
   */
  protected function getOrderOperations(): array {
    $implementationsByHook = $this->getFilteredImplementations();
    $operations_by_hook = [];
    foreach ($this->orderOperations as $hook => $order_operations_by_weight) {
      ksort($order_operations_by_weight);
      $order_operations = array_merge(...$order_operations_by_weight);
      foreach ($order_operations as $key => $operation) {
        if (!isset($implementationsByHook[$hook][$operation->identify()]) || !in_array($implementationsByHook[$hook][$operation->identify()], $this->modules)) {
          unset($order_operations[$key]);
        }
      }
      $operations_by_hook[$hook] = array_values($order_operations);
    }
    return array_filter($operations_by_hook);
  }

  /**
   * Applies order operations to a hook implementation list.
   *
   * @param array<string, string> $implementation_list
   *   Implementation list for one hook, as module names keyed by
   *   "$class::$method" identifiers.
   * @param list<\Drupal\Core\Hook\OrderOperation\OrderOperation> $order_operations
   *   A list of order operations for one hook.
   */
  protected static function applyOrderOperations(array &$implementation_list, array $order_operations): void {
    $module_finder = $implementation_list;
    $identifiers = array_keys($module_finder);
    foreach ($order_operations as $order_operation) {
      $order_operation->apply($identifiers, $module_finder);
      assert($identifiers === array_unique($identifiers));
      assert(array_is_list($identifiers));
      assert(!array_diff($identifiers, array_keys($module_finder)));
      assert(!array_diff(array_keys($module_finder), $identifiers));
    }
    // Rebuild the identifier -> module array with the new order.
    $identifiers = array_combine($identifiers, $identifiers);
    $identifiers = array_intersect_key($identifiers, $module_finder);
    $implementation_list = array_replace($identifiers, $module_finder);
  }

  /**
   * Collects all hook implementations.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *   The ContainerBuilder to get module parameters from.
   *
   * @return static
   *   A HookCollectorPass instance holding all hook implementations and
   *   include file information.
   */
  protected static function collectAllHookImplementations(ContainerBuilder $container): static {
    $module_list = $container->getParameter('container.modules');

    $modules = array_keys($module_list);
    $collector = new static($modules);
    foreach ($module_list as $module => $info) {
      $collector->collectModuleHookImplementations(dirname($info['pathname']), $module);
    }
    return $collector;
  }

  /**
   * Collects procedural and Attribute hook implementations.
   *
   * @param string $dir
   *   The directory in which the module resides.
   * @param string $module
   *   The name of the module.
   */
  protected function collectModuleHookImplementations($dir, $module): void {
    $hook_file_cache = FileCacheFactory::get('hook_implementations');
    foreach ($this->getHookFileIterator($dir) as $fileinfo) {
      assert($fileinfo instanceof \SplFileInfo);
      $extension = $fileinfo->getExtension();
      $filename = $fileinfo->getPathname();

      if ($extension === 'php') {
        $cached = $hook_file_cache->get($filename);
        if ($cached) {
          $class = $cached['class'];
          $attributes = $cached['attributes'];
          $class_attributes = $cached['class_attributes'] ?? [];
        }
        else {
          // Immediately after a deployment, opcache may not yet have refreshed
          // depending on the value of opcache.revalidation_freq which defaults
          // to 2 seconds. Ensure that reflection operates on the new code by
          // forcibly invalidating the opcode cache.
          // @see https://www.php.net/manual/en/opcache.configuration.php#ini.opcache.revalidate-freq
          OpCodeCache::invalidate($filename);
          $namespace = preg_replace('#^src/#', "Drupal/$module/", substr($fileinfo->getPath(), strlen($dir) + 1));
          $class = $namespace . '/' . $fileinfo->getBasename('.php');
          $class = str_replace('/', '\\', $class);
          $class_attributes = [];
          $attributes = [];
          if (class_exists($class)) {
            $reflectionClass = new \ReflectionClass($class);
            $class_attributes = $reflectionClass->getAttributes(HookAttributeInterface::class, \ReflectionAttribute::IS_INSTANCEOF);
            $class_attributes = array_map(static fn (\ReflectionAttribute $ra) => $ra->newInstance(), $class_attributes);
            $attributes = self::getAttributeInstances($reflectionClass);
            $hook_file_cache->set($filename, [
              'class' => $class,
              'attributes' => $attributes,
              'class_attributes' => $class_attributes,
            ]);
          }
        }

        foreach ($class_attributes as $class_attribute) {
          // Skip the whole class if it depends on something that isn't
          // available. It will not be registered in the container then.
          if ($class_attribute instanceof HookDependsOnModule) {
            if (!in_array($class_attribute->module, $this->modules)) {
              continue 2;
            }
          }
        }

        foreach ($attributes as $method => $methodAttributes) {
          foreach ($methodAttributes as $attribute) {
            // Skip the method if it depends on something that isn't available.
            // If all methods are skipped, then the class will not be registered
            // in the container.
            if ($attribute instanceof HookDependsOnModule) {
              if (!in_array($attribute->module, $this->modules)) {
                continue 2;
              }
            }
          }
          foreach ($methodAttributes as $attribute) {
            if ($attribute instanceof Hook) {
              $this->oopImplementations[$attribute->hook][$class . '::' . ($attribute->method ?: $method)] = $attribute->module ?? $module;
              if ($attribute->order !== NULL) {
                // Use a lower weight for order operations that are declared
                // together with the hook listener they apply to.
                $this->orderOperations[$attribute->hook][0][] = $attribute->order->getOperation("$class::$method");
              }
            }
            elseif ($attribute instanceof ReorderHook) {
              $identifier = $attribute->class . '::' . $attribute->method;
              // Use a higher weight for order operations that target other hook
              // listeners.
              $this->orderOperations[$attribute->hook][1][] = $attribute->order->getOperation($identifier);
            }
            elseif ($attribute instanceof RemoveHook) {
              $identifier = $attribute->class . '::' . $attribute->method;
              $this->removeHookIdentifiers[$attribute->hook][] = $identifier;
            }
          }
        }
      }
    }
  }

  /**
   * Returns whether the existing attributes match any of the expected ones.
   *
   * @param array $existingAttributes
   *   List of attribute classes.
   * @param array $attributesLookingFor
   *   List of expected attribute classes.
   *
   * @return bool
   *   Whether an expected attribute class exists.
   */
  public static function hasAnyAttribute(array $existingAttributes, array $attributesLookingFor): bool {
    foreach ($existingAttributes as $existingAttribute) {
      foreach ($attributesLookingFor as $attributeLookingFor) {
        if (is_a($existingAttribute, $attributeLookingFor, TRUE)) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

}
