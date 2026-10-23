<?php

namespace Drupal\update;

/**
 * Default choices for sharing site data with Drupal.org.
 *
 * Nothing is sent while a data_sharing value in update.settings is NULL.
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

}
