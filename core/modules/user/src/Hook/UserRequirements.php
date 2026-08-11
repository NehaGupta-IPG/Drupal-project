<?php

declare(strict_types=1);

namespace Drupal\user\Hook;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\user\PermissionHandlerInterface;

/**
 * Requirements for the User module.
 */
class UserRequirements {

  use StringTranslationTrait;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly Connection $connection,
    protected readonly PermissionHandlerInterface $permissionHandler,
    protected readonly AccountProxyInterface $currentUser,
  ) {
  }

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtime(): array {
    $requirements = [];

    $result = (bool) $this->entityTypeManager->getStorage('user')->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', 0)
      ->range(0, 1)
      ->execute();

    if ($result === FALSE) {
      $requirements['anonymous user'] = [
        'title' => $this->t('Anonymous user'),
        'description' => $this->t('The anonymous user does not exist. See the <a href=":url">restore the anonymous (user ID 0) user record</a> for more information', [
          ':url' => 'https://www.drupal.org/node/1029506',
        ]),
        'severity' => RequirementSeverity::Warning,
      ];
    }

    $query = $this->connection->select('users_field_data');
    $query->addExpression('LOWER(mail)', 'lower_mail');
    $query->isNotNull('mail');
    $query->groupBy('lower_mail');
    $query->groupBy('langcode');
    $query->having('COUNT(uid) > :matches', [':matches' => 1]);
    $conflicts = $query->countQuery()->execute()->fetchField();

    if ($conflicts > 0) {
      $requirements['conflicting emails'] = [
        'title' => $this->t('Conflicting user emails'),
        'description' => $this->t('Some user accounts have email addresses that differ only by case. For example, one account might have alice@example.com and another might have Alice@Example.com. See <a href=":url">Conflicting User Emails</a> for more information.', [
          ':url' => 'https://www.drupal.org/node/3486109',
        ]),
        'severity' => RequirementSeverity::Warning,
      ];
    }

    // Load all permissions.
    /** @var array<string, array{title: string, deprecated?: string, ...}> $permissions */
    $permissions = $this->permissionHandler->getPermissions();

    // Find all deprecated permissions.
    $deprecated_permissions = array_filter($permissions, fn (array $permission): bool => !empty($permission['deprecated']));

    $used_deprecated_permissions = [];
    if (count($deprecated_permissions) > 0) {
      $role_storage = $this->entityTypeManager->getStorage('user_role');
      $roles = $role_storage->loadMultiple();

      foreach ($deprecated_permissions as $name => $permission) {
        $permission_in_use = array_any($roles, fn($role) => $role->hasPermission($name));
        if ($permission_in_use) {
          $used_deprecated_permissions[] = $permission['title'];
        }
      }
    }

    if (count($used_deprecated_permissions) > 0) {
      $requirements['deprecated_permissions'] = [
        'title' => $this->t('Deprecated permissions'),
        'severity' => RequirementSeverity::Warning,
      ];

      $requirements['deprecated_permissions']['description'] = $this->t('The following permissions are deprecated but are assigned to one or more roles: %permissions.', ['%permissions' => implode(', ', $used_deprecated_permissions)]);
      if ($this->currentUser->hasPermission('administer permissions')) {
        $requirements['deprecated_permissions']['description'] = $this->t('The following permissions are deprecated but are assigned to one or more roles: %permissions. To unassign these permissions visit the <a href=":url">permissions form</a>.', [
          '%permissions' => implode(', ', $used_deprecated_permissions),
          ':url' => Url::fromRoute('user.admin_permissions')->toString(),
        ]);
      }
    }

    return $requirements;
  }

}
