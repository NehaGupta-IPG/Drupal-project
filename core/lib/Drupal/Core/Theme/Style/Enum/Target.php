<?php

declare(strict_types=1);

namespace Drupal\Core\Theme\Style\Enum;

/**
 * Enumeration of the style targets.
 */
enum Target: string {

  // The style can be placed on any tag.
  case Any = 'any';

  // The style can only be placed on the html tag.
  case Html = 'html';

  // The style can only be placed on the body tag.
  case Body = 'body';

  // The style will generate a meta tag.
  case Meta = 'meta';

}
