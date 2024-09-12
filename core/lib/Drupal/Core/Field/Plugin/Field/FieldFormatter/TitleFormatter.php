<?php

namespace Drupal\Core\Field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'title' formatter.
 */
#[FieldFormatter(
  id: 'title',
  label: new TranslatableMarkup('Title'),
  field_types: [
    'string',
  ],
)]
class TitleFormatter extends StringFormatter {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);
    $heading_options = [
      'span' => 'span',
      'div' => 'div',
    ];
    foreach (range(1, 6) as $level) {
      $heading_options['h' . $level] = 'H' . $level;
    }

    $form['tag'] = [
      '#title' => $this->t('Tag'),
      '#type' => 'select',
      '#description' => $this->t('Select the tag which will be wrapped around the title.'),
      '#options' => $heading_options,
      '#default_value' => $this->getSetting('tag'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'tag' => 'h2',
      'link_to_entity' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();
    $summary[] = $this->t('Display as @tag', ['@tag' => $this->getSetting('tag')]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode = NULL): array {
    $items = parent::viewElements($items, $langcode);

    foreach ($items as &$item) {
      $tag = $this->getSetting('tag');
      $item['#prefix'] = "<$tag>";
      $item['#suffix'] = "</$tag>";
    }

    return $items;
  }

}
