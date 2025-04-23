<?php

namespace Drupal\Core\Render\MainContent;

use Drupal\Core\Url;

/**
 * Provides a mechanism for main content renders add attributes to links.
 */
interface LinkableMainContentRendererInterface extends MainContentRendererInterface {

  /**
   * Opens the given URL in the main content renderer.
   */
  public function openUrlInRenderer(Url $url, $options = []);

} 