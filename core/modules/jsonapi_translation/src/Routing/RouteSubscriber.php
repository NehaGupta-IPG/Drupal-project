<?php

namespace Drupal\jsonapi_translation\Routing;

use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alters dynamic routes.
 *
 * @internal JSON:API Translation maintains no PHP API. The API is the HTTP API.
 *   This class may change at any time and could break any dependencies on it.
 *
 * @see https://www.drupal.org/project/drupal/issues/3032787
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * The JSON:API resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * RouteSubscriber constructor.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The JSON:API resource type repository.
   */
  public function __construct(ResourceTypeRepositoryInterface $resource_type_repository) {
    $this->resourceTypeRepository = $resource_type_repository;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // The individual delete route method's signature does not feature all the
    // available parameters. To avoid BC issues, we need to replace the original
    // method with a new one, to get access to all the available parameters,
    // which are required to perform translation deletion.
    foreach ($this->resourceTypeRepository->all() as $resource_type) {
      $delete_route_name = Routes::getRouteName($resource_type, 'individual.delete');
      $route = $collection->get($delete_route_name);
      if ($route) {
        $route->setDefault(RouteObjectInterface::CONTROLLER_NAME, Routes::CONTROLLER_SERVICE_NAME . ':deleteIndividualOrTranslation');
      }
    }
  }

}
