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
    $settings['show_link_as'] = 'relative';
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);
    $form['show_link_as'] = [
      '#type' => 'radios',
      '#title' => $this->t('Show link as'),
      '#default_value' => $this->getSetting('show_link_as'),
      '#description' => $this->t('If checked, links will be rendered as absolute URLs.'),
      '#options' => [
        'absolute' => $this->t('Absolute'),
        'relative' => $this->t('Relative'),
      ],
    ];
    $form['absolute_url_suggestion'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('<strong>Example</strong>: https://www.example.com/sites/default/files/image.png'),
      '#states' => [
        'visible' => [
          ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][show_link_as]"]' => ['value' => 'absolute'],
        ],
      ]
    ];
    $form['relative_url_suggestion'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('<strong>Example</strong>: /sites/default/files/image.png'),
      '#states' => [
        'visible' => [
          ':input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][show_link_as]"]' => ['value' => 'relative'],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary[] = $this->getSetting('absolute_url') ? $this->t('Absolute URL') : $this->t('Relative URL');

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
        '#markup' => $file->createFileUrl($this->getSetting('show_link_as') === 'relative'),
        '#cache' => [
          'tags' => $file->getCacheTags(),
        ],
      ];

      if ($this->getSetting('show_link_as') === 'absolute') {
        $elements[$delta]['#cache']['contexts'] = ['url.site'];
      }
    }

    return $elements;
  }

}
