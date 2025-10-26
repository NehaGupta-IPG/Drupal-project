<?php

namespace Drupal\views\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\ResultRow;

/**
 * Provides a handler that renders links as dropbutton.
 *
 * @ingroup views_field_handlers
 */
#[ViewsField("dropbutton")]
class Dropbutton extends Links {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values): array|string {
    $links = $this->getLinks();

    if (!empty($links)) {
      return [
        '#type' => 'dropbutton',
        '#dropbutton_type' => $this->options['dropbutton_type'] ?? '',
        '#links' => $links,
      ];
    }

    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function defineOptions(): array {
    $options = parent::defineOptions();
    $options['dropbutton_type'] = ['default' => ''];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    parent::buildOptionsForm($form, $form_state);
    // Add option to select the dropbutton type.
    // @see \Drupal\Core\Render\Element\Dropbutton
    $options = self::getDropbuttonTypes(TRUE);
    $form['dropbutton_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Dropbutton type'),
      '#description' => $this->t('Select the dropbutton style type.'),
      '#options' => $options,
      '#default_value' => $this->options['dropbutton_type'] ?? '',
    ];
  }

  /**
   * Returns the allowed dropbutton_type choices.
   *
   * This is used for the config field schema
   * and also for the field options form.
   *
   * @param ?bool $options
   *   Whether to return the full options array.
   *   If NULL, only the keys are returned (default).
   *   For example, keys only for config schema
   *   Choices validation purposes.
   *
   * @see views.field.schema.yml
   *
   * @return array
   *   An array of allowed dropbutton type choices.
   */
  public static function getDropbuttonTypes(?bool $options = NULL): array {
    // Return allowed dropbutton types.
    // More can be added here as needed.
    $values = [
      '' => t('Default'),
      'small' => t('Small'),
      'extrasmall' => t('Extra small'),
    ];
    // If $options is NULL (default), return only the keys.
    // This is used for config schema validation.
    if ($options === NULL) {
      return array_keys($values);
    }

    // Otherwise, return the full key => value array.
    // This is used for the field options form.
    return $values;
  }

}
