<?php

declare(strict_types=1);

namespace Drupal\breadcrumb_render_cache_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Controller to Test Breadcrumb Render Cache.
 */
class BreadcrumbRenderCacheTestController extends ControllerBase {

  /**
   * Builds the API response.
   */
  public function getTitle(string $api_id): TranslatableMarkup {
    return $this->t('API Response for : @data', ['@data' => $api_id]);
  }

  /**
   * Builds the response.
   */
  public function build(): array {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('Example Controller Works!'),
      '#cache' => ['max-age' => 0],
    ];

    return $build;
  }

}
