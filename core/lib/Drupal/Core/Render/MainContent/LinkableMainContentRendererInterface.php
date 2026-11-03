<?php

namespace Drupal\Core\Render\MainContent;

use Drupal\Core\Url;

/**
 * Provides a mechanism for main content renders add attributes to links.
 */
interface LinkableMainContentRendererInterface extends MainContentRendererInterface {

  /**
   * Opens the given URL in the main content renderer.
   *
   * @param \Drupal\Core\Url $url
   *   The URL to open.
   * @param array $options
   *   (optional) An array of options.
   *
   * @return void
   *   opens the url in the main content renderer.
   */
  public function openUrlInRenderer(Url $url, $options = []): void;

}
