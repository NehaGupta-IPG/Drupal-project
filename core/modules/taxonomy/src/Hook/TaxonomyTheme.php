<?php

namespace Drupal\taxonomy\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Implements hook_theme().
 */
#[Hook('theme')]
class TaxonomyTheme {

  public function __invoke() : array {
    return ['taxonomy_term' => ['render element' => 'elements']];
  }

}
