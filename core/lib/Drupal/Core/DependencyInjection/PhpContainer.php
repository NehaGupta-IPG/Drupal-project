<?php

declare(strict_types=1);

namespace Drupal\Core\DependencyInjection;

use Drupal\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;

/**
 * Base class for Symfony PhpDumper-generated containers in Drupal.
 */
class PhpContainer extends Container implements ContainerInterface {

  /**
   * {@inheritdoc}
   */
  public function set(string $id, ?object $service): void {
    // Symfony prevents replacing already-initialized services. Drupal allows
    // this; ModuleInstaller swaps the route provider during module install.
    // @todo Remove in https://www.drupal.org/project/drupal/issues/3585728
    unset($this->services[$id]);
    parent::set($id, $service);
  }

  /**
   * {@inheritdoc}
   */
  public function __sleep(): array {
    assert(FALSE, 'The container was serialized.');
    return array_keys(get_object_vars($this));
  }

}
