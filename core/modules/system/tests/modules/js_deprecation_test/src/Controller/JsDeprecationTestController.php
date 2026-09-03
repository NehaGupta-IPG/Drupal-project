<?php

declare(strict_types=1);

namespace Drupal\js_deprecation_test\Controller;

use Drupal\Core\Routing\Attribute\Route;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Test Controller to show message links.
 */
class JsDeprecationTestController {

  /**
   * Renders page that has js_deprecation_test/deprecation library attached.
   *
   * @return array
   *   Render array.
   */
  #[Route(
    path: '/js_deprecation_test',
    name: 'js_deprecation_test.deprecation',
    title: new TranslatableMarkup('JsDeprecationTest'),
    requirements: ['_access' => 'TRUE'],
  )]
  public function jsDeprecationTest() {
    return [
      '#attached' => ['library' => ['js_deprecation_test/deprecation_test']],
    ];
  }

}
