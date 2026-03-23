<?php

namespace Drupal\user\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Event fired to filter the permissions list.
 */
class PermissionsListFilterEvent extends Event {

  /**
   * The permissions to filter.
   *
   * @var array
   */
  protected $permissions;

  /**
   * Constructs a permissions list filter event object.
   *
   * @param array $permissions
   *   The permissions to filter.
   */
  public function __construct(array $permissions) {
    $this->permissions = $permissions;
  }

  /**
   * Filters the permissions with a provided callback function.
   *
   * @param callable $callback
   *   The filter callback.
   *
   *   This is a callback used by array_filter and applied to a permissions
   *   array. The ARRAY_FILTER_USE_BOTH option is used, so the function
   *   is effectively `callable(array, string): bool`.
   *
   *    @code
   *   // Example:
   *   function filterDeleteAndBlockContent($permission_array, $permission_name) {
   *     // Remove any permission with a name that starts with 'delete'
   *     if (strpos($permission_name, 'delete') === 0) {
   *       return FALSE;
   *     }
   *     // Remove any permission from the Block Content module.
   *     if ($permission_array['provider'] === 'block_content) {
   *       return FALSE;
   *     }
   *     return TRUE;
   *   }
   *
   *   $permission_list_filter_event->filter('filterDeleteAndBlockContent');
   *
   * @endcode
   */
  public function filter(callable $callback): void {
    $this->permissions = array_filter($this->permissions, $callback, ARRAY_FILTER_USE_BOTH);
  }

  /**
   * Gets the available permissions.
   *
   * @return array
   *   The available permissions.
   */
  public function getPermissions(): array {
    return $this->permissions;
  }

}
