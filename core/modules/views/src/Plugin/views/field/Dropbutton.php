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
    $form['dropbutton_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Dropbutton type'),
      '#description' => $this->t('Select the dropbutton style type.'),
      '#options' => [
        '' => $this->t('Default'),
        'small' => $this->t('Small'),
        'extrasmall' => $this->t('Extra small'),
      ],
      '#default_value' => $this->options['dropbutton_type'] ?? '',
    ];
  }
}
