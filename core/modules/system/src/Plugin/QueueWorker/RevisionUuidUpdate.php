<?php

declare(strict_types=1);

namespace Drupal\system\Plugin\QueueWorker;

use Drupal\Component\Uuid\Php;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\system\Plugin\Derivative\RevisionUuidUpdateDeriver;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Updates the UUIDs for the entity revisions missing them.
 *
 * Each instance is derived by an entity type which supports revisions UUID. The
 * queue item data has no importance, usually is NULL. When the worker is called
 * to process an item, it updates a limited set of entities. If there are still
 * unprocessed entities left, it re-queues itself and resolve the rest of the
 * entities on the following run.
 */
#[QueueWorker(
  id: 'revision_uuid_update',
  title: new TranslatableMarkup('Update revision UUIDs'),
  deriver: RevisionUuidUpdateDeriver::class,
)]
class RevisionUuidUpdate extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly Php $uuid,
    protected readonly QueueFactory $queueFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('uuid'),
      $container->get('queue'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $entity_type_id = $this->getDerivativeId();

    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    assert($entity_type instanceof ContentEntityTypeInterface);
    $storage = $this->entityTypeManager->getStorage($entity_type_id);

    $revision_uuid_field = $entity_type->getRevisionMetadataKey('revision_uuid');

    $unprocessed_ids = array_values($storage->getQuery()
      ->accessCheck(FALSE)
      ->notExists($revision_uuid_field)
      ->execute());

    // Process up to 50 entities once.
    $ids_to_process = array_splice($unprocessed_ids, 0, 50);

    if ($ids_to_process) {
      foreach ($storage->loadMultiple($ids_to_process) as $entity) {
        $entity->set($revision_uuid_field, $this->uuid->generate())->save();
      }
    }

    if ($unprocessed_ids) {
      // There are still unprocessed items, requeue this entity type.
      $queue = $this->queueFactory->get($this->getPluginId());
      $queue->createQueue();
      $queue->createItem(NULL);
    }
  }

}
