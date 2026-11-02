<?php

namespace Drupal\jsonapi_write\Routing;

use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\jsonapi\Access\RelationshipRouteAccessCheck;
use Drupal\jsonapi\ParamConverter\ResourceTypeConverter;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\Routing\Routes;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Defines dynamic routes for JSON:API write operations.
 *
 * @internal JSON:API maintains no PHP API since its API is the HTTP API. This
 *   class may change at any time and this will break any dependencies on it.
 *
 * @see https://www.drupal.org/project/drupal/issues/3032787
 * @see jsonapi.api.php
 */
class WriteRoutes extends Routes {

  /**
   * {@inheritdoc}
   */
  public function routes(): RouteCollection {
    $routes = new RouteCollection();
    $upload_routes = new RouteCollection();

    foreach ($this->resourceTypeRepository->all() as $resource_type) {
      $routes->addCollection(static::getWriteRoutesForResourceType($resource_type, $this->jsonApiBasePath));
      $upload_routes->addCollection(static::getFileUploadRoutesForResourceType($resource_type, $this->jsonApiBasePath));
    }

    // Require the JSON:API media type header on every route, except on file
    // upload routes, where we require `application/octet-stream`.
    $routes->addRequirements(['_content_type_format' => 'api_json']);
    $upload_routes->addRequirements(['_content_type_format' => 'bin']);

    $routes->addCollection($upload_routes);

    // Enable all available authentication providers.
    $routes->addOptions(['_auth' => $this->providerIds]);

    // Flag every route as belonging to the JSON:API module.
    $routes->addDefaults([static::JSON_API_ROUTE_FLAG_KEY => TRUE]);

    // All routes serve only the JSON:API media type.
    $routes->addRequirements(['_format' => 'api_json']);

    return $routes;
  }

  /**
   * Gets the write route collection for the given resource type.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The resource type for which the route collection should be created.
   * @param string $path_prefix
   *   The root path prefix.
   *
   * @return \Symfony\Component\Routing\RouteCollection
   *   The route collection.
   */
  protected static function getWriteRoutesForResourceType(ResourceType $resource_type, $path_prefix) {
    $routes = new RouteCollection();

    if ($resource_type->isInternal() || !$resource_type->isMutable() || !$resource_type->isLocatable()) {
      return $routes;
    }

    $path = $resource_type->getPath();
    $entity_type_id = $resource_type->getEntityTypeId();

    // Collection POST route like `/jsonapi/node/article`.
    $collection_create_route = new Route("/{$path}");
    $collection_create_route->addDefaults([RouteObjectInterface::CONTROLLER_NAME => static::CONTROLLER_SERVICE_NAME . ':createIndividual']);
    $collection_create_route->setMethods(['POST']);
    $create_requirement = sprintf("%s:%s", $resource_type->getEntityTypeId(), $resource_type->getBundle());
    $collection_create_route->setRequirement('_entity_create_access', $create_requirement);
    $collection_create_route->setRequirement('_csrf_request_header_token', 'TRUE');
    $routes->add(static::getRouteName($resource_type, 'collection.post'), $collection_create_route);

    // Individual write routes.
    $individual_update_route = new Route("/{$path}/{entity}");
    $individual_update_route->addDefaults([RouteObjectInterface::CONTROLLER_NAME => static::CONTROLLER_SERVICE_NAME . ':patchIndividual']);
    $individual_update_route->setMethods(['PATCH']);
    $individual_update_route->setRequirement('_entity_access', 'entity.update');
    $individual_update_route->setRequirement('_csrf_request_header_token', 'TRUE');
    $routes->add(static::getRouteName($resource_type, 'individual.patch'), $individual_update_route);

    $individual_remove_route = new Route("/{$path}/{entity}");
    $individual_remove_route->addDefaults([RouteObjectInterface::CONTROLLER_NAME => static::CONTROLLER_SERVICE_NAME . ':deleteIndividual']);
    $individual_remove_route->setMethods(['DELETE']);
    $individual_remove_route->setRequirement('_entity_access', 'entity.delete');
    $individual_remove_route->setRequirement('_csrf_request_header_token', 'TRUE');
    $routes->add(static::getRouteName($resource_type, 'individual.delete'), $individual_remove_route);

    // Relationship write routes.
    foreach (array_keys($resource_type->getRelatableResourceTypes()) as $relationship_field_name) {
      $relationship_controller_methods = [
        'POST' => 'addToRelationshipData',
        'PATCH' => 'replaceRelationshipData',
        'DELETE' => 'removeFromRelationshipData',
      ];
      foreach ($relationship_controller_methods as $method => $controller_method) {
        $relationship_route = new Route("/{$path}/{entity}/relationships/{$relationship_field_name}");
        $relationship_route->addDefaults(['_on_relationship' => TRUE]);
        $relationship_route->addDefaults(['related' => $relationship_field_name]);
        $relationship_route->setRequirement('_csrf_request_header_token', 'TRUE');
        $relationship_route->setRequirement(RelationshipRouteAccessCheck::ROUTE_REQUIREMENT_KEY, "$relationship_field_name.edit");
        $relationship_route->addDefaults([RouteObjectInterface::CONTROLLER_NAME => static::CONTROLLER_SERVICE_NAME . ":{$controller_method}"]);
        $relationship_route->setMethods($method);
        $routes->add(static::getRouteName($resource_type, sprintf('%s.relationship.%s', $relationship_field_name, strtolower($method))), $relationship_route);
      }
    }

    // Add entity parameter conversion to every route.
    $routes->addOptions(['parameters' => ['entity' => ['type' => 'entity:' . $entity_type_id]]]);

    // Add the resource type as a parameter to every resource route.
    foreach ($routes as $route) {
      static::addRouteParameter($route, static::RESOURCE_TYPE_KEY, ['type' => ResourceTypeConverter::PARAM_TYPE_ID]);
      $route->addDefaults([static::RESOURCE_TYPE_KEY => $resource_type->getTypeName()]);
    }

    $routes->addPrefix($path_prefix);

    return $routes;
  }

