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
   * The parameter values.
   *
   * Generated containers declare this property and fill it in their
   * constructor. It is declared without a type to match their declaration.
   *
   * @var array
   */
  protected $parameters = [];

  /**
   * Sets the values of parameters that are not stored in the container.
   *
   * @param array $parameters
   *   The parameter values, keyed by parameter name.
   *
   * @internal
   *   Only for use by container storage, before the container is used.
   *   Generated containers build their parameter bag from the parameter values
   *   on first use, so the values must be set before that.
   *
   * @see \Drupal\Core\DependencyInjection\Compiler\RuntimeParametersPass
   */
  public function setRuntimeParameters(array $parameters): void {
    $this->parameters = $parameters + $this->parameters;
  }

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
  public function reset(): void {
    // Keep synthetic services and the request stack, so that code still
    // holding a reference to this container after a rebuild can use them.
    $persist_services = [];
    foreach ($this->services as $id => $service) {
      if ($id === 'request_stack' || isset($this->syntheticIds[$id])) {
        $persist_services[$id] = $service;
      }
    }
    parent::reset();
    $this->services = $persist_services;
  }

  /**
   * {@inheritdoc}
   */
  public function __sleep(): array {
    assert(FALSE, 'The container was serialized.');
    return array_keys(get_object_vars($this));
  }

}
