<?php

declare(strict_types=1);

namespace Drupal\media\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\media\OEmbed\ResourceException;
use Drupal\media\OEmbed\ResourceFetcher;
use Drupal\media\OEmbed\ResourceFetcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Refreshes stored oEmbed resources before they expire.
 *
 * @see \Drupal\media\OEmbed\ResourceFetcher::fetchResource()
 */
#[QueueWorker(
  id: ResourceFetcher::QUEUE_NAME,
  title: new TranslatableMarkup('oEmbed resource refresher'),
  cron: ['time' => 60]
)]
class OEmbedResourceRefresher extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ResourceFetcherInterface $resourceFetcher,
    protected LoggerInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('media.oembed.resource_fetcher'),
      $container->get('logger.factory')->get('media'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    // Only the core resource fetcher stores resources and queues refreshes.
    if (!$this->resourceFetcher instanceof ResourceFetcher) {
      return;
    }
    try {
      $this->resourceFetcher->refreshResource($data['url']);
    }
    catch (ResourceException $e) {
      // Keep the stored data until it expires, and do not retry the refresh.
      $this->logger->warning('The oEmbed resource @url could not be refreshed: @message', [
        '@url' => $data['url'],
        '@message' => $e->getMessage(),
      ]);
    }
  }

}
