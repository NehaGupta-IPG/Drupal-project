<?php

declare(strict_types=1);

namespace Drupal\Core\Entity;

/**
 * Provides an interface for entity permission providers.
 */
interface EntityPermissionProviderInterface {

  /**
   * Builds permissions for an entity type.
   *
   * @return array[]
   *   An associative array of permission values, keyed by permission name.
   *
   * @see \Drupal\user\PermissionHandlerInterface::getPermissions()
   */
  public function buildPermissions();

  /**
   * Gets the name of the admin permission.
   *
   * @return string|null
   *   Returns Administrator permission.
   */
  public function getAdminPermission();

  /**
   * Gets the name of the collection permission.
   *
   * This permission is mostly used for providing access to the 'overview' page
   * of an entity type. For other kind of listings, for example one that is
   * provided by a HTTP REST API, the 'view any published' permission should be
   * used instead.
   *
   * @return string|null
   *   Returns Collection Permission.
   */
  public function getCollectionPermission();

  /**
   * Gets the name of the 'view any|own published' permission.
   *
   * @param string $bundle
   *   A bundle name or the entity type ID if the entity type does not have
   *   bundles.
   * @param string $scope
   *   (optional) Whether the 'view any published' or 'view own published'
   *   permission name should be returned. Defaults to 'any'.
   *
   * @return string|null
   *   Returns view permission.
   */
  public function getViewPermission($bundle, $scope = 'any');

  /**
   * Gets the name of the 'view any|own unpublished' permission.
   *
   * Note that only the 'view own unpublished' permission is provided by Drupal
   * core, but contrib can implement support for 'view own unpublished' in a
   * custom permission provider handler.
   *
   * @param string $scope
   *   (optional) Whether the 'view any unpublished' or 'view own unpublished'
   *   permission name should be returned. Defaults to 'own'.
   *
   * @return string|null
   *   Returns view unpublished permission.
   */
  public function getViewUnpublishedPermission($scope = 'own');

  /**
   * Gets the name of the 'edit any|own' permission.
   *
   * @param string $bundle
   *   A bundle name or the entity type ID if the entity type does not have
   *   bundles.
   * @param string $scope
   *   (optional) Whether the 'edit any' or 'edit own' permission name should be
   *   returned. Defaults to 'any'.
   *
   * @return string|null
   *   Returns update permission.
   */
  public function getUpdatePermission($bundle, $scope = 'any');

  /**
   * Gets the name of the 'delete any|own' permission.
   *
   * @param string $bundle
   *   A bundle name or the entity type ID if the entity type does not have
   *   bundles.
   * @param string $scope
   *   (optional) Whether the 'delete any' or 'delete own' permission name
   *   should be returned. Defaults to 'any'.
   *
   * @return string|null
   *   Returns delete permission.
   */
  public function getDeletePermission($bundle, $scope = 'any');

  /**
   * Gets the name of the 'create' permission.
   *
   * @param string $bundle
   *   A bundle name or the entity type ID if the entity type does not have
   *   bundles.
   *
   * @return string|null
   *   Returns create permission.
   */
  public function getCreatePermission($bundle);

}
