<?php

declare(strict_types=1);

namespace Drupal\node\Plugin\QueueWorker;

use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines 'node_index' queue worker.
 */
#[QueueWorker(
  id: 'node_index',
  title: new TranslatableMarkup('Node Index'),
  cron: ['time' => 60],
)]
final class NodeIndexQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    /** @var \Drupal\search\SearchPageRepositoryInterface $search_page_repository */
    $search_page_repository = \Drupal::service('search.search_page_repository');
    foreach ($search_page_repository->getIndexableSearchPages() as $entity) {
      /** @var NodeSearch $entity */
      if ($entity->getPlugin()->getPluginId() === 'node_search') {
        $entity->getPlugin()->performUpdateIndex($data);
        break;
      }
    }
  }

}
