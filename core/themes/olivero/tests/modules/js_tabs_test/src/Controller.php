<?php

declare(strict_types=1);

namespace Drupal\js_tabs_test;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller to tabs.js tests.
 */
class Controller extends ControllerBase {

  /**
   * Returns an empty page.
   */
  public function jsTabsTest(): array {
    return [];
  }

}
