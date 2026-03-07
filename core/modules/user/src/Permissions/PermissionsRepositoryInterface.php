<?php

declare(strict_types=1);

namespace Drupal\user\Permissions;

/**
 * Represents a set of permissions (like 'access content').
 * Permissions are organized by provider (e.g., module machine name).
 * In addition to permissions themselves, this object can contain
 * the section that a specific permission might be placed in.
 * For instance, 'access content' might be in a 'users' section,
 * while 'delete any node' might be in an 'admin' section.
 * Each provider can also have a 'main' section with extended help.
 */
interface PermissionsRepositoryInterface {

  /**
   * Adds a permission to the repository.
   *
   * @param string $provider
   *   The provider machine name, such as a module machine name.
   *
   * @param array $permission
   *   The permission data.
   *   It must at least have a 'title' and 'key' keys.
   */
  public function addPermission(string $provider, array $permission): void;

  /**
   * Adds a section to the repository.
   *
   * @param string $provider
   *   The provider machine name, such as a module machine name.
   *
   * @param array $section
   *   The section data.
   *   It must at least have a 'title' key.
   */
  public function addSection(string $provider, array $section): void;

  /**
   * Adds the main data to the repository.
   * This covers all the permissions etc for this provider.
   *
   * @param string $provider
   *   The provider machine name, such as a module machine name.
   *
   * @param array $main
   *   The main data.
   *   It must at least have a 'extended_help' key.
   */
  public function setMain(string $provider, array $main): void;

  /**
   * Sorts the permissions by overall weight.
   * Overall weight = 1000 * section weight (if any) + permission weight.
   */
  public function sortAll(): void;

  /**
   * Returns the providers (e.g., module machine names).
   *
   * @return array
   *   The providers, possibly empty.
   */
  public function getProviders(): array;

  /**
   * Returns an array of all permissions in the repository.
   * Each key of the array is the machine name of a permission.
   *
   * @param PermissionsRepositoryReturnStyle $style
   *   In the permission array, whether the 'section' key should
   *   be the name of the section or the entire section array.
   *
   * @return array
   *   The permissions.
   */
  public function getAllPermissions(PermissionsRepositoryReturnStyle $style = PermissionsRepositoryReturnStyle::SectionArray): array;

  /**
   * Returns an array of permissions data in the repository for the given provider.
   *
   * @param string $provider
   *   The provider (e.g., machine name of a module).
   * @param PermissionsRepositoryReturnStyle $style
   *   In the permission array, whether the 'section' key should
   *   be the name of the section or the entire section array.
   *
   * @return array
   *   The permissions data.
   *   Keys are the data that was parsed and might include 'permissions',
   *   'sections', etc.
   */
  public function getProviderData(string $provider, PermissionsRepositoryReturnStyle $style = PermissionsRepositoryReturnStyle::SectionArray): array;

  /**
   * Returns an array of permissions data in the repository.
   *
   * @param PermissionsRepositoryReturnStyle $style
   *   In the permission array, whether the 'section' key should
   *   be the name of the section or the entire section array.
   *
   * @return array
   *   The permissions data.
   *   Keys are provider names, values are an array with the permissions data
   *   for that provider.
   *   Keys of the inner array are the data that was parsed and might
   *   include 'permissions', 'sections', etc.
   */
  public function getAllData(PermissionsRepositoryReturnStyle $style = PermissionsRepositoryReturnStyle::SectionArray): array;

}
