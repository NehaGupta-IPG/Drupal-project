<?php

namespace Drupal\mongodb\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\LanguageFilter as CoreLanguageFilter;

/**
 * Overriding the views filter plugin "language".
 */
class LanguageFilter extends CoreLanguageFilter {

  use InOperatorTrait;

}
