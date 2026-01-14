<?php

declare(strict_types=1);

namespace Drupal\theme_test;

/**
 * Service to test preprocess callbacks with callable resolver.
 */
class ThemeTestPreprocessService {

  /**
   * Preprocess callback for testing service notation in preprocess functions.
   *
   * @param array $variables
   *   An associative array of variables.
   */
  public function preprocessServiceCallback(array &$variables): void {
    $variables['foo'] = 'Preprocessed via service!';
  }

}
