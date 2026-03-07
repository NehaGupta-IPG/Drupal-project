<?php

declare(strict_types = 1);

namespace Drupal\user;

use Drupal\user\Permissions\PermissionsRepositoryInterface;

/**
 * Interface for permissions parser plugins.
 */
interface PermissionsParserInterface {

  /**
   * Parse the array for permissions we know about.
   *
   * If we parse something, the subarray should be marked as processed
   * by setting self::PROCESSED_KEY to TRUE in the subarray.
   *
   * For instance, if this plugin looks for the 'main' subarray and has
   * parsed it, we should set the _processed flag before returning.
   * That way we know not to parse it again:
   *
   * 'main' => [
   *   'extended_help' => '...',
   *   self::PROCESSED_KEY => TRUE,
   * ],
   *
   * @param array $ary
   *   The permissions data to be parsed.
   * @param string $provider
   *   The provider of the permissions data, most likely the module name.
   * @param PermissionsRepositoryInterface $permissionObj
   *   The object where the parsed data should be stored.
   *
   * @return int
   *   The number of items that were parsed.
   */
  public function parse(array &$ary, string $provider, PermissionsRepositoryInterface $permissionObj): int;

  /**
   * Returns the translated plugin label.
   *
   * @return string
   *   The label.
   */
  public function label(): string;

  /**
   * Returns the translated plugin description.
   *
   * @return string
   *   The description.
   */
  public function description(): string;


}
