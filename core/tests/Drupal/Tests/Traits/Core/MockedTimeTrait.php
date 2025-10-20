<?php

declare(strict_types=1);

namespace Drupal\Tests\Traits\Core;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\TestTools\Mocks\MockedTime;

/**
 * Provides a method to alter the container to use the MockedTime class.
 *
 * @phpstan-require-implements \Drupal\Core\DependencyInjection\ServiceModifierInterface
 */
trait MockedTimeTrait {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    $service_definition = $container->getDefinition('datetime.time');
    $service_definition->setClass(MockedTime::class);
  }

}
