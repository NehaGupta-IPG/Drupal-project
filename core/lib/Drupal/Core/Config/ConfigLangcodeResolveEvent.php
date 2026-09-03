<?php

declare(strict_types=1);

namespace Drupal\Core\Config;

use Drupal\Component\EventDispatcher\Event;

/**
 * Event fired to determine the default langcode for new configuration.
 *
 * Dispatched whenever translatable configuration has no langcode set and during
 * extension install when the langcode is 'en'.
 *
 * self::setLangcode() stops propagation as soon as a subscriber answers, so
 * a higher-priority, more specific policy takes precedence over a later, more
 * general one simply by running first. It is up to the subscriber to ensure the
 * langcode is valid for the site.
 *
 * If a subscriber is added by a contributed or custom module, the
 * DefaultConfigLangcodeSubscriber event must be changed to account for the new
 * source of truth.
 *
 * Drupal core always registers a subscriber for the site's default language, so
 * self::getLangcode() is guaranteed a value by the time dispatch() returns.
 *
 * @see \Drupal\language\EventSubscriber\DefaultConfigLangcodeSubscriber
 */
final class ConfigLangcodeResolveEvent extends Event {

  /**
   * The resolved langcode.
   */
  private string $langcode;

  /**
   * Gets the resolved langcode.
   *
   * @return string
   *   The langcode.
   */
  public function getLangcode(): string {
    return $this->langcode;
  }

  /**
   * Sets the resolved langcode, and stops any further subscriber running.
   *
   * @param string $langcode
   *   The langcode to use.
   */
  public function setLangcode(string $langcode): void {
    $this->langcode = $langcode;
    $this->stopPropagation();
  }

}
