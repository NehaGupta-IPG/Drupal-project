<?php

namespace Drupal\Core\Extension;

/**
 * Provides methods to enable and disable feature flags.
 */
interface FeatureFlagInstallerInterface {

  /**
   * Enables a given list of feature flags.
   *
   * @param string[] $flagList
   *   An array of feature flag names.
   *
   * @return bool
   *   Whether any of the given feature flags have been enabled.
   *
   * @throws \Drupal\Core\Extension\ExtensionNameLengthException
   *   Thrown when a flag's name is longer than
   *   DRUPAL_EXTENSION_NAME_MAX_LENGTH.
   *
   * @throws \Drupal\Core\Extension\Exception\UnknownExtensionException
   *   Thrown when the flag does not exist.
   */
  public function enable(array $flagList): bool;

  /**
   * Disables a given list of feature flags.
   *
   * @param string[] $flagList
   *   An array of feature flag names.
   */
  public function disable(array $flagList): void;

}
