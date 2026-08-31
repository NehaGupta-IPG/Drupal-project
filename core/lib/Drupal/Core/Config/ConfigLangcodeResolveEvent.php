<?php

declare(strict_types=1);

namespace Drupal\Core\Config;

/**
 * Event fired to resolve the langcode for a configuration object.
 *
 * Subscribers can do two independent things: resolve a langcode when none, or
 * English, was supplied, and vouch for which langcodes are actually supported
 * on this site.
 *
 * Deliberately does not extend \Drupal\Component\EventDispatcher\Event
 * (Symfony's stoppable event base class): stopping propagation would skip
 * whichever subscribers have not run yet, including ones with nothing to do
 * with resolving a langcode - such as ones vouching for what languages are
 * supported, which self::isLangcodeSupported() depends on every subscriber
 * having had a chance to contribute to. self::setLangcode() already gives an
 * earlier, more specific subscriber precedence over a later, more general
 * one without needing to stop anything.
 *
 * @see \Drupal\Core\Config\ConfigEvents::RESOLVE_LANGCODE
 */
final class ConfigLangcodeResolveEvent {

  /**
   * The resolved langcode, or NULL if nothing has resolved one yet.
   */
  private ?string $langcode = NULL;

  /**
   * Langcodes subscribers have vouched for as supported on this site.
   *
   * @var string[]
   */
  private array $allowedLangcodes = [];

  /**
   * Constructs a ConfigLangcodeResolveEvent.
   *
   * @param string|null $currentLangcode
   *   The langcode currently present on the configuration, if any.
   * @param \Drupal\Core\Config\ConfigLangcodeContext $context
   *   Why the langcode is being resolved.
   */
  public function __construct(
    private readonly ?string $currentLangcode,
    private readonly ConfigLangcodeContext $context,
  ) {}

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
   *   The langcode a subscriber resolved, the current langcode if none has,
   *   or NULL if there was no current langcode either.
   */
  public function getLangcode(): ?string {
    return $this->langcode ?? $this->currentLangcode;
  }

  /**
   * Sets the resolved langcode.
   *
   * The first subscriber to call this wins: a later call is silently
   * ignored. This lets an earlier, more specific subscriber - for example a
   * module establishing its own default-langcode policy, such as always
   * defaulting new configuration to English regardless of the site default
   * - take precedence over a later, more general one (like core's own
   * site-default subscriber) without needing to stop propagation, which
   * would also stop unrelated subscribers - such as ones vouching for
   * supported langcodes - from running.
   *
   * @param string $langcode
   *   The langcode to use.
   *
   * @return bool
   *   TRUE if this call resolved the langcode, FALSE if a subscriber had
   *   already done so and this call was ignored.
   */
  public function setLangcode(string $langcode): bool {
    if ($this->langcode !== NULL) {
      return FALSE;
    }
    $this->langcode = $langcode;
    return TRUE;
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
