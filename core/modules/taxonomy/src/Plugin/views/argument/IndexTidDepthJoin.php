<?php

namespace Drupal\taxonomy\Plugin\views\argument;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\TaxonomyIndexDepthJoinQueryTrait;
use Drupal\taxonomy\TermStorageInterface;
use Drupal\views\Attribute\ViewsArgument;

/**
 * Argument handler for taxonomy terms with depth using a term list join.
 *
 * This handler expands the term hierarchy in PHP using the term storage and
 * joins the taxonomy index on the resulting flat list of term IDs. This can
 * be more performant than the subquery used by the base handler on sites
 * with a fast entity cache.
 *
 * @ingroup views_argument_handlers
 */
#[ViewsArgument(
  id: 'taxonomy_index_tid_depth_join',
)]
class IndexTidDepthJoin extends IndexTidDepth {

  use TaxonomyIndexDepthJoinQueryTrait;

  /**
   * The term storage.
   */
  protected TermStorageInterface $termStorage;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityRepositoryInterface $entityRepository, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entityRepository);
    $this->termStorage = $entity_type_manager->getStorage('taxonomy_term');
  }

  /**
   * {@inheritdoc}
   */
  public function query($group_by = FALSE): void {
    $this->ensureMyTable();

    if (!empty($this->options['break_phrase'])) {
      $break = static::breakString($this->argument);
      if ($break->value === [-1]) {
        return;
      }
      $tids = $break->value;
    }
    else {
      $tids = $this->argument;
    }

    $this->addDepthJoinToQuery($tids);
  }

}
