<?php

declare(strict_types=1);

namespace Drupal\module_test_oop_preprocess\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for module_test_oop_preprocess.
 */
class ModuleTestOopPreprocessThemeHooks {

  /**
   * Implements hook_preprocess().
   *
   * This implementation is used to test that an OOP preprocess hook with a
   * prefix is executed.
   */
  #[Hook('preprocess')]
  public function rootPreprocess($arg): mixed {
    return $arg;
  }

  /**
   * Implements hook_preprocess_HOOK().
   *
   * This implementation is used to test that an OOP preprocess hook with a
   * prefix and suffix is executed.
   */
  #[Hook('preprocess_test')]
  public function preprocessTest($arg): mixed {
    return $arg;
  }

}
