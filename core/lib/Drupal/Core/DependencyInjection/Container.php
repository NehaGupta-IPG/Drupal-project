<?php

namespace Drupal\Core\DependencyInjection;

use Drupal\Component\DependencyInjection\Container as DrupalContainer;
use Drupal\Core\Utility\DeprecatedSleepTrait;

/**
 * Extends the container to prevent serialization.
 */
class Container extends DrupalContainer {

  use DeprecatedSleepTrait;

  /**
   * {@inheritdoc}
   */
  protected function doSerialize(): array {
    assert(FALSE, 'The container was serialized.');
    return get_object_vars($this);
  }

}
