<?php

declare(strict_types=1);

namespace Drupal\js_errors_test\Controller;

use Drupal\Core\Routing\Attribute\Route;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Test Controller loading js_errors_test/errors_test library.
 */
class JsErrorsTestController {

  /**
   * Renders page that has js_errors_test/errors_test library attached.
   *
   * @return string[][]
   *   Render array.
   */
  #[Route(
    path: '/js_errors_test',
    name: 'js_errors_test.errors',
    title: new TranslatableMarkup('JsErrorsTest'),
    requirements: ['_access' => 'TRUE'],
  )]
  public function jsErrorsTest(): array {
    return [
      '#attached' => ['library' => ['js_errors_test/errors_test']],
    ];
  }

  /**
   * Renders page that has js_errors_test/errors_async_test library attached.
   *
   * @return string[][]
   *   Render array.
   */
  #[Route(
    path: '/js_errors_async_test',
    name: 'js_errors_async_test.errors',
    title: new TranslatableMarkup('JsErrorsAsyncTest'),
    requirements: ['_access' => 'TRUE'],
  )]
  public function jsErrorsAsyncTest(): array {
    return [
      '#attached' => ['library' => ['js_errors_test/errors_async_test']],
    ];
  }

}
