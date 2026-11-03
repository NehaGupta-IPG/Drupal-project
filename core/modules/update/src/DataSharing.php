<?php

namespace Drupal\update;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\State\StateInterface;

/**
 * Default choices for sharing site data with Drupal.org.
 *
 * A NULL data_sharing value in update.settings means no choice is recorded.
 * Nothing is sent while undecided inside the notice period. After it, the
 * defaults apply until a choice is saved. Change a default by editing its
 * constant.
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
   * Notice period in seconds (30 days).
   */
  public const NOTICE_PERIOD = 30 * 24 * 60 * 60;

  /**
   * State key holding the timestamp the notice period started.
   */
  public const NOTICE_START_STATE_KEY = 'update.data_sharing_notice_start';

  /**
   * Returns TRUE if either choice has not been recorded.
   */
  public static function isUndecided(Config $update_settings): bool {
    return $update_settings->get('data_sharing.domain') === NULL || $update_settings->get('data_sharing.site_mail') === NULL;
  }

  /**
   * Returns the timestamp the notice period ends, or NULL if it has no start.
   */
  public static function noticeEndsAt(StateInterface $state): ?int {
    $start = $state->get(static::NOTICE_START_STATE_KEY);
    return $start === NULL ? NULL : $start + static::NOTICE_PERIOD;
  }

  /**
   * Returns TRUE if the notice period has ended.
   */
  public static function noticeEnded(StateInterface $state, TimeInterface $time): bool {
    $ends = static::noticeEndsAt($state);
    return $ends !== NULL && $time->getRequestTime() >= $ends;
  }

  /**
   * Returns the value in effect for a choice, or NULL while undecided.
   */
  public static function effectiveValue(string $key, Config $update_settings, StateInterface $state, TimeInterface $time): ?bool {
    $value = $update_settings->get("data_sharing.$key");
    if ($value !== NULL || !static::noticeEnded($state, $time)) {
      return $value;
    }
    return match ($key) {
      'domain' => static::DEFAULT_DOMAIN,
      'site_mail' => static::DEFAULT_SITE_MAIL,
    };
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
