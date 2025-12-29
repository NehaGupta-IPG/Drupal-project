<?php

namespace Drupal\system;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;

class RevisionUuidUpdater {

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * @param \Drupal\Core\State\StateInterface $state
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\Core\Database\Connection $database
   */
  public function __construct(StateInterface $state, EntityTypeManagerInterface $entity_type_manager, Connection $database) {
    $this->state = $state;
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
  }

  /**
   * @param int $range
   */
  public function update($range = 100) {
    $revision_uuid_update = $this->state->get('revision_uuid_update');
    foreach ((array) $revision_uuid_update as $entity_type_id) {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      $query = $this->database
        ->select($entity_type->getRevisionTable(), 'revision_table')
        ->fields('revision_table', [$entity_type->getKey('revision')])
        ->where($entity_type->getKey('revision') . ' = ' . $entity_type->getKey('revision_uuid'))
        ->range(0, $range);
      $revisions = $query->execute()->fetchCol();
      if ($revisions) {
        $this->updateRevisions($entity_type, $revisions);
      }
      else {
        unset($revision_uuid_update[$entity_type->id()]);
        $this->state->set('node:revision_uuid_update', $revision_uuid_update);
      }
    }
  }

  /**
   * Update all revisions.
   */
  public function updateAll() {
    $this->update(null);
  }

  /**
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   * @param array $revisions
   */
  protected function updateRevisions(EntityTypeInterface $entity_type, $revisions) {
    foreach ($revisions as $revision) {
      $this->database
        ->update($entity_type->getRevisionTable())
        ->fields([$entity_type->getKey('revision_uuid') => \Drupal::service('uuid')->generate()])
        ->condition($entity_type->getKey('revision'), $revision)
        ->execute();
    }
  }

}
