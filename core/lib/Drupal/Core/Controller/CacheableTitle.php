<?php

namespace Drupal\Core\Controller;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Allows attaching cacheability metadata to a title.
 */
class CacheableTitle extends BubbleableMetadata {

  /**
   * The title.
   *
   * @var \Drupal\Core\StringTranslation\TranslatableMarkup|array|string|null
   *   The title.
   */
  protected TranslatableMarkup|array|string|null $title = NULL;

  /**
   * Gets the title.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|array|string|null
   *   The title.
   */
  public function getTitle(): TranslatableMarkup|array|string|null {
    return $this->title;
  }

  /**
   * Sets the title.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|array|string|null $title
   *   The title.
   */
  public function setTitle(TranslatableMarkup|array|string|null $title): void {
    $this->title = $title;
  }

}
