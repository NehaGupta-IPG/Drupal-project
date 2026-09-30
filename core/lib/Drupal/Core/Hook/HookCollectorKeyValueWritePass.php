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
    $initializedBefore = array_filter($container->getServiceIds(), $container->initialized(...));
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

    // This pass creates the keyvalue and cache.bootstrap services and their
    // dependencies. Later compiler passes change the service definitions, but
    // not the instances that exist. For example, an event dispatcher instance
    // that exists at this point has no listeners. The update kernel and kernel
    // tests use this container builder as their container. Remove the
    // instances that this pass created. The container creates them again from
    // the complete definitions.
    $initializedAfter = array_filter($container->getServiceIds(), $container->initialized(...));
    foreach (array_diff($initializedAfter, $initializedBefore) as $id) {
      $container->set($id, NULL);
    }
  }

}
