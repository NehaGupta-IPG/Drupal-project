<?php

declare(strict_types=1);

namespace Drupal\editor_test\EditorXssFilter;

use Drupal\filter\FilterFormatInterface;
use Drupal\editor\EditorXssFilterInterface;

/**
 * Defines an insecure text editor XSS filter (for testing purposes).
 */
class Insecure implements EditorXssFilterInterface {

  /**
   * {@inheritdoc}
   */
  public function filterXss(string $html, FilterFormatInterface $format, ?FilterFormatInterface $original_format = NULL): string {
    return $html;
  }

}
