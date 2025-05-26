<?php

namespace Drupal\search\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\search\SearchPageRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides local tasks for each search page.
 */
class SearchLocalTask extends DeriverBase implements ContainerDeriverInterface {

  /**
   * Constructs a new SearchLocalTask.
   *
   * @param \Drupal\search\SearchPageRepositoryInterface $searchPageRepository
   *   The search page repository.
   */
  public function __construct(protected SearchPageRepositoryInterface $searchPageRepository) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('search.search_page_repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = [];

    if ($this->searchPageRepository->getDefaultSearchPage()) {
      $active_search_pages = $this->searchPageRepository->getActiveSearchPages();
      foreach ($this->searchPageRepository->sortSearchPages($active_search_pages) as $entity_id => $entity) {
        $this->derivatives[$entity_id] = [
          'title' => $entity->label(),
          'route_name' => 'search.view_' . $entity_id,
          'base_route' => 'search.view',
          'weight' => $entity->getWeight(),
        ];
      }
    }
    return $this->derivatives;
  }

}
