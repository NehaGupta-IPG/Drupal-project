<?php

namespace Drupal\update;

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
   * Returns the hostname that would be shared.
   *
   * @param string $base_url
   *   The base URL, as used for the site key in the update processor.
   */
  public static function hostname(string $base_url): ?string {
    return parse_url($base_url, PHP_URL_HOST) ?: NULL;
  }

}
