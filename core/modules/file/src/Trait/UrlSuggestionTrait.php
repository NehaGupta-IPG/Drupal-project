<?php

declare(strict_types=1);

namespace Drupal\file\Trait;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\Enum\FileUrlTypeEnum;

/**
 * Trait to provide sample items element for URL suggestions.
 */
trait UrlSuggestionTrait {

  use StringTranslationTrait;

  /**
   * Show link as relative or absolute.
   *
   * @param array $form
   *   Form array.
   * @param string $fieldName
   *   Field name.
   *
   * @return array
   *   Form array containing show link as options.
   */
  public function showLinkAs(array $form, string $fieldName): array {
    $form['show_link_as'] = [
      '#type' => 'radios',
      '#title' => $this->t('Show link as'),
      '#default_value' => $this->getSetting('show_link_as'),
      '#options' => [
        FileUrlTypeEnum::ABSOLUTE_URL->value => $this->t('Absolute URL'),
        FileUrlTypeEnum::RELATIVE_URL->value => $this->t('Relative URL'),
      ],
    ];
    $form['absolute_url_suggestion'] = $this->absoluteUrlSuggestion();
    $form['absolute_url_suggestion']['#states'] = [
      'visible' => [
        ':input[name="fields[' . $fieldName . '][settings_edit_form][settings][show_link_as]"]' => ['value' => 'absolute'],
      ],
    ];
    $form['relative_url_suggestion'] = $this->relativeUrlSuggestion();
    $form['relative_url_suggestion']['#states'] = [
      'visible' => [
        ':input[name="fields[' . $fieldName . '][settings_edit_form][settings][show_link_as]"]' => ['value' => 'relative'],
      ],
    ];
    return $form;
  }

  /**
   * Get the URL suggestion for the absolute URL.
   *
   * @return array
   *   Render item for absolute URL.
   */
  public function absoluteUrlSuggestion(): array {
    return [
      '#type' => 'item',
      '#title' => '',
      '#description' => $this->t('<strong>Example</strong>: https://www.example.com/sites/default/files/image.png'),
    ];
  }

  /**
   * Get the URL suggestion for the relative URL.
   *
   * @return array
   *   Render item for relative URL.
   */
  public function relativeUrlSuggestion(): array {
    return [
      '#type' => 'item',
      '#title' => '',
      '#description' => $this->t('<strong>Example</strong>: /sites/default/files/image.png'),
    ];
  }

}
