<?php

namespace Drupal\Core;

use Drupal\Core\Render\BubbleableMetadata;

/**
 * Used for returning generated URLs and their associated bubbleable metadata.
 *
 * Note: Do not confuse this with \Drupal\Core\Url. That's typically used for
 *  managing URLs that are not yet generated (generally includes route name and route parameters).
 */
class GeneratedUrl extends BubbleableMetadata {

  /**
   * The string value of the URL.
   *
   * @var string
   */
  protected $generatedUrl = '';

  /**
   * Gets the generated URL.
   *
   * @return string
   *   The generated URL.
   */
  public function getGeneratedUrl() {
    return $this->generatedUrl;
  }

  /**
   * Sets the generated URL.
   *
   * @param string $generated_url
   *   The generated URL.
   *
   * @return $this
   */
  public function setGeneratedUrl($generated_url) {
    $this->generatedUrl = $generated_url;
    return $this;
  }

}
