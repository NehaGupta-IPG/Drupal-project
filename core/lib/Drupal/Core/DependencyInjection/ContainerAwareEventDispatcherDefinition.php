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
   * {@inheritdoc}
   */
  public function addMethodCall(string $method, array $arguments = [], bool $returnsClone = FALSE): static {
    // Capture
    // $dispatcherDefinition->addMethodCall('addListener', [$event['event'], [new ServiceClosureArgument(new Reference($id)), $event['method']], $priority]);
    // calls
    if ($method === 'addListener' && count($arguments) === 3 && array_is_list($arguments)) {
      [$eventName, $listenerSpec, $priority] = $arguments;
      if (count($listenerSpec) === 2 && array_is_list($listenerSpec) && $listenerSpec[0] instanceof ServiceClosureArgument) {
        // A ServiceClosureArgument holds one and only one value.
        $ref = $listenerSpec[0]->getValues()[0];
        if ($ref instanceof Reference) {
          $this->arguments[1][$eventName][$priority][] = ['service' => [(string) $ref, $listenerSpec[1]]];
          return $this;
        }
      }
    }
    return parent::addMethodCall($method, $arguments, $returnsClone);
  }

}
