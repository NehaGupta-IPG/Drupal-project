<?php

declare(strict_types=1);

namespace Drupal\Core\Hook;

use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Stores hook implementations in keyvalue and clears cache.
 *
 * This is done in a separate, late compiler pass to ensure that a possibly
 * altered keyvalue service is respected.
 *
 * @internal
 *
 * @see \Drupal\Core\Hook\HookCollectorPass::writeToContainer
 */
class HookCollectorKeyValueWritePass implements CompilerPassInterface {

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void {
    // First validate that no hook services have been altered or decorated.
    $hookServiceIds = array_keys($container->findTaggedServiceIds('hook'));
    $invalidAlteredIds = [];
    $invalidDecoratorIds = [];
    foreach ($hookServiceIds as $id) {
      $definition = $container->getDefinition($id);
      $class = str_starts_with($id, 'hook.') ? substr($id, 5) : $id;
      if ($definition->getClass() !== $class) {
        if (preg_match('/Drupal\\\[^\\\]+\\\Hook\\\/', $id)) {
          @trigger_error(sprintf('Altering hook service %s is deprecated in drupal:11.5.0 and is not supported in drupal:12.0.0. Use the ReorderHook or RemoveHook attributes to control hook methods instead. See https://www.drupal.org/node/3526437', $id), E_USER_DEPRECATED);
          continue;
        }

        $invalidAlteredIds[] = $id;
      }
      elseif (!empty($definition->getChanges()['decorated_service'])) {
        if (preg_match('/Drupal\\\[^\\\]+\\\Hook\\\/', $id)) {
          @trigger_error(sprintf('Defining service %s to decorate a hook service is deprecated in drupal:11.5.0 and is not supported in drupal:12.0.0. Use the ReorderHook or RemoveHook attributes to control hook methods instead. See https://www.drupal.org/node/3526437', $id), E_USER_DEPRECATED);
          continue;
        }

        $invalidDecoratorIds[] = $id;
      }
    }
    if (!empty($invalidAlteredIds)) {
      throw new \LogicException(sprintf('Hook service(s) "%s" can not be altered', implode(', ', $invalidAlteredIds)));
    }
    if (!empty($invalidDecoratorIds)) {
      throw new \LogicException(sprintf('Service(s) "%s" can not decorate a hook service', implode(', ', $invalidDecoratorIds)));
    }

    $hookData = $container->getParameter('.hook_data');
    if ($container->hasParameter('.theme_hook_data')) {
      $themeHookData = $container->getParameter('.theme_hook_data');
      $hookData = array_merge($hookData, $themeHookData);
      $hookData['preprocess_for_suggestions'] = array_merge($hookData['preprocess_for_suggestions'], $hookData['theme_preprocess_for_suggestions']);
      unset($hookData['theme_preprocess_for_suggestions']);
    }
    $keyvalue = $container->get('keyvalue')->get('hook_data');
    assert($keyvalue instanceof KeyValueStoreInterface);
    $keyvalue->setMultiple($hookData);
    $container->get('cache.bootstrap')->deleteMultiple(['hook_data', 'theme_hook_data']);

    // Remove converted flags, they are only needed while building the
    // container.
    $parameters = $container->getParameterBag();
    foreach ($parameters->all() as $name => $value) {
      if (str_ends_with($name, '.skip_procedural_hook_scan')) {
        $parameters->remove($name);
      }
    }
  }

}
