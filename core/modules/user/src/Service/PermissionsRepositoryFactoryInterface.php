<?php

declare(strict_types=1);

namespace Drupal\user\Service;

use Drupal\user\Permissions\PermissionsRepositoryInterface;

/**
 * Factory for an object that contains the permissions for a site.
 * This contains methods that parse the *.permissions.yml files that
 * many modules contain.
 *
 * One method scans those *.permissions.yml files, the other scans a raw
 * array.
 *
 * Sample usage:
 * @code
 * $factory = \Drupal::service('user_permissions_parser.repository_factory');
 * $permissionsRepository = $factory->createPermissionsRepositoryFromPermissionFiles();
 * $permissions = $permissionsRepository->getAllPermissions();
 * @endcode
 *
 * Afterwards, $permissions will look like:
 * @code
 * [
 *   'delete own nodes' => [
 *     'title' => 'Delete own nodes',
 *     ...
 *   ],
 * ],
 * @endcode
 */
interface PermissionsRepositoryFactoryInterface {

  /**
   * Scan all of the *.permissions.yml files and return a permissions repository.
   *
   * @param bool $legacyOnly
   *   If TRUE, ignore recent additions to permission files, like sections.
   *
   * @return \Drupal\user\Permissions\PermissionsRepositoryInterface
   *   The permissions repository.
   *
   * @see \Drupal\user\Permissions\PermissionsRepositoryInterface
   */
  public function createPermissionsRepositoryFromPermissionFiles($legacyOnly = FALSE): PermissionsRepositoryInterface;

  /**
   * Parse an array and return a permissions repository.
   *
   * @param array $list
   *   The input data.
   *   It should be in the format provider => array of permissions,
   *   sections, etc. such as returned from YamlDiscovery::findAll().
   *
   * @return \Drupal\user\Permissions\PermissionsRepositoryInterface
   *   The permissions repository.
   *
   * @see \Drupal\user\Permissions\PermissionsRepositoryInterface
   */
  public function createPermissionsRepository(array $list);

}
