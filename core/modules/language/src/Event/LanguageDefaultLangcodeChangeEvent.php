<?php

declare(strict_types=1);

namespace Drupal\language\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Batch\BatchBuilder;

/**
 * Event fired when the site's default language changes.
 *
 * Subscribers add their own batch operations - for example, translating
 * updated configuration - to run as part of the same batch that
 * reconciles configuration langcode with the new default.
 */
final class LanguageDefaultLangcodeChangeEvent extends Event {

  /**
   * Constructs a LanguageDefaultLangcodeChangeEvent.
   *
   * @param string $oldLangcode
   *   The previous default langcode.
   * @param string $newLangcode
   *   The new default langcode.
   * @param \Drupal\Core\Batch\BatchBuilder $batch
   *   The batch builder object.
   */
  public function __construct(
    public readonly string $oldLangcode,
    public readonly string $newLangcode,
    private readonly BatchBuilder $batch,
  ) {}

  /**
   * Adds a batch operation to run as part of the same batch.
   *
   * The batch operation callback will be passed the list of configuration
   * object names updated by
   * DefaultLangcodeConfigUpdater::reconcileConfigLangcodeOperation() in
   * $context['results']['updated_names'].
   *
   * @param callable|string $callback
   *   The batch operation callback.
   * @param array $arguments
   *   Arguments to pass to the callback, before the $context argument
   *   Batch API appends automatically.
   *
   * @see \Drupal\language\DefaultLangcodeConfigUpdater::reconcileConfigLangcodeOperation()
   */
  public function addOperation(callable|string $callback, array $arguments = []): void {
    $this->batch->addOperation($callback, $arguments);
  }

  /**
   * Gets the batch builder object.
   *
   * @return \Drupal\Core\Batch\BatchBuilder
   *   The batch builder object.
   */
  public function getBatch(): BatchBuilder {
    return $this->batch;
  }

}
