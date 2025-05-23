<?php

namespace Drupal\node\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Field\FieldUpdateActionBase;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;

/**
 * Makes a node sticky.
 */
#[Action(
  id: 'node_make_sticky_action',
  label: new TranslatableMarkup('Make selected content sticky'),
  context_definitions: [
    'node' => new EntityContextDefinition(
      data_type: 'entity:node',
      label: new TranslatableMarkup("Node"),
      required: TRUE,
    ),
  ],
)]
class StickyNode extends FieldUpdateActionBase {

  /**
   * {@inheritdoc}
   */
  protected function getFieldsToUpdate() {
    return ['sticky' => NodeInterface::STICKY];
  }

}
