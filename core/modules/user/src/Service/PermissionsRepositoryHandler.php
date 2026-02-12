<?php

declare(strict_types=1);

namespace Drupal\user\Service;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\PermissionHandlerInterface;
use Drupal\user\Permissions\PermissionsRepositoryReturnStyle;

/**
 * Implementation of the PermissionHandlerInterface interface.
 *
 * @see \Drupal\user\PermissionHandlerInterface
 */
class PermissionsRepositoryHandler implements PermissionHandlerInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new PermissionsRepositoryHandler.
   *
   * @param \Drupal\user\Service\PermissionsRepositoryFactoryInterface $permissionsRepositoryFactory
   *   The permissions repository factory.
   */
  public function __construct(
    protected readonly PermissionsRepositoryFactoryInterface $permissionsRepositoryFactory,
    ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getPermissions() {
    $permissionObj = $this->permissionsRepositoryFactory->createPermissionsRepositoryFromPermissionFiles();

    return $permissionObj->getAllPermissions(PermissionsRepositoryReturnStyle::SectionArray);
  }

  /**
   * {@inheritdoc}
   */
  public function moduleProvidesPermissions($module_name) {
    // @todo Static cache this information.
    //   https://www.drupal.org/node/2339487
    $permissions = $this->getPermissions();

    foreach ($permissions as $permission) {
      if ($permission['provider'] == $module_name) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
