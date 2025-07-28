<?php

namespace Drupal\Core\DependencyInjection;

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;

/**
 * Definition of a container aware dispatcher.
 *
 * The class captures the addListener calls from RegisterListenersPass and
 * turns them into arguments.
 */
class ContainerAwareEventDispatcherDefinition extends Definition {

  /**
   * Whether the listeners have been sorted already.
   *
   * @var bool
   */
  protected bool $sorted;

  /**
   * {@inheritdoc}
   */
  public function addMethodCall(string $method, array $arguments = [], bool $returnsClone = FALSE): static {
    return $this->convertAddListenerToArgument($method, $arguments) ? $this : parent::addMethodCall($method, $arguments, $returnsClone);
  }

  /**
   * Convert an addMethodCall('addListener') call if possible.
   *
   * @param string $method
   *   The method name to call.
   * @param array $arguments
   *   An array of arguments to pass to the method call.
   *
   * phpcs:disable
   * @return bool
   *   TRUE when the call looks like
   *   $dispatcherDefinition->addMethodCall('addListener', [$event['event'], [new ServiceClosureArgument(new Reference($id)), $event['method']], $priority]);
   *   which is how \Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass
   *   registers listeners. FALSE otherwise.
   * phpcs:enable
   */
  protected function convertAddListenerToArgument(string $method, array $arguments): bool {
    if ($method !== 'addListener') {
      return FALSE;
    }
    if (!(count($arguments) === 3 && array_is_list($arguments))) {
      return FALSE;
    }
    [$eventName, $listenerSpec, $priority] = $arguments;
    if (!(is_array($listenerSpec) && count($listenerSpec) === 2 && array_is_list($listenerSpec) && $listenerSpec[0] instanceof ServiceClosureArgument)) {
      return FALSE;
    }
    [$serviceClosureArgument, $method] = $listenerSpec;
    // To quote ServiceClosureArgument::setValues(): A ServiceClosureArgument
    // must hold one and only one value.
    $ref = $serviceClosureArgument->getValues()[0];
    if (!$ref instanceof Reference) {
      return FALSE;
    }
    $this->arguments[1][$eventName][$priority][] = ['service' => [(string) $ref, $method]];
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function setArguments(array $arguments): static {
    $this->sorted = FALSE;
    return parent::setArguments($arguments);
  }

  /**
   * {@inheritdoc}
   */
  public function addArgument(mixed $argument): static {
    $this->sorted = FALSE;
    return parent::addArgument($argument);
  }

  /**
   * {@inheritdoc}
   */
  public function replaceArgument(int|string $index, mixed $argument): static {
    $this->sorted = FALSE;
    return parent::replaceArgument($index, $argument);
  }

  /**
   * {@inheritdoc}
   */
  public function setArgument(int|string $key, mixed $value): static {
    $this->sorted = FALSE;
    return parent::setArgument($key, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function getArguments(): array {
    $this->sortListeners();
    return parent::getArguments();
  }

  /**
   * {@inheritdoc}
   */
  public function getArgument(int|string $index): mixed {
    $this->sortListeners();
    return parent::getArgument($index);
  }

  /**
   * Sort listeners.
   */
  protected function sortListeners(): void {
    if (!$this->sorted) {
      foreach (array_keys($this->arguments[1] ?? []) as $event_name) {
        krsort($this->arguments[1][$event_name]);
      }
      $this->sorted = TRUE;
    }
  }

}
