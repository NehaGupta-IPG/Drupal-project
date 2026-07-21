<?php

namespace Drupal\Core\DependencyInjection\Compiler;

use Psr\Log\LoggerAwareInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tags every LoggerAwareInterface instance, pinned through decoration.
 *
 * Must run before DecoratorServicePass (TYPE_OPTIMIZE); registered at
 * TYPE_BEFORE_OPTIMIZATION, default priority, in CoreServiceProvider.
 *
 * @see \Drupal\Core\DependencyInjection\Compiler\LoggerAwarePass
 */
class LoggerAwareInstanceTaggingPass implements CompilerPassInterface {

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void {
    $interface = LoggerAwareInterface::class;
    $tagged = FALSE;

    foreach ($container->getDefinitions() as $definition) {
      $class = $definition->getClass();
      if ($class === NULL || !is_subclass_of($class, $interface)) {
        continue;
      }

      $tagged = TRUE;
      if (!$definition->hasTag('container.do_not_inline')) {
        $definition->addTag('container.do_not_inline');
      }
      $definition->addTag(LoggerAwarePass::INSTANCE_TAG);

      $providerTags = $definition->getTag('_provider');
      $provider = $providerTags[0]['provider'] ?? NULL;
      if ($provider !== NULL) {
        $definition->addTag(LoggerAwarePass::PROVIDER_TAG, ['provider' => $provider]);
      }
    }

    if ($tagged) {
      $this->pinThroughDecoration($container, LoggerAwarePass::INSTANCE_TAG);
      $this->pinThroughDecoration($container, LoggerAwarePass::PROVIDER_TAG);
    }
  }

  /**
   * Keeps $tagName pinned through decoration (mirrors Symfony's own default).
   */
  private function pinThroughDecoration(ContainerBuilder $container, string $tagName): void {
    $parameter = 'container.behavior_describing_tags';
    $tags = $container->hasParameter($parameter)
      ? $container->getParameter($parameter) : [
        'proxy',
        'container.do_not_inline',
        'container.service_locator',
        'container.service_subscriber',
        'container.service_subscriber.locator',
      ];
    if (!in_array($tagName, $tags, TRUE)) {
      $tags[] = $tagName;
      $container->setParameter($parameter, $tags);
    }
  }

}
