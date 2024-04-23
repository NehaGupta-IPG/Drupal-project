<?php

namespace Drupal\layout_builder_test\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a block that renders an arbitrary entity.
 *
 * @Block(
 *   id = "layout_builder_test_entity_block",
 *   admin_label = @Translation("Test Entity Block"),
 * )
 */
class EntityBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'entity_type_id' => NULL,
      'entity_id' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $entity = \Drupal::entityTypeManager()
      ->getStorage($this->configuration['entity_type_id'])
      ->load($this->configuration['entity_id']);
    return \Drupal::entityTypeManager()
      ->getViewBuilder($this->configuration['entity_type_id'])
      ->view($entity);
  }

}
