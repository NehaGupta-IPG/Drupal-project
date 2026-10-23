<?php

namespace Drupal\update;

use Drupal\Core\Config\Config;

/**
 * Default choices for sharing site data with Drupal.org.
 *
 * A NULL data_sharing value in update.settings means no choice is recorded.
 * Change a default by editing its constant.
 */
final class DataSharing {

  /**
   * Default for sharing the site domain name.
   */
  public const DEFAULT_DOMAIN = TRUE;

  /**
   * Default for sharing the site email address.
   */
  public const DEFAULT_SITE_MAIL = FALSE;

  /**
   * Returns TRUE if either choice has not been recorded.
   */
  public static function isUndecided(Config $update_settings): bool {
    return $update_settings->get('data_sharing.domain') === NULL || $update_settings->get('data_sharing.site_mail') === NULL;
  }

  /**
   * Returns the value in effect for a choice, or NULL while undecided.
   */
  public static function effectiveValue(string $key, Config $update_settings): ?bool {
    return $update_settings->get("data_sharing.$key");
  }

  /**
   * Returns the hostname that would be shared.
   *
   * @param string $base_url
   *   The base URL, as used for the site key in the update processor.
   */
  public static function hostname(string $base_url): ?string {
    return parse_url($base_url, PHP_URL_HOST) ?: NULL;
  }

}
