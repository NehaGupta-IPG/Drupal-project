<?php

declare(strict_types=1);

namespace Drupal\Core\Session;

use Drupal\Core\Cache\CacheOptionalInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Grants permissions based on a user's roles.
 */
final class UserRolesAccessPolicy extends AccessPolicyBase implements CacheOptionalInterface {

  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritdoc}
   */
  public function calculatePermissions(AccountInterface $account, string $scope): RefinableCalculatedPermissionsInterface {
    $calculated_permissions = parent::calculatePermissions($account, $scope);

    /** @var \Drupal\user\RoleInterface[] $user_roles */
    $user_roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple($account->getRoles());

    foreach ($user_roles as $user_role) {
      $calculated_permissions
        ->addItem(new CalculatedPermissionsItem($user_role->getPermissions(), $user_role->isAdmin()))
        ->addCacheTags($user_role->getCacheTags())
        ->mergeCacheMaxAge($user_role->getCacheMaxAge())
        // Filter out language cache contexts as they do not affect which
        // permissions a role has.
        ->addCacheContexts(array_filter($user_role->getCacheContexts(), fn(string $context) => !str_starts_with($context, 'languages:')));
    }

    return $calculated_permissions;
  }

  /**
   * {@inheritdoc}
   */
  public function getPersistentCacheContexts(): array {
    return ['user.roles'];
  }

}
