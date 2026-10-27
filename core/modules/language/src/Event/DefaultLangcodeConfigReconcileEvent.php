<?php

declare(strict_types=1);

namespace Drupal\language\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Event fired before configuration is updated to a new default langcode.
 *
 * Subscribers can change the data that will be saved, or skip the
 * configuration so it keeps its current langcode. Nothing has been saved for
 * the configuration when this event is dispatched.
 *
 * @see \Drupal\language\DefaultLangcodeConfigUpdater::reconcileConfigLangcodeOperation()
 */
final class DefaultLangcodeConfigReconcileEvent extends Event {

  /**
   * Whether the configuration is skipped.
   */
  private bool $skipped = FALSE;

  /**
   * Constructs a DefaultLangcodeConfigReconcileEvent object.
   *
   * @param string $name
   *   The configuration name.
   * @param string $oldLangcode
   *   The previous default langcode.
   * @param string $newLangcode
   *   The new default langcode.
   * @param array $data
   *   The configuration data to save. Any override for the new default
   *   langcode is already merged in.
   * @param array $untranslatedKeys
   *   A nested array of the translatable key paths that still hold content in
   *   the previous default language.
   */
  public function __construct(
    public readonly string $name,
    public readonly string $oldLangcode,
    public readonly string $newLangcode,
    private array $data,
    public readonly array $untranslatedKeys,
  ) {}

  /**
   * Gets the configuration data to save.
   *
   * @return array
   *   The configuration data.
   */
  public function getData(): array {
    return $this->data;
  }

  /**
   * Sets the configuration data to save.
   *
   * The langcode key is set by the updater and any value set here is ignored.
   *
   * @param array $data
   *   The configuration data.
   */
  public function setData(array $data): void {
    $this->data = $data;
  }

  /**
   * Skips the configuration, and stops any further subscriber running.
   *
   * Skipped configuration keeps its current langcode and data.
   */
  public function skip(): void {
    $this->skipped = TRUE;
    $this->stopPropagation();
  }

  /**
   * Determines if the configuration is skipped.
   *
   * @return bool
   *   TRUE if the configuration is skipped, FALSE if not.
   */
  public function isSkipped(): bool {
    return $this->skipped;
  }

}
