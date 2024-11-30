<?php

declare(strict_types=1);

namespace Drupal\file\Trait;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Trait to provide sample items element for URL suggestions.
 */
trait UrlSuggestionTrait {

  use StringTranslationTrait;

  /**
   * Get the URL suggestion for the absolute URL.
   *
   * @return array
   */
  public function absoluteUrlSuggestion(): array {
    return [
      '#type' => 'item',
      '#title' => '',
      '#description' => $this->t('<strong>Example</strong>: https://www.example.com/sites/default/files/image.png'),
    ];
  }

  /**
   * Get the URL suggestion for the absolute URL.
   *
   * @return array
   */
  public function relativeUrlSuggestion(): array {
    return [
      '#type' => 'item',
      '#title' => '',
      '#description' => $this->t('<strong>Example</strong>: https://www.example.com/sites/default/files/image.png'),
    ];
  }

}
