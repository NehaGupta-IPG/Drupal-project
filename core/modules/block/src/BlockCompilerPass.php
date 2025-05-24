<?php

declare(strict_types=1);

namespace Drupal\block;

use Drupal\block\Hook\BlockBlockContentHooks;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Block compiler pass.
 */
final class BlockCompilerPass implements CompilerPassInterface {

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void {
    // If block_content.module is not installed, remove integration.
    if (!\array_key_exists('block_content', $container->getParameter('container.modules'))) {
      $container->removeDefinition(BlockBlockContentHooks::class);
      // @todo https://www.drupal.org/project/drupal/issues/3526411 remove the
      // need to update hook_implementations_map when removing hook services.
      $hookImplementationMap = $container->getParameter('hook_implementations_map');
      foreach ($hookImplementationMap as &$classes) {
        unset($classes[BlockBlockContentHooks::class]);
      }
    }
  }

}
