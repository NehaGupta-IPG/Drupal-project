<?php

namespace Drupal\Core\DependencyInjection\Compiler;

use Drupal\Core\DependencyInjection\ContainerBuilder;

/**
 * Provides a method to remove compiler passes from the container build.
 */
trait RemovePassesTrait {

  /**
   * Removes compiler passes from all phases of the container build.
   *
   * @param \Drupal\Core\DependencyInjection\ContainerBuilder $container
   *   The container builder.
   * @param string[] $passClassesToRemove
   *   Fully qualified class names of compiler passes to remove.
   */
  protected function removePasses(ContainerBuilder $container, array $passClassesToRemove): void {
    $filter = fn($pass) => !in_array(get_class($pass), $passClassesToRemove, TRUE);

    $passConfig = $container->getCompilerPassConfig();
    $passConfig->setBeforeOptimizationPasses(array_values(array_filter($passConfig->getBeforeOptimizationPasses(), $filter)));
    $passConfig->setOptimizationPasses(array_values(array_filter($passConfig->getOptimizationPasses(), $filter)));
    $passConfig->setBeforeRemovingPasses(array_values(array_filter($passConfig->getBeforeRemovingPasses(), $filter)));
    $passConfig->setRemovingPasses(array_values(array_filter($passConfig->getRemovingPasses(), $filter)));
    $passConfig->setAfterRemovingPasses(array_values(array_filter($passConfig->getAfterRemovingPasses(), $filter)));
  }

}
