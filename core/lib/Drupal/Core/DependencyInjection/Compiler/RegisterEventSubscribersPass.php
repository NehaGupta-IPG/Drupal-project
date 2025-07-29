<?php

namespace Drupal\Core\DependencyInjection\Compiler;

use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;

/**
 * Wraps the Symfony event subscriber pass to use different tag names.
 */
class RegisterEventSubscribersPass implements CompilerPassInterface {

  /**
   * Constructs a RegisterEventSubscribersPass object.
   *
   * @param \Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass $pass
   *   The Symfony compiler pass that registers event subscribers.
   */
  public function __construct(
    protected RegisterListenersPass $pass,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void {
    $this->renameTag($container, 'event_subscriber', 'kernel.event_subscriber');
    $this->pass->process($container);
    $this->renameTag($container, 'kernel.event_subscriber', 'event_subscriber');
    $this->convertEventDispatcherDefinitions($container);
  }

  /**
   * Renames tags in the container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *   The container.
   * @param string $source_tag
   *   The tag to be renamed.
   * @param string $target_tag
   *   The tag to rename with.
   */
  protected function renameTag(ContainerBuilder $container, string $source_tag, string $target_tag): void {
    foreach ($container->getDefinitions() as $definition) {
      if ($definition->hasTag($source_tag)) {
        $attributes = $definition->getTag($source_tag)[0];
        $definition->addTag($target_tag, $attributes);
        $definition->clearTag($source_tag);
      }
    }
  }

  /**
   * Convert container aware dispatcher definitions.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *   The container.
   */
  protected function convertEventDispatcherDefinitions(ContainerBuilder $container): void {
    foreach ($container->getDefinitions() as $id => $definition) {
      if (($class = $definition->getClass()) && str_contains($class, 'EventDispatcher') && is_a($class, ContainerAwareEventDispatcher::class, TRUE)) {
        $argument = $definition->getArguments[1] ?? [];
        $methodCalls = $this->convertMethodCalls($definition->getMethodCalls(), $argument);
        assert(count($methodCalls) === 0);
        $definition->setMethodCalls($methodCalls);
        $definition->setArgument(1, $argument);
      }
    }
  }


  /**
   * @param array $methodCalls
   * @param array $argument
   *
   * @return array
   */
  protected function convertMethodCalls(array $methodCalls, array &$argument): array {
    $newMethodCalls = [];
    foreach ($methodCalls as $methodCall) {
      if (!$this->convertAddListenerToArgument($argument, ... $methodCall)) {
        $newMethodCalls[] = $methodCall;
      }
    }
    return $newMethodCalls;
  }

  protected function convertAddListenerToArgument(array &$argument, string $method, array $arguments): bool {
    $arrayIsListWithCount = static fn ($x, int $count) => is_array($x) && count($x) === $count && array_is_list($x);
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
      $argument[$eventName][$priority][] = ['service' => [(string) $ref, $serviceMethod]];
      return TRUE;
    }
    return FALSE;
  }


}
