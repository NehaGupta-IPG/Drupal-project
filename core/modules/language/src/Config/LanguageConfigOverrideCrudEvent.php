<?php

namespace Drupal\language\Config;

use Drupal\Component\EventDispatcher\Event;

/**
 * Provides a language override event for event listeners.
 *
 * @see \Drupal\Core\Config\ConfigCrudEvent
 */
class LanguageConfigOverrideCrudEvent extends Event {

  /**
   * Configuration object.
   *
   * @var LanguageConfigOverride
   */
  protected $override;

  /**
   * Constructs a configuration event object.
   *
   * @param LanguageConfigOverride $override
   *   Configuration object.
   */
  public function __construct(LanguageConfigOverride $override) {
    $this->override = $override;
  }

  /**
   * Gets configuration object.
   *
   * @return LanguageConfigOverride
   *   The configuration object that caused the event to fire.
   */
  public function getLanguageConfigOverride() {
    return $this->override;
  }

}
