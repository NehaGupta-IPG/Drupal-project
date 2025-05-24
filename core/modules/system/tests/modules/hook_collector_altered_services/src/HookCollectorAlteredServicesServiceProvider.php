<?php

declare(strict_types=1);

namespace Drupal\hook_collector_altered_services;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\hook_collector_hook_attribute\AnotherServiceWithHook;

/**
 * Overrides the AnotherServiceWithHook service with AlteredServiceWithHook.
 */
class HookCollectorAlteredServicesServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    if ($container->has(AnotherServiceWithHook::class)) {
      $definition = $container->getDefinition(AnotherServiceWithHook::class);
      $definition->setClass(AlteredServiceWithHook::class);
    }
  }

}
