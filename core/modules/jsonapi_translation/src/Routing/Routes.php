<?php

declare(strict_types=1);

namespace Drupal\jsonapi_translation\Routing;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\jsonapi\ParamConverter\ResourceTypeConverter;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi\Routing\Routes as JsonApiRoutes;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Defines dynamic routes.
 *
 * Provides new route definitions for all the translation-specific JSON:API
 * routes. Existing JSON:API routes are overridden by a specialized controller.
 *
 * @internal JSON:API Translation maintains no PHP API. The API is the HTTP API.
 *   This class may change at any time and could break any dependencies on it.
 *
 * @see https://www.drupal.org/project/drupal/issues/3032787
 * @see \Drupal\jsonapi_translation\Controller\EntityResource
 * @see \Drupal\jsonapi_translation\JsonapiTranslationServiceProvider
 */
final class Routes extends JsonApiRoutes {

  /**
   * {@inheritdoc}
   */
  const CONTROLLER_SERVICE_NAME = 'jsonapi_translation.entity_resource';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Instantiates a Routes object.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The JSON:API resource type repository.
   * @param string[] $authentication_providers
   *   The authentication providers, keyed by ID.
   * @param string $jsonapi_base_path
   *   The JSON:API base path.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(ResourceTypeRepositoryInterface $resource_type_repository, array $authentication_providers, string $jsonapi_base_path, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($resource_type_repository, $authentication_providers, $jsonapi_base_path);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): Routes {
    return new static(
      $container->get('jsonapi.resource_type.repository'),
      $container->getParameter('authentication_providers'),
      $container->getParameter('jsonapi.base_path'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function routes(): RouteCollection {
    $routes = new RouteCollection();

    foreach ($this->resourceTypeRepository->all() as $resource_type) {
      $routes->addCollection(static::getIndividualTranslationRoutesForResourceType($resource_type, $this->jsonApiBasePath));
    }

    // Require the JSON:API media type header on every route, except on file
    // upload routes, where we require `application/octet-stream`.
    $routes->addRequirements(['_content_type_format' => 'api_json']);

    // Enable all available authentication providers.
    $routes->addOptions(['_auth' => $this->providerIds]);

    // Flag every route as belonging to the JSON:API module.
    $routes->addDefaults([static::JSON_API_ROUTE_FLAG_KEY => TRUE]);

    // All routes serve only the JSON:API media type.
    $routes->addRequirements(['_format' => 'api_json']);

    return $routes;
  }

  /**
   * Defines translation-specific routes.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The JSON:API resource type for which to get the routes.
   * @param string $path_prefix
   *   The root path prefix.
   *
   * @return \Symfony\Component\Routing\RouteCollection
   *   A collection of routes for the given resource type.
   */
  protected function getIndividualTranslationRoutesForResourceType(ResourceType $resource_type, string $path_prefix): RouteCollection {
    $routes = new RouteCollection();

    if ($resource_type->isInternal() || !$resource_type->isLocatable() || !$resource_type->isMutable() || !$resource_type->isTranslatable()) {
      return $routes;
    }

    $path = $resource_type->getPath();
    $individual_route_path = "{$path}/{entity}";

    $translation_creation_route = new Route($individual_route_path);
    $translation_creation_route->addDefaults([RouteObjectInterface::CONTROLLER_NAME => static::CONTROLLER_SERVICE_NAME . ':createIndividualTranslation']);
    $translation_creation_route->setMethods(['POST']);
    // @todo Allow users with translation permissions and no edit permissions to
    //   handle translations. See https://www.drupal.org/i/3483404.
    $translation_creation_route->setRequirement('_entity_access', 'entity.update');
    $translation_creation_route->setRequirement('_csrf_request_header_token', 'TRUE');
    $routes->add(static::getRouteName($resource_type, 'individual.translation.post'), $translation_creation_route);

    $routes->addOptions(['parameters' => ['entity' => ['type' => 'entity:' . $resource_type->getEntityTypeId()]]]);

    foreach ($routes as $route) {
      static::addRouteParameter($route, static::RESOURCE_TYPE_KEY, ['type' => ResourceTypeConverter::PARAM_TYPE_ID]);
      $route->addDefaults([static::RESOURCE_TYPE_KEY => $resource_type->getTypeName()]);
    }

    $routes->addPrefix($path_prefix);

    return $routes;
  }

}
