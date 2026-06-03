<?php

declare(strict_types=1);

namespace Drupal\hook_collector_alter_hook_class;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\hook_collector_alter_decorate_target\Hook\TestHooks;

/**
 * Replaces the class for the TestHooks hook service.
 */
class HookCollectorAlterHookClassServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    if ($container->hasDefinition(TestHooks::class)) {
      $container->getDefinition(TestHooks::class)
        ->setClass(TestHooksAltered::class);
    }
  }

}
