<?php

declare(strict_types=1);

namespace Drupal\Core\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Marks services as private using Symfony's container.private tag.
 *
 * Services tagged with 'container.private' will trigger a deprecation when
 * accessed directly via Container::get(). Dependency injection is unaffected.
 *
 * This pass auto-tags certain service types (e.g., event subscribers) with
 * 'container.private'. Services can opt out by adding the 'container.public'
 * tag.
 *
 * @see \Symfony\Component\DependencyInjection\Compiler\AliasDeprecatedPublicServicesPass
 */
class PrivateServicePass implements CompilerPassInterface {

  /**
   * Tags that should automatically mark services as private.
   *
   * @var array<string, array{package: string, version: string}>
   */
  protected const array AUTO_PRIVATE_TAGS = [
    'event_subscriber' => [
      'package' => 'drupal/core',
      'version' => '11.4.0',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public function process(ContainerBuilder $container): void {
    foreach (static::AUTO_PRIVATE_TAGS as $source_tag => $attributes) {
      foreach ($container->findTaggedServiceIds($source_tag) as $service_id => $tags) {
        $definition = $container->getDefinition($service_id);
        if (!$definition->hasTag('container.public') && !$definition->hasTag('container.private') && !$definition->isSynthetic()) {
          $definition->addTag('container.private', $attributes);
        }
      }
    }
  }

}
