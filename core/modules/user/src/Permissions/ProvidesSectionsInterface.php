<?php

declare(strict_types=1);

namespace Drupal\user\Permissions;

/**
 * Defines an interface for classes that provide permissions page sections.
 *
 * @see the user_permissions_repository_test module for an example.
 */
interface ProvidesSectionsInterface {

  /**
   * Return an array of sections, keyed by machine name.
   *
   * @return array<string, array<string, mixed>>
   *   Array of sections.
   */
  public function getSections(): array;

}
