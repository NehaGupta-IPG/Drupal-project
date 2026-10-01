<?php

declare(strict_types=1);

namespace Drupal\language;

use Drupal\Core\Batch\BatchBuilder;

/**
 * Reconciles configuration langcode with a new default language.
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
   * Batch operation: updates configuration following the previous default.
   *
   * Only configuration whose langcode currently matches the previous
   * default is touched - anything explicitly set to some other langcode is
   * left alone, the same way an ordinary save would leave it alone.
   *
   * Before updating, whatever the configuration's current content
   * actually is gets saved as an override for the langcode it is about to
   * stop being - otherwise it would simply be gone the moment locale module
   * applies the new default's own translation over the top. This is a plain
   * snapshot of the current values, not a translation.
   *
   * A configuration override already existing for the new default langcode
   * is absorbed into the configuration being updated, then removed: the new
   * default language can no longer have an override of its own once the active
   * configuration itself becomes its canonical copy.
   *
   * Before the first batch step reads the full configuration list, modules
   * can remove names from it via
   * hook_language_update_config_langcode_list_alter(), so that configuration
   * keeps its current langcode regardless of the new default.
   *
   * @param string $old_langcode
   *   The previous default langcode.
   * @param string $new_langcode
   *   The new default langcode.
   * @param array|\ArrayAccess $context
   *   The batch context.
   *
   * @see hook_language_update_config_langcode_list_alter()
   */
  public function reconcileConfigLangcodeOperation(string $old_langcode, string $new_langcode, &$context): void;

}
