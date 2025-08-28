<?php

declare(strict_types=1);

namespace Drupal\Core\Entity;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a permission provider containing only the 'administer' permission.
 *
 * @see \Drupal\Core\Entity\EntityAccessControlHandler
 */
class DefaultEntityPermissionProvider implements EntityPermissionProviderInterface, EntityHandlerInterface {

  use StringTranslationTrait;

  /**
   * Information about the entity type.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityType;

  /**
   * Constructs a new DefaultEntityPermissionProvider object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   */
  public function __construct(EntityTypeInterface $entity_type) {
    $this->entityType = $entity_type;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildPermissions() {
    $permissions = [];

    if ($this->getAdminPermission()) {
      $permissions = [
        $this->getAdminPermission() => [
          'title' => $this->t('Administer @type', ['@type' => $this->entityType->getPluralLabel()]),
          'description' => $this->t('Maintain all the @type available.', ['@type' => $this->entityType->getPluralLabel()]),
          'restrict access' => TRUE,
        ],
      ];
    }

    return $this->processPermissions($permissions);
  }

  /**
   * {@inheritdoc}
   */
  public function getAdminPermission() {
    return $this->entityType->getAdminPermission();
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionPermission() {
  }

  /**
   * {@inheritdoc}
   */
  public function getViewPermission($bundle, $scope = 'any') {
  }

  /**
   * {@inheritdoc}
   */
  public function getViewUnpublishedPermission($scope = 'own') {
  }

  /**
   * {@inheritdoc}
   */
  public function getUpdatePermission($bundle, $scope = 'any') {
  }

  /**
   * {@inheritdoc}
   */
  public function getDeletePermission($bundle, $scope = 'any') {
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatePermission($bundle) {
  }

  /**
   * Adds the provider and converts the titles to strings to allow sorting.
   *
   * @param array $permissions
   *   The array of permissions
   *
   * @return array
   *   An array of processed permissions.
   */
  protected function processPermissions(array $permissions) {
    foreach ($permissions as $name => $permission) {
      // Permissions are grouped by provider on admin/people/permissions.
      $permissions[$name]['provider'] = $this->entityType->getProvider();
      // TranslatableMarkup objects don't sort properly.
      $permissions[$name]['title'] = $permission['title'];
    }
    return $permissions;
  }

}
