<?php

declare(strict_types=1);

namespace Drupal\link_test_widget\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;

/**
 * A link widget that edits the class attribute.
 *
 * Like contributed attribute widgets, it adds an element in
 * ['options']['attributes'] and doesn't call the parent's
 * massageFormValues().
 */
#[FieldWidget(
  id: 'link_test_attribute',
  label: new TranslatableMarkup('Link with class'),
  field_types: ['link'],
)]
class LinkTestAttributeWidget extends LinkWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $class = $items[$delta]->options['attributes']['class'] ?? [];
    $element['options']['attributes']['class'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Class'),
      '#default_value' => implode(' ', $class),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    foreach ($values as &$value) {
      $class = $value['options']['attributes']['class'];
      $value['options']['attributes'] = $class === '' ? [] : ['class' => explode(' ', $class)];
    }
    return $values;
  }

}
