<?php

namespace Drupal\system\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * Provides a callback for finding a time zone identifier.
 */
class TimezoneController {

  /**
   * Returns a time zone identifier given a time zone abbreviation.
   *
   * @param string $abbreviation
   *   Time zone abbreviation.
   * @param int $offset
   *   Offset from GMT in seconds. Defaults to -1 which means that first found
   *   time zone corresponding to abbreviation is returned. Otherwise exact
   *   offset is searched and only if not found then the first time zone with
   *   any offset is returned.
   * @param null|int $is_daylight_saving_time
   *   Daylight saving time indicator. If abbreviation does not exist then the
   *   time zone is searched solely by offset and is DST.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   The time zone identifier or 'false' in CacheableJsonResponse object.
   */
  public function getTimezone($abbreviation = '', $offset = -1, $is_daylight_saving_time = NULL) {
    $offset = intval($offset);

    // All timezone responses are cacheable based on the route.
    $cacheable_metadata = (new CacheableMetadata())->addCacheContexts(['route']);

    // Out of bounds check for offset. Offset +/- UTC is typically no
    // smaller/larger than -12/+14.
    if ($offset < -60000 || $offset > 60000) {
      return (new CacheableJsonResponse(FALSE))->addCacheableDependency($cacheable_metadata);
    }

    if (isset($is_daylight_saving_time)) {
      $original = intval($is_daylight_saving_time);
      $is_daylight_saving_time = min(1, max(-1, intval($is_daylight_saving_time)));
      // Catch if out of boundary.
      if ($original !== $is_daylight_saving_time) {
        return (new CacheableJsonResponse(FALSE))->addCacheableDependency($cacheable_metadata);
      }
    }
    else {
      // If no daylight saving time indicator was specified, default to -1
      // to indicate that daylight saving was not taken into consideration.
      $is_daylight_saving_time = -1;
    }

    // An abbreviation of "0" passed in the callback arguments should be
    // interpreted as the empty string.
    $abbreviation = $abbreviation ?: '';
    $timezone = timezone_name_from_abbr($abbreviation, $offset, $is_daylight_saving_time);
    return (new CacheableJsonResponse($timezone))->addCacheableDependency($cacheable_metadata);
  }

}
