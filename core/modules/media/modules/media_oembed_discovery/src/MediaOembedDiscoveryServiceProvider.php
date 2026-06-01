<?php

declare(strict_types=1);

namespace Drupal\media_oembed_discovery;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\media_oembed_discovery\OEmbed\DiscoveringUrlResolver;

/**
 * Swaps the media URL resolver for one that performs oEmbed discovery.
 */
class MediaOembedDiscoveryServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    $container->getDefinition('media.oembed.url_resolver')
      ->setClass(DiscoveringUrlResolver::class);
  }

}
