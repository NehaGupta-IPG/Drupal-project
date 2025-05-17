<?php

declare(strict_types=1);

namespace Drupal\block_content;

use Drupal\block_content\BlockUsage\BlockContentBlockUsage;
use Drupal\block_content\BlockUsage\BlockContentBlockUsageInterface;
use Drupal\block_content\Hook\BlockContentBlockHooks;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Block Content compiler pass.
 */
final class BlockContentCompilerPass implements CompilerPassInterface {

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void {
    // If block.module is not installed, remove integration.
    if (!\array_key_exists('block', $container->getParameter('container.modules'))) {
      $container->removeAlias(BlockContentBlockUsageInterface::class);
      $container->removeDefinition(BlockContentBlockUsage::class);
      $container->removeDefinition(BlockContentBlockHooks::class);
    }
  }

}
