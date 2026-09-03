<?php

declare(strict_types=1);

namespace Drupal\cache_test\Controller;

use Drupal\Core\Routing\Attribute\Route;
use Drupal\Core\Url;

/**
 * Controller routines for cache_test routes.
 */
class CacheTestController {

  /**
   * Early renders a URL to test bubbleable metadata bubbling.
   */
  #[Route(
    path: '/cache-test/url-bubbling',
    name: 'cache_test.url_bubbling',
    requirements: ['_access' => 'TRUE'],
  )]
  public function urlBubbling() {
    $url = Url::fromRoute('<current>')->setAbsolute();
    return [
      '#markup' => 'This URL is early-rendered: ' . $url->toString() . '. Yet, its bubbleable metadata should be bubbled.',
    ];
  }

  /**
   * Bundle listing tags invalidation.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $bundle
   *   The bundle.
   *
   * @return array
   *   Renderable array.
   */
  #[Route(
    path: '/cache-test-list/{entity_type_id}/{bundle}',
    name: 'cache_test_list.bundle_tags',
    requirements: ['_access' => 'TRUE'],
  )]
  public function bundleTags($entity_type_id, $bundle) {
    $storage = \Drupal::entityTypeManager()->getStorage($entity_type_id);
    $entity_ids = $storage->getQuery()->accessCheck(TRUE)->condition('type', $bundle)->execute();
    $page = [];

    $entities = $storage->loadMultiple($entity_ids);
    foreach ($entities as $entity) {
      $page[$entity->id()] = [
        '#markup' => $entity->label(),
      ];
    }
    $page['#cache']['tags'] = [$entity_type_id . '_list:' . $bundle];
    return $page;
  }

}
