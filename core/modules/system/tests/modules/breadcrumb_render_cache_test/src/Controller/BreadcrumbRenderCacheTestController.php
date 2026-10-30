<?php

namespace Drupal\breadcrumb_render_cache_test\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller to Test Breadcrumb Render Cache.
 */
class BreadcrumbRenderCacheTestController extends ControllerBase {

  /**
   * Builds the API response.
   */
  public function getTitle(string $api_id) {
    return $this->t('API Response for : @data', ['@data' => $api_id]);
  }

  /**
   * Builds the response.
   */
  public function build() {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('Example Controller Works!'),
      '#cache' => ['max-age' => 0],
    ];

    return $build;
  }

}
