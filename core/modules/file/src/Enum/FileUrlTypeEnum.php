<?php

namespace Drupal\file\Enum;

/**
 * Enum to specify whether file URL is absolute or relative.
 */
enum FileUrlTypeEnum: string {

  /**
   * Display URL as an absolute URL.
   */
  case ABSOLUTE_URL = 'absolute';

  /**
   * Display URL as a relative URL.
   */
  case RELATIVE_URL = 'relative';

}
