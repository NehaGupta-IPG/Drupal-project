<?php

namespace Drupal\taxonomy\Hook;

use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\node\NodeInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Hook implementations for taxonomy.
 */
class TaxonomyEntityHooks {

  use StringTranslationTrait;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected Connection $database,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Returns the maintain_index_table configuration value.
   *
   */
  protected function shouldMaintainIndexTable(): bool {
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
  protected function buildNodeIndex(NodeInterface $node): void {
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
  protected function deleteNodeIndex(NodeInterface $node): void {
    if ($this->shouldMaintainIndexTable()) {
      $this->database->delete('taxonomy_index')->condition('nid', $node->id())->execute();
    }
  }

  /**
   * Implements hook_entity_operation().
   */
  #[Hook('entity_operation')]
  public function entityOperation(EntityInterface $term): array {
    $operations = [];
    if ($term instanceof Term && $term->access('create')) {
      $operations['add-child'] = [
        'title' => $this->t('Add child'),
        'weight' => 10,
        'url' => Url::fromRoute('entity.taxonomy_term.add_form', [
          'taxonomy_vocabulary' => $term->bundle(),
        ], [
          'query' => [
            'parent' => $term->id(),
          ],
        ]),
      ];
    }
    return $operations;
  }

  /**
   * @defgroup taxonomy_index Taxonomy indexing
   * @{
   * Functions to maintain taxonomy indexing.
   *
   * Taxonomy uses default field storage to store canonical relationships
   * between terms and fieldable entities. However its most common use case
   * requires listing all content associated with a term or group of terms
   * sorted by creation date. To avoid slow queries due to joining across
   * multiple node and field tables with various conditions and order by
   * criteria, we maintain a denormalized table with all relationships between
   * terms, published nodes and common sort criteria such as status, sticky and
   * created. When using other field storage engines or alternative methods of
   * denormalizing this data you should set the
   * taxonomy.settings:maintain_index_table to '0' to avoid unnecessary writes
   * in SQL.
   */

  /**
   * Implements hook_ENTITY_TYPE_insert() for node entities.
   */
  #[Hook('node_insert')]
  public function nodeInsert(EntityInterface $node): void {
    // Add taxonomy index entries for the node.
    $this->buildNodeIndex($node);
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for node entities.
   */
  #[Hook('node_update')]
  public function nodeUpdate(EntityInterface $node): void {
    // If we're not dealing with the default revision of the node, do not make any
    // change to the taxonomy index.
    if (!$node->isDefaultRevision()) {
      return;
    }
    $this->deleteNodeIndex($node);
    $this->buildNodeIndex($node);
  }

  /**
   * Implements hook_ENTITY_TYPE_predelete() for node entities.
   */
  #[Hook('node_predelete')]
  public function nodePredelete(EntityInterface $node): void {
    // Clean up the {taxonomy_index} table when nodes are deleted.
    $this->deleteNodeIndex($node);
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for taxonomy_term entities.
   */
  #[Hook('taxonomy_term_delete')]
  public function taxonomyTermDelete(Term $term): void {
    if ($this->shouldMaintainIndexTable()) {
      // Clean up the {taxonomy_index} table when terms are deleted.
      $this->database->delete('taxonomy_index')->condition('tid', $term->id())->execute();
    }
  }

}
