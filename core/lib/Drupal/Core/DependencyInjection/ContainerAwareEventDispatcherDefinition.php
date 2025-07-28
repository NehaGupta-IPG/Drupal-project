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
    if ($method === 'addListener' && ($info = $this->parseAddListener($arguments))) {
      ['eventName' => $eventName, 'priority' => $priority, 'service' => $service] = $info;
      $this->arguments[1][$eventName][$priority][] = $service;
      $this->sorted = FALSE;
      return $this;
    }
    else {
      return parent::addMethodCall($method, $arguments, $returnsClone);
    }
  }

  /**
   * Parses the arguments of $this->methodCall('addListener').
   *
   * @param array $arguments
   *   The arguments.
   *
   * phpcs:disable
   * @return array
   *   An associative array with keys eventName, service and priority with
   *   corresponding value if the call looks like
   *   $dispatcherDefinition->addMethodCall('addListener', [$event['event'], [new ServiceClosureArgument(new Reference($id)), $event['method']], $priority]);
   *   which is how RegisterListenerPass registers listeners. An empty array
   *   otherwise.
   * phpcs:enable
   */
  protected function parseAddListener(array $arguments): array {
    if (!(count($arguments) === 3 && array_is_list($arguments))) {
      return [];
    }
    [$eventName, $listenerSpec, $priority] = $arguments;
    if (!(is_array($listenerSpec) && count($listenerSpec) === 2 && array_is_list($listenerSpec) && $listenerSpec[0] instanceof ServiceClosureArgument)) {
      return [];
    }
    // A ServiceClosureArgument holds one and only one value.
    $ref = $listenerSpec[0]->getValues()[0];
    if (!$ref instanceof Reference) {
      return [];
    }
    return [
      'eventName' => $eventName,
      'service' => ['service' => [(string) $ref, $listenerSpec[1]]],
      'priority' => $priority,
    ];
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
