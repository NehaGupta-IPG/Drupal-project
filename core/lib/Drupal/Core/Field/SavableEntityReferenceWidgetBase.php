<?php

declare(strict_types=1);

namespace Drupal\Core\Field;

use Drupal\Core\Field\Plugin\Field\FieldType\SavableEntityReferenceItemInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Base class for entity reference allowing to change the referenced entity.
 */
abstract class SavableEntityReferenceWidgetBase extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state): void {
    parent::extractFormValues($items, $form, $form_state);

    foreach ($items as $item) {
      if ($item instanceof SavableEntityReferenceItemInterface) {
        $this->processSubmittedItem($item, $form, $form_state);
      }
    }
  }

  /**
   * Processes the submitted item.
   *
   * @param \Drupal\Core\Field\Plugin\Field\FieldType\SavableEntityReferenceItemInterface $item
   *   An individual field item from the list.
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function processSubmittedItem(SavableEntityReferenceItemInterface $item, array $form, FormStateInterface $form_state): void {
    $item->setNeedsEntitySave($item->hasNewEntity() || $item->hasChangedEntity());
  }

}
