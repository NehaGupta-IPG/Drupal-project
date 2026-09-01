<?php

declare(strict_types=1);

namespace Drupal\Core\Config;

use Drupal\Component\EventDispatcher\Event;

/**
 * Event fired to resolve the langcode for a configuration object.
 *
 * @see \Drupal\Core\Config\ConfigEvents::RESOLVE_LANGCODE
 */
final class ConfigLangcodeResolveEvent extends Event {

  /**
   * The resolved langcode, or NULL if nothing has resolved one yet.
   */
  private ?string $langcode;

  /**
   * Whether a subscriber has rejected the configuration outright.
   */
  private bool $rejected = FALSE;

  /**
   * The reason the configuration was rejected, if it was.
   */
  private ?string $rejectionReason = NULL;

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
   * Rejects the configuration outright.
   *
   * Used for \Drupal\Core\Config\ConfigLangcodeContext::ExtensionInstall,
   * when configuration ships with an explicit langcode that is not supported
   * by the site, so it should not be installed at all.
   *
   * @param string $reason
   *   Why the configuration was rejected.
   */
  public function reject(string $reason): void {
    $this->rejected = TRUE;
    $this->rejectionReason = $reason;
  }

  /**
   * Whether the configuration has been rejected.
   *
   * @return bool
   *   TRUE if rejected.
   */
  public function isRejected(): bool {
    return $this->rejected;
  }

  /**
   * Gets the rejection reason.
   *
   * @return string|null
   *   The reason, or NULL if not rejected.
   */
  public function getRejectionReason(): ?string {
    return $this->rejectionReason;
  }

}
