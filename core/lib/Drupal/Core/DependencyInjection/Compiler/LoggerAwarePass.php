<?php

namespace Drupal\Core\DependencyInjection\Compiler;

use Psr\Log\LoggerAwareInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Sets the logger on all services that implement LoggerAwareInterface.
 */
class LoggerAwarePass implements CompilerPassInterface {

  /**
   * Applied to every real instance, never a decorator.
   *
   * @see \Drupal\Core\DependencyInjection\Compiler\LoggerAwareInstanceTaggingPass
   */
  public const INSTANCE_TAG = '_logger_awar.instance';

  /**
   * Copy of '_provider', captured before decoration can move/strip it.
   */
  public const PROVIDER_TAG = '_logger_awar.instance_provider';

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void {
    $interface = LoggerAwareInterface::class;

    foreach ($container->findTaggedServiceIds(self::INSTANCE_TAG) as $id => $tags) {
      $definition = $container->getDefinition($id);
      if ($definition->hasMethodCall('setLogger')) {
        continue;
      }
      $providerTags = $definition->getTag(self::PROVIDER_TAG);
      $loggerId = 'logger.channel.' . ($providerTags[0]['provider'] ?? '');
      if ($container->has($loggerId)) {
        $definition->addMethodCall('setLogger', [new Reference($loggerId)]);
      }
    }

    // Tagged without implementing the interface is a genuine error,
    // unless it's a decorator that inherited the tag.
    foreach ($container->findTaggedServiceIds('logger_aware') as $id => $attributes) {
      $definition = $container->getDefinition($id);
      if (!is_subclass_of($definition->getClass(), $interface) && !$definition->hasTag('container.decorator')) {
        throw new \InvalidArgumentException(sprintf('Service "%s" (%s) must implement interface "%s".', $id, $definition->getClass(), $interface));
      }
    }
  }

}
