<?php

namespace Drupal\node\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Field\FieldUpdateActionBase;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;

/**
 * Demotes a node.
 */
#[Action(
  id: 'node_unpromote_action',
  label: new TranslatableMarkup('Demote selected content from front page'),
  context_definitions: [
    'node' => new EntityContextDefinition(
      data_type: 'entity:node',
      label: new TranslatableMarkup("Node"),
      required: TRUE,
    ),
  ],
)]
class DemoteNode extends FieldUpdateActionBase {

  /**
   * {@inheritdoc}
   */
  protected function getFieldsToUpdate() {
    return ['promote' => NodeInterface::NOT_PROMOTED];
  }

}
