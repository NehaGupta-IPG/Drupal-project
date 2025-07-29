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
    $arrayIsListWithCount = static fn ($x, $count) => is_array($x) && count($x) === $count && array_is_list($x);
    if (
      $method === 'addListener' &&
      // phpcs:disable
      // The expected arguments are:
      // [$event['event'], [new ServiceClosureArgument(new Reference($id)), $event['method']], $priority]
      // phpcs:enable
      $arrayIsListWithCount($arguments, 3) &&
      ([$eventName, $listenerSpec, $priority] = $arguments) &&
      // The listener should be an array of two elements:
      // [new ServiceClosureArgument(new Reference($id)), $event['method']].
      $arrayIsListWithCount($listenerSpec, 2) &&
      ([$serviceClosureArgument, $serviceMethod] = $listenerSpec) &&
      // If it's a service closure, those hold exactly one argument,
      // extract it and check it is as expected.
      $serviceClosureArgument instanceof ServiceClosureArgument &&
      ($ref = $serviceClosureArgument->getValues()[0]) instanceof Reference) {
      $this->arguments[1][$eventName][$priority][] = ['service' => [(string) $ref, $serviceMethod]];
      return TRUE;
    }
    return FALSE;
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
