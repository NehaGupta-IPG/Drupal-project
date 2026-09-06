<?php

declare(strict_types=1);

namespace Drupal\Core\Config;

use Drupal\Component\EventDispatcher\Event;

/**
 * Event fired to resolve the langcode for a configuration object.
 *
 * Subscribers can do two independent things: resolve a langcode when none, or
 * English, was supplied, and vouch for which langcodes are actually supported
 * on this site.
 *
 * @see \Drupal\Core\Config\ConfigEvents::RESOLVE_LANGCODE
 */
final class ConfigLangcodeResolveEvent extends Event {

  /**
   * The resolved langcode, or NULL if nothing has resolved one yet.
   */
  private ?string $langcode;

  /**
   * Langcodes subscribers have vouched for as supported on this site.
   *
   * @var string[]
   */
  private array $allowedLangcodes = [];

  /**
   * Constructs a ConfigLangcodeResolveEvent.
   *
   * @param string $name
   *   The name of the configuration object.
   * @param string|null $currentLangcode
   *   The langcode currently present on the configuration, if any. Seeds the
   *   resolved value so a subscriber that does nothing leaves it unchanged.
   * @param \Drupal\Core\Config\ConfigLangcodeContext $context
   *   Why the langcode is being resolved.
   */
  public function __construct(
    private readonly string $name,
    private readonly ?string $currentLangcode,
    private readonly ConfigLangcodeContext $context,
  ) {
    $this->langcode = $currentLangcode;
  }

  /**
   * Gets the name of the configuration object.
   *
   * @return string
   *   The configuration object name.
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * Gets the current langcode that was present on the configuration.
   *
   * @return string|null
   *   The current langcode, or NULL if none.
   */
  public function getCurrentLangcode(): ?string {
    return $this->currentLangcode;
  }

  /**
   * Gets why the langcode is being resolved.
   *
   * @return \Drupal\Core\Config\ConfigLangcodeContext
   *   The context.
   */
  public function getContext(): ConfigLangcodeContext {
    return $this->context;
  }

  /**
   * Gets the resolved langcode.
   *
   * @return string|null
   *   The resolved langcode, or NULL if nothing has resolved one.
   */
  public function getLangcode(): ?string {
    return $this->langcode;
  }

  /**
   * Sets the resolved langcode.
   *
   * @param string $langcode
   *   The langcode to use.
   */
  public function setLangcode(string $langcode): void {
    $this->langcode = $langcode;
  }

  /**
   * Vouches for a langcode being supported on this site.
   *
   * @param string $langcode
   *   The langcode.
   */
  public function allowLangcode(string $langcode): void {
    $this->allowedLangcodes[] = $langcode;
  }

  /**
   * Vouches for several langcodes being supported on this site.
   *
   * @param string[] $langcodes
   *   The langcodes.
   */
  public function allowLangcodes(array $langcodes): void {
    $this->allowedLangcodes = array_merge($this->allowedLangcodes, $langcodes);
  }

  /**
   * Whether the given langcode is supported on this site.
   *
   * @param string $langcode
   *   The langcode to check. There is nothing to check a missing langcode
   *   against - it is up to whoever dispatched the event to decide what a
   *   missing langcode means for them.
   *
   * @return bool
   *   TRUE if the langcode has been vouched for by a subscriber, FALSE
   *   otherwise.
   */
  public function isLangcodeSupported(string $langcode): bool {
    return in_array($langcode, $this->allowedLangcodes, TRUE);
  }

}
