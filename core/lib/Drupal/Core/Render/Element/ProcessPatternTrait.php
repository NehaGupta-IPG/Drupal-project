<?php

namespace Drupal\Core\Render\Element;

/**
 * Process pattern trait.
 */
trait ProcessPatternTrait {

  /**
   * A regular expression without delimiters used to validate #value.
   *
   * @var string
   */
  protected string $pattern;

}
