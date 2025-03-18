<?php

namespace Drupal\file;

/**
 * Enum to specify whether file URL is absolute or relative.
 */
enum FileUrlType: string {

  // Display URL as absolute.
  case Absolute = 'absolute';

  // Display URL as relative.
  case Relative = 'relative';

}
