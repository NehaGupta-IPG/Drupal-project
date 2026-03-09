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
   * A tag to pass the number of expected method calls after conversion.
   *
   * For example, if a test does
   *
   * @code
   * $container->getDefinition('event_dispatcher')
   *   ->addMethodCall('addSubscriber', [$this])
   * @endcode
   *
   * then it should also signal there will be a method call which can't be
   * converted to service get calls:
   *
   * @code
   * $container->getDefinition('event_dispatcher')
   *   ->addMethodCall('addSubscriber', [$this])
   *  ->addTag(RegisterEventSubscribersPass::CALLS, ['value' => 1]);
   * @endcode
   *
   * There is no known use cases for this outside of tests although perhaps
   * install code might use it, there's no devilry install doesn't do.
   */
  public const string CALLS = 'drupal.event_subscriber.conversion';

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
   * \Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass
   * builds the definition expecting their dispatcher, convert it to the
   * data structure expected by
   * \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher::__construct().
   *
   * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
   *   The container.
   */
  protected function convertEventDispatcherDefinitions(ContainerBuilder $container): void {
    foreach ($container->getDefinitions() as $definition) {
      if (($class = $definition->getClass()) && str_contains($class, 'EventDispatcher') && is_a($class, ContainerAwareEventDispatcher::class, TRUE)) {
        $definitionArgument = $definition->getArguments[1] ?? [];
        $methodCalls = $this->convertMethodCalls($definitionArgument, $definition->getMethodCalls());
        // There should be no method calls left after conversion
        $expectedMethodCalls = 0;
        // Or perhaps a test did a addMethodCall() which can't be converted.
        foreach ($definition->getTag(static::CALLS) as $tag) {
          $expectedMethodCalls = $tag['value'];
        }
        assert(count($methodCalls) === $expectedMethodCalls);
        // Still, if there are any those should not get lost.
        $definition->setMethodCalls($methodCalls);
        foreach (array_keys($definitionArgument) as $event_name) {
          krsort($definitionArgument[$event_name]);
        }
        $definition->setArgument(1, $definitionArgument);
      }
    }
  }

  /**
   * Convert method calls.
   *
   * @param array $definitionArgument
   *   Method calls.
   * @param array $methodCalls
   *   The event dispatcher service argument.
   *
   * @return array
   *   The new method calls, hopefully an empty array.
   */
  protected function convertMethodCalls(array &$definitionArgument, array $methodCalls): array {
    $newMethodCalls = [];
    foreach ($methodCalls as $methodCall) {
      if (!$this->convertAddListenerToArgument($definitionArgument, ... $methodCall)) {
        $newMethodCalls[] = $methodCall;
      }
    }
    return $newMethodCalls;
  }

  /**
   * Convert an addMethodCall('addListener') call if possible.
   *
   * @param array &$definitionArgument
   *   The listener definitions to be passed as a second argument to
   *   \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher::__construct()
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
  protected function convertAddListenerToArgument(array &$definitionArgument, string $method, array $arguments): bool {
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
      $definitionArgument[$eventName][$priority][] = ['service' => [(string) $ref, $serviceMethod]];
      return TRUE;
    }
    return FALSE;
  }

}
