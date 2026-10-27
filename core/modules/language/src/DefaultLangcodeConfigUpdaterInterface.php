<?php

declare(strict_types=1);

namespace Drupal\language;

use Drupal\Core\Batch\BatchBuilder;

/**
 * Updates configuration when the default language changes.
 */
interface DefaultLangcodeConfigUpdaterInterface {

  /**
   * Builds the batch definition for a default langcode change.
   *
   * @param string $old_langcode
   *   The previous default langcode.
   * @param string $new_langcode
   *   The new default langcode.
   *
   * @return \Drupal\Core\Batch\BatchBuilder
   *   A batch builder object.
   */
  public function buildBatch(string $old_langcode, string $new_langcode): BatchBuilder;

  /**
   * Batch operation: updates configuration to match the new default langcode.
   *
   * Only configuration whose langcode currently matches the previous
   * default is touched. Anything explicitly set to some other langcode is left
   * alone.
   *
   * Before updating the current default configuration, the existing translated
   * strings are saved as an override for the old langcode. This preserves the
   * existing translations.
   *
   * A configuration override already existing for the new default langcode
   * is merged into the configuration being updated, then removed. The new
   * default language can no longer have an override of its own.
   *
   * Before each configuration object is saved, a
   * \Drupal\language\Event\DefaultLangcodeConfigReconcileEvent is dispatched.
   * Subscribers can change the data to save, or skip the configuration.
   *
   * @param string $old_langcode
   *   The previous default langcode.
   * @param string $new_langcode
   *   The new default langcode.
   * @param array|\ArrayAccess $context
   *   The batch context.
   *
   * @see \Drupal\language\Event\DefaultLangcodeConfigReconcileEvent
   */
  public function reconcileConfigLangcodeOperation(string $old_langcode, string $new_langcode, &$context): void;

  /**
   * Determines if configuration is being reconciled.
   *
   * @return bool
   *   TRUE while self::reconcileConfigLangcodeOperation() saves configuration,
   *   FALSE if not.
   */
  public function isReconciling(): bool;

}
