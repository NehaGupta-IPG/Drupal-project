<?php

namespace Drupal\file\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\Enum\FileUrlTypeEnum;
use Drupal\file\FileInterface;
use Drupal\file\Trait\UrlSuggestionTrait;

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

  use UrlSuggestionTrait;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    $settings = parent::defaultSettings();
    $settings['show_link_as'] = FileUrlTypeEnum::RELATIVE_URL->value;
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);
    $form = $this->showLinkAs($form, $this->fieldDefinition->getName());
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary[] = (FileUrlTypeEnum::tryFrom($this->getSetting('show_link_as')) === FileUrlTypeEnum::ABSOLUTE_URL) ? $this->t('Absolute URL') : $this->t('Relative URL');

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $absolute_link = FileUrlTypeEnum::tryFrom($this->getSetting('show_link_as')) === FileUrlTypeEnum::ABSOLUTE_URL;

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $file) {
      assert($file instanceof FileInterface);
      $elements[$delta] = [
        '#markup' => $file->createFileUrl(!$absolute_link),
        '#cache' => [
          'tags' => $file->getCacheTags(),
        ],
      ];

      if ($absolute_link) {
        $elements[$delta]['#cache']['contexts'] = ['url.site'];
      }
    }

    return $elements;
  }

}
