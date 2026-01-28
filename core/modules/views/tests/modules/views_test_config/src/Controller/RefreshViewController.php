<?php

declare(strict_types=1);

namespace Drupal\views_test_config\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for testing Views AJAX functionality.
 */
class RefreshViewController extends ControllerBase {

  /**
   * AJAX callback to trigger RefreshView on a view.
   *
   * @param string $view_name
   *   The machine name of the view to refresh.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function refreshView(string $view_name): AjaxResponse {
    $response = new AjaxResponse();
    // Build the CSS selector from the view name.
    // Views add a class like .view-{view-name} where underscores become hyphens.
    $selector = '.view-' . Html::cleanCssIdentifier($view_name);
    // Trigger RefreshView on the specified view.
    $response->addCommand(new InvokeCommand($selector, 'trigger', ['RefreshView']));
    return $response;
  }

}
