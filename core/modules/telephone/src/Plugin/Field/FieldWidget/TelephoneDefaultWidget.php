<?php

namespace Drupal\telephone\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\StringTextfieldWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\telephone\Plugin\Field\FieldType\TelephoneItem;

/**
 * Plugin implementation of the 'telephone_default' widget.
 */
#[FieldWidget(
  id: 'telephone_default',
  label: new TranslatableMarkup('Telephone number'),
  field_types: ['telephone'],
)]
class TelephoneDefaultWidget extends StringTextfieldWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element['value'] = $element + [
      '#type' => 'tel',
      '#default_value' => $items[$delta]->value ?? NULL,
      '#size' => $this->getSetting('size'),
      '#placeholder' => $this->getSetting('placeholder'),
      '#maxlength' => TelephoneItem::MAX_LENGTH,
    ];
    return $element;
  }

}
