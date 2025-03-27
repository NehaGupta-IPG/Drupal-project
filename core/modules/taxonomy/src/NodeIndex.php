<?php

namespace Drupal\taxonomy;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\node\NodeInterface;

/**
 * Defines a Controller class for maintaining the taxonomy node index.
 */
class NodeIndex {

  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly Connection $database,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns the maintain_index_table configuration value.
   */
  public function shouldMaintainIndexTable(): bool {
    $taxonomy_config = $this->configFactory->get('taxonomy.settings');
    $maintain_index_table = $taxonomy_config->get('maintain_index_table');
    return (bool) $maintain_index_table;
  }

  /**
   * Builds and inserts taxonomy index entries for a given node.
   *
   * The index lists all terms that are related to a given node entity, and is
   * therefore maintained at the entity level.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   */
  public function buildNodeIndex(NodeInterface $node): void {
    // We maintain a denormalized table of term/node relationships, containing
    // only data for current, published nodes.
    if (!$this->shouldMaintainIndexTable() || !($this->entityTypeManager->getStorage('node') instanceof SqlContentEntityStorage)) {
      return;
    }

    $status = $node->isPublished();
    $sticky = (int) $node->isSticky();
    // We only maintain the taxonomy index for published nodes.
    if ($status && $node->isDefaultRevision()) {
      // Collect a unique list of all the term IDs from all node fields.
      $tid_all = [];
      $entity_reference_class = 'Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem';
      foreach ($node->getFieldDefinitions() as $field) {
        $field_name = $field->getName();
        $class = $field->getItemDefinition()->getClass();
        $is_entity_reference_class = ($class === $entity_reference_class) || is_subclass_of($class, $entity_reference_class);
        if ($is_entity_reference_class && $field->getSetting('target_type') == 'taxonomy_term') {
          foreach ($node->getTranslationLanguages() as $language) {
            foreach ($node->getTranslation($language->getId())->$field_name as $item) {
              if (!$item->isEmpty()) {
                $tid_all[$item->target_id] = $item->target_id;
              }
            }
          }
        }
      }
      // Insert index entries for all the node's terms.
      if (!empty($tid_all)) {
        foreach ($tid_all as $tid) {
          $this->database->merge('taxonomy_index')
            ->keys(['nid' => $node->id(), 'tid' => $tid, 'status' => $node->isPublished()])
            ->fields(['sticky' => $sticky, 'created' => $node->getCreatedTime()])
            ->execute();
        }
      }
    }
  }

  /**
   * Deletes taxonomy index entries for a given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   */
  public function deleteNodeIndex(NodeInterface $node): void {
    if ($this->shouldMaintainIndexTable()) {
      $this->database->delete('taxonomy_index')->condition('nid', $node->id())->execute();
    }
  }

}
