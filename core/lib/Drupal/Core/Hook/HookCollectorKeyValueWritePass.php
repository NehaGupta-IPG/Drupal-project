<?php

declare(strict_types=1);

namespace Drupal\Core\Hook;

use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\Site\Settings;
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
    $hookData = $container->getParameter('.hook_data');
    if ($container->hasParameter('.theme_hook_data')) {
      $themeHookData = $container->getParameter('.theme_hook_data');
      $hookData = array_merge($hookData, $themeHookData);
      $hookData['preprocess_for_suggestions'] = array_merge($hookData['preprocess_for_suggestions'], $hookData['theme_preprocess_for_suggestions']);
      unset($hookData['theme_preprocess_for_suggestions']);
    }
    $keyvalue = $container->get('keyvalue')->get('hook_data');
    assert($keyvalue instanceof KeyValueStoreInterface);

    // During code deployments it is theoretically possible for one process to
    // start rebuilding the container just before code is deployed. If this then
    // completes after the container rebuild triggered by the deployment, it
    // could write permanently stale hook data to the database. To protect
    // against this, add the container cache key as a suffix to the key value
    // entries.
    $data_to_write = [];
    foreach ($hookData as $key => $value) {
      $data_to_write[$key . ':' . Settings::get('deployment_identifier', \Drupal::VERSION)] = $value;
    }
    $keyvalue->setMultiple($data_to_write);
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
