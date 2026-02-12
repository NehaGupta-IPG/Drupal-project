<?php

namespace Drupal\Core\Plugin\Factory;

use Drupal\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Plugin\Definition\PluginDefinitionInterface;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\Factory\DefaultFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Exception\AutowiringFailedException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Plugin factory which passes a container to a create method.
 */
class ContainerFactory extends DefaultFactory {

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    $plugin_definition = $this->discovery->getDefinition($plugin_id);
    $plugin_class = static::getPluginClass($plugin_id, $plugin_definition, $this->interface);

    // If the plugin can be autowired, autowire it, or if it provides a factory
    // method, pass the container to it.
    if (is_subclass_of($plugin_class, 'Drupal\Core\Plugin\ContainerFactoryPluginInterface')) {
      if ($plugin_definition instanceof PluginDefinitionInterface) {
        $args = $plugin_definition->constructorServices ?? NULL;
      }
      else {
        $args = $plugin_definition['constructor_services'] ?? NULL;
      }
      if (is_array($args)) {
        $container = \Drupal::getContainer();
        $args = array_map(function (Reference $arg, int $pos) use ($container, $plugin_class) {
          $service = (string) $arg;
          try {
            return $container->get($service, $arg->getInvalidBehavior());
          }
          catch (ServiceNotFoundException) {
            $constructor = new \ReflectionMethod($plugin_class, '__construct');
            $argument = $constructor->getParameters()[$pos]->getName();
            throw new AutowiringFailedException($service, sprintf('Cannot autowire service "%s": argument "$%s" of method "%s::__construct()", you should configure its value explicitly.', $service, $argument, $plugin_class));
          }
        }, $args, array_keys($args));
        return new $plugin_class($configuration, $plugin_id, $plugin_definition, ...$args);
      }
      else {
        return $plugin_class::create(\Drupal::getContainer(), $configuration, $plugin_id, $plugin_definition);
      }
    }

    // Otherwise, create the plugin directly.
    return new $plugin_class($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Alters plugin definitions to include the set of constructor services.
   *
   * @param array &$definitions
   *   The plugin definitions.
   */
  public function alterDefinitions(&$definitions): void {
    foreach ($definitions as $id => $definition) {
      try {
        $class = static::getPluginClass($id, $definition);
      }
      catch (PluginException) {
        continue;
      }

      if (!is_subclass_of($class, 'Drupal\Core\Plugin\ContainerFactoryPluginInterface')) {
        continue;
      }

      if (method_exists($class, 'create')) {
        $factory = new \ReflectionMethod($class, 'create');
        if ($factory->getDeclaringClass()->getName() !== 'Drupal\Core\Plugin\PluginBase') {
          // The class provides a custom factory method, so skip autowiring.
          continue;
        }
      }

      $args = [];
      if (method_exists($class, '__construct')) {
        $constructor = new \ReflectionMethod($class, '__construct');
        foreach (array_slice($constructor->getParameters(), 3, NULL, TRUE) as $pos => $parameter) {
          $service = ltrim((string) $parameter->getType(), '?');
          foreach ($parameter->getAttributes(Autowire::class) as $attribute) {
            $service = (string) $attribute->newInstance()->value;
          }

          $invalidBehavior = $parameter->allowsNull() ? ContainerInterface::NULL_ON_INVALID_REFERENCE : ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE;
          $args[$pos] = new Reference($service, $invalidBehavior);
        }
      }

      if ($definition instanceof PluginDefinitionInterface) {
        $definition->constructorServices = $args;
      }
      else {
        $definitions[$id]['constructor_services'] = $args;
      }
    }
  }

}
