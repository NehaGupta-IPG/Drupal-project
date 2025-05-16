<?php

declare(strict_types=1);

namespace Drupal\comment;

/**
 * Defines the comment mode options.
 */
enum CommentMode: int {

  // Comments are displayed in a flat list - expanded.
  case Flat = 0;

  // Comments are displayed as a threaded list - expanded.
  case Threaded = 1;

}
