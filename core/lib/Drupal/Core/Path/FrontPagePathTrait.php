<?php

namespace Drupal\Core\Path;

/**
 * Provides a trait for resolving the front page path.
 */
trait FrontPagePathTrait {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The default front page.
   *
   * @var string
   */
  protected $frontPage;

  /**
   * Gets the current front page path.
   *
   * @return string
   *   The front page path.
   */
  protected function getFrontPagePath() {
    // Lazy-load front page config.
    if (!isset($this->frontPage)) {
      $this->frontPage = $this->getConfigFactory()->get('system.site')
        ->get('page.front');
    }
    return $this->frontPage;
  }

  /**
   * Gets the config factory.
   *
   * @return \Drupal\Core\Config\ConfigFactoryInterface
   *   The config factory.
   */
  protected function getConfigFactory() {
    if (!$this->configFactory) {
      $this->configFactory = \Drupal::configFactory();
    }
    return $this->configFactory;
  }

}
