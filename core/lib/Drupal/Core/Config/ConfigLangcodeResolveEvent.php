<?php

declare(strict_types=1);

namespace Drupal\Core\Config;

use Drupal\Component\EventDispatcher\Event;

/**
 * Event fired to determine the default langcode for new configuration.
 *
 * Dispatched whenever configuration would otherwise be saved with no
 * meaningful langcode of its own. It carries no information about the
 * configuration being saved: every subscriber answers the same question -
 * "what should unlabelled configuration default to right now" - regardless
 * of what is asking. Whether a particular piece of configuration actually
 * needs an answer at all is decided by the caller before dispatching; this
 * event is never dispatched otherwise.
 *
 * self::setLangcode() stops propagation as soon as a subscriber answers, so
 * a higher-priority, more specific policy - for example a module always
 * defaulting new configuration to English regardless of the site default -
 * takes precedence over a later, more general one (like core's own
 * site-default subscriber) simply by running first. Stopping propagation
 * here is safe: unlike an earlier design, no subscriber also uses this event
 * to vouch for which langcodes are supported - that question is answered
 * directly from the language manager, wherever it is needed, so nothing is
 * ever waiting for propagation to continue.
 *
 * core.services.yml always registers a subscriber for the site's default
 * language at the lowest priority, so self::getLangcode() is guaranteed a
 * value by the time dispatch() returns.
 *
 * @see \Drupal\Core\Config\ConfigEvents::RESOLVE_LANGCODE
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
