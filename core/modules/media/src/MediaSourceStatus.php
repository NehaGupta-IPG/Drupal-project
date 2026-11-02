<?php

declare(strict_types=1);

namespace Drupal\media;

/**
 * Whether a media item's source can be read before refreshing from it.
 *
 * @internal
 */
enum MediaSourceStatus {

  // The source can be read, so a refresh is safe.
  case Available;

  // The local source file, or the source field value, is missing.
  case Missing;

  // The remote source could not be reached.
  case Unavailable;

}
