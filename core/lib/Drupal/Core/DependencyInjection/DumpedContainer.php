<?php

namespace Drupal\Core\DependencyInjection;

use Drupal\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;

/**
 * Base class for Symfony PhpDumper-generated containers in Drupal.
 */
class DumpedContainer extends Container implements ContainerInterface {

  /**
   * {@inheritdoc}
   */
  public function set(string $id, ?object $service): void {
    // Symfony prevents replacing already-initialized services. Drupal allows
    // this; ModuleInstaller swaps the route provider during module install.
    unset($this->services[$id]);
    parent::set($id, $service);
  }

}
