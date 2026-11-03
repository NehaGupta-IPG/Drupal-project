<?php

declare(strict_types=1);

namespace Drupal\views_test_ajax_page\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for testing Drupal.Views JS behavior.
 */
class ViewsTestAjaxPageController extends ControllerBase {

  /**
   * Provides a page that attaches the views.ajax library.
   *
   * Used to test Drupal.Views.parseQueryString() in the browser, without
   * requiring an actual AJAX view to be rendered.
   *
   * @return array<string, mixed>
   *   The render array.
   */
  public function parseQueryStringPage(): array {
    return [
      '#attached' => [
        'library' => ['views/views.ajax'],
      ],
    ];
  }

}
