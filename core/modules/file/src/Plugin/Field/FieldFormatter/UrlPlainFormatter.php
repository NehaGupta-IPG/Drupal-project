<?php

namespace Drupal\file\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;

/**
 * Plugin implementation of the 'file_url_plain' formatter.
 */
#[FieldFormatter(
  id: 'file_url_plain',
  label: new TranslatableMarkup('URL to file'),
  field_types: [
    'file',
  ],
)]
class UrlPlainFormatter extends FileFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    $settings = parent::defaultSettings();
    $settings['absolute_url'] = FALSE;
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);
    $form['absolute_url'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Render as absolute url'),
      '#description' => $this->t('If checked, links will be rendered as absolute urls.'),
      '#default_value' => $this->getSetting('absolute_url'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary[] = $this->getSetting('absolute_url') ? $this->t('Rendered as absolute url') : $this->t('Rendered as relative url');

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $file) {
      assert($file instanceof FileInterface);
      $elements[$delta] = [
        '#markup' => $file->createFileUrl(!$this->getSetting('absolute_url')),
        '#cache' => [
          'tags' => $file->getCacheTags(),
        ],
      ];

      if ($this->getSetting('absolute_url')) {
        $elements[$delta]['#cache']['contexts'] = ['url.site'];
      }
    }

    return $elements;
  }

}