  /**
   * Gets the file upload route collection for the given resource type.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The resource type for which the route collection should be created.
   * @param string $path_prefix
   *   The root path prefix.
   *
   * @return \Symfony\Component\Routing\RouteCollection
   *   The route collection.
   */
  protected static function getFileUploadRoutesForResourceType(ResourceType $resource_type, $path_prefix) {
    $routes = new RouteCollection();

    // Internal resources have no routes; individual routes require locations.
    if ($resource_type->isInternal() || !$resource_type->isLocatable() || !$resource_type->isMutable()) {
      return $routes;
    }

    // File upload routes are only necessary for resource types that have file
    // fields.
    $has_file_field = array_reduce($resource_type->getRelatableResourceTypes(), function ($carry, array $target_resource_types) {
      return $carry || static::hasNonInternalFileTargetResourceTypes($target_resource_types);
    }, FALSE);
    if (!$has_file_field) {
      return $routes;
    }

    $path = $resource_type->getPath();
    $entity_type_id = $resource_type->getEntityTypeId();

    $new_resource_file_upload_route = new Route("/{$path}/{file_field_name}");
    $new_resource_file_upload_route->addDefaults([RouteObjectInterface::CONTROLLER_NAME => 'jsonapi.file_upload:handleFileUploadForNewResource']);
    $new_resource_file_upload_route->setMethods(['POST']);
    $new_resource_file_upload_route->setRequirement('_csrf_request_header_token', 'TRUE');
    $routes->add(static::getFileUploadRouteName($resource_type, 'new_resource'), $new_resource_file_upload_route);

    $existing_resource_file_upload_route = new Route("/{$path}/{entity}/{file_field_name}");
    $existing_resource_file_upload_route->addDefaults([RouteObjectInterface::CONTROLLER_NAME => 'jsonapi.file_upload:handleFileUploadForExistingResource']);
    $existing_resource_file_upload_route->setMethods(['POST']);
    $existing_resource_file_upload_route->setRequirement('_csrf_request_header_token', 'TRUE');
    $routes->add(static::getFileUploadRouteName($resource_type, 'existing_resource'), $existing_resource_file_upload_route);

    // Add entity parameter conversion to every route.
    $routes->addOptions(['parameters' => ['entity' => ['type' => 'entity:' . $entity_type_id]]]);

    // Add the resource type as a parameter to every resource route.
    foreach ($routes as $route) {
      static::addRouteParameter($route, static::RESOURCE_TYPE_KEY, ['type' => ResourceTypeConverter::PARAM_TYPE_ID]);
      $route->addDefaults([static::RESOURCE_TYPE_KEY => $resource_type->getTypeName()]);
    }

    // File upload routes all have the same base path.
    $routes->addPrefix($path_prefix);

    return $routes;
  }

}
