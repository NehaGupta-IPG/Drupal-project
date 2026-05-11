<?php

declare(strict_types=1);

namespace Drupal\hook_collector_altered_services;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\hook_collector_hook_attribute\Hook\HookRegisteredService;

/**
 * Overrides the AnotherServiceWithHook service with AlteredServiceWithHook.
 */
class HookCollectorAlteredServicesServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    if ($container->has(HookRegisteredService::class)) {
      $definition = $container->getDefinition(HookRegisteredService::class);
      $definition->setClass(AlteredServiceWithHook::class);
    }
  }

}
