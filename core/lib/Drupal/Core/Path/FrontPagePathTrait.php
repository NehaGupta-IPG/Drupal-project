<?php

namespace Drupal\Core\Path;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Provides a trait for resolving the front page path.
 */
trait FrontPagePathTrait {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ?ConfigFactoryInterface $configFactory;

  /**
   * The default front page.
   *
   * @var string
   */
  protected ?string $frontPage;

  /**
   * Gets the current front page path.
   *
   * @return string|null
   *   The front page path or null if "system.site:page.front" is not set.
   */
  protected function getFrontPagePath(): ?string {
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
  protected function getConfigFactory(): ConfigFactoryInterface {
    if (!$this->configFactory) {
      $this->configFactory = \Drupal::configFactory();
    }
    return $this->configFactory;
  }

}
