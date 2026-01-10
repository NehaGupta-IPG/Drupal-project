<?php

namespace Drupal\Core\Session;

/**
 * Represents a single entry for the calculated permissions.
 *
 * @see \Drupal\Core\Session\ChainPermissionCalculator
 */
class CalculatedPermissionsItem implements CalculatedPermissionsItemInterface {

  public function __construct(
    protected array $permissions,
    protected bool $isAdmin = FALSE,
    protected string $scope = AccessPolicyInterface::SCOPE_DRUPAL,
    protected string|int $identifier = AccessPolicyInterface::SCOPE_DRUPAL,
  ) {
    $this->permissions = $isAdmin ? [] : array_unique($permissions);
  }

  /**
   * {@inheritdoc}
   */
  public function getScope(): string {
    return $this->scope;
  }

  /**
   * {@inheritdoc}
   */
  public function getIdentifier(): string|int {
    return $this->identifier;
  }

  /**
   * {@inheritdoc}
   */
  public function getPermissions(): array {
    return $this->permissions;
  }

  /**
   * {@inheritdoc}
   */
  public function isAdmin(): bool {
    return $this->isAdmin;
  }

  /**
   * {@inheritdoc}
   */
  public function hasPermission(string $permission): bool {
    return $this->isAdmin() || in_array($permission, $this->permissions, TRUE);
  }

}
