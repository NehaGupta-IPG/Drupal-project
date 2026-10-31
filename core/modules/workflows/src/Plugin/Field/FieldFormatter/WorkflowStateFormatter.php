<?php

declare(strict_types=1);

namespace Drupal\workflows\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'workflow_state' formatter.
 */
#[FieldFormatter(
  id: 'workflow_state',
  label: new TranslatableMarkup('Workflow state'),
  field_types: [
    'workflow_state',
  ],
)]
class WorkflowStateFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    foreach ($items as $delta => $item) {
      /** @var \Drupal\workflows\Plugin\Field\FieldType\WorkflowStateItem $item */
      $label = $item->getStateLabel();
      $elements[$delta] = [
        '#markup' => $label ?: $item->value,
      ];
    }
    return $elements;
  }

}
