<?php

declare(strict_types=1);

namespace Drupal\Core\Entity;

use Drupal\user\EntityOwnerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides generic entity permissions for editorial content entity types.
 *
 * Note that the 'view own published' permissions will require caching per user.
 * This may harm performance on most sites and is therefore disabled by default.
 *
 * @see \Drupal\Core\Entity\EntityAccessControlHandler
 */
class EditorialEntityPermissionProvider extends DefaultEntityPermissionProvider {

  /**
   * A placeholder string to use for a bundle name.
   *
   * @var string
   */
  const BUNDLE_NAME_PLACEHOLDER = '__BUNDLE__';

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * Whether per-bundle permissions should be provided.
   *
   * @var bool
   */
  protected $bundleGranularity;

  /**
   * Whether per-bundle permissions should be provided for the 'view' operation.
   *
   * Note that self::$bundleGranularity must also be TRUE in order for this
   * setting to be taken into consideration.
   *
   * @var bool
   */
  protected $viewBundleGranularity = FALSE;

  /**
   * Whether separate 'any|own' permissions should be provided.
   *
   * @var bool
   */
  protected $ownerGranularity;

  /**
   * Whether the 'view own ($bundle) published' permissions should be provided.
   *
   * Important! Note that enabling these permissions will result in caching per
   * user, which might harm performance of sites.
   *
   * @var bool
   */
  protected $viewOwnPublishedPermission = FALSE;

  /**
   * @var array
   */
  protected $permissionMap = [
    'admin' => NULL,
    'collection' => NULL,
    'view any published' => NULL,
    'view own published' => NULL,
    'view own unpublished' => NULL,
    'edit any' => NULL,
    'edit own' => NULL,
    'delete any' => NULL,
    'delete own' => NULL,
    'create' => NULL,
  ];

  /**
   * Constructs a new EditorialEntityPermissionProvider object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    parent::__construct($entity_type);
    $this->entityTypeBundleInfo = $entity_type_bundle_info;

    if (!isset($this->bundleGranularity)) {
      if ($entity_type_permission_granularity = $this->entityType->getPermissionGranularity()) {
        $this->bundleGranularity = $entity_type_permission_granularity === 'bundle' ? TRUE : FALSE;
      }
      else {
        $this->bundleGranularity = $this->entityType->getBundleEntityType() ? TRUE : FALSE;
      }
    }

    if (!isset($this->ownerGranularity)) {
      $this->ownerGranularity = $entity_type->entityClassImplements(EntityOwnerInterface::class);
    }

    if ($this->bundleGranularity) {
      $this->permissionMap = $this->buildPerBundlePermissionMap();
    }
    else {
      $this->permissionMap = $this->buildPerEntityTypePermissionMap();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildPermissions(): array {
    $permissions = [];

    foreach (array_filter($this->permissionMap) as $key => $permission_name) {
      if ($this->bundleGranularity) {
        foreach ($this->entityTypeBundleInfo->getBundleInfo($this->entityType->id()) as $bundle_name => $bundle_info) {
          $permission_name = $this->getPermissionNameForBundle($permission_name, $bundle_name);
          $permissions[$permission_name] = $this->getPermissionInfoForBundle($key, $bundle_info);
        }
      }
      else {
        $permissions[$permission_name] = $this->getPermissionInfo($key);
      }
    }

    return $this->processPermissions($permissions);
  }

  /**
   * Generates the permission names for each supported permission key.
   */
  protected function buildPermissionMap(): array {
    $entity_type_id = $this->entityType->id();
    $permission_map = [];

    $permission_map['admin'] = "administer {$entity_type_id}";

    if ($this->entityType->hasLinkTemplate('collection')) {
      $permission_map['collection'] = "access {$entity_type_id} overview";
    }

    // Don't include the operation name for the widely used 'view any published'
    // permissions.
    $permission_map['view any published'] = "view {$entity_type_id}";

    // Allow entity types that are usually admin-facing only to declare a poorly
    // cacheable 'view own published' permission.
    if ($this->ownerGranularity && $this->viewOwnPublishedPermission) {
      $permission_map['view own published'] = "view own {$entity_type_id}";
    }

    if ($this->ownerGranularity && $this->entityType->entityClassImplements(EntityPublishedInterface::class)) {
      $permission_map['view own unpublished'] = "view own unpublished {$entity_type_id}";
    }

    return $permission_map;
  }

  /**
   * Generates the permission map for the 'entity type' granularity.
   */
  protected function buildPerEntityTypePermissionMap(): array {
    $entity_type_id = $this->entityType->id();
    $permission_map = $this->buildPermissionMap();

    $permission_map['create'] = "create {$entity_type_id}";

    $permission_map['edit any'] = "edit any {$entity_type_id}";
    $permission_map['delete any'] = "delete any {$entity_type_id}";

    if ($this->ownerGranularity) {
      $permission_map['edit own'] = "edit own {$entity_type_id}";
      $permission_map['delete own'] = "delete own {$entity_type_id}";
    }

    return $permission_map;
  }

  /**
   * Generates the permission map for the 'bundle' granularity.
   */
  protected function buildPerBundlePermissionMap(): array {
    $entity_type_id = $this->entityType->id();
    $permission_map = $this->buildPermissionMap();

    if ($this->viewBundleGranularity) {
      $permission_map['view any published'] = sprintf('view %s %s', static::BUNDLE_NAME_PLACEHOLDER, $entity_type_id);

      if ($this->ownerGranularity) {
        $permission_map['view own published'] = sprintf('view own %s %s', static::BUNDLE_NAME_PLACEHOLDER, $entity_type_id);
      }
    }

    $permission_map['create'] = sprintf('create %s %s', static::BUNDLE_NAME_PLACEHOLDER, $entity_type_id);

    $permission_map['edit any'] = sprintf('edit any %s %s', static::BUNDLE_NAME_PLACEHOLDER, $entity_type_id);
    $permission_map['delete any'] = sprintf('delete any %s %s', static::BUNDLE_NAME_PLACEHOLDER, $entity_type_id);

    if ($this->ownerGranularity) {
      $permission_map['edit own'] = sprintf('edit own %s %s', static::BUNDLE_NAME_PLACEHOLDER, $entity_type_id);
      $permission_map['delete own'] = sprintf('delete own %s %s', static::BUNDLE_NAME_PLACEHOLDER, $entity_type_id);
    }

    return $permission_map;
  }

  /**
   * Returns the permission values (e.g. title, etc.) for a permission key.
   *
   * @param string $permission_key
   *   A permission key as defined in self::$permissionMap.
   *
   * @return array
   *   An array of permission values.
   */
  protected function getPermissionInfo($permission_key): array {
    $singular_label = $this->entityType->getSingularLabel();
    $plural_label = $this->entityType->getPluralLabel();

    $info = [];
    switch ($permission_key) {
      case 'admin':
        $info = [
          'title' => $this->t('Administer @type', ['@type' => $plural_label]),
          'restrict access' => TRUE,
        ];
        break;

      case 'collection':
        $info = [
          'title' => $this->t('Access the @type overview page', ['@type' => $plural_label]),
        ];
        break;

      case 'view any published':
        $info = [
          'title' => $this->t('View @type', ['@type' => $plural_label]),
        ];
        break;

      case 'view own published':
        $info = [
          'title' => $this->t('View own @type', ['@type' => $plural_label]),
        ];
        break;

      case 'view own unpublished':
        $info = [
          'title' => $this->t('View own unpublished @type', ['@type' => $plural_label]),
        ];
        break;

      case 'create':
        $info = [
          'title' => $this->t('Create @type', ['@type' => $plural_label]),
        ];
        break;

      case 'edit any':
        $info = [
          'title' => $this->t('Edit any @type', ['@type' => $singular_label]),
        ];
        break;

      case 'edit own':
        $info = [
          'title' => $this->t('Edit own @type', ['@type' => $plural_label]),
        ];
        break;

      case 'delete any':
        $info = [
          'title' => $this->t('Delete any @type', ['@type' => $singular_label]),
        ];
        break;

      case 'delete own':
        $info = [
          'title' => $this->t('Delete own @type', ['@type' => $plural_label]),
        ];
        break;

      default:
        break;
    }

    return $info;
  }

  /**
   * Returns the permission values (e.g. title) for a per-bundle permission key.
   *
   * @param string $permission_key
   *   A permission key as defined in self::$perBundlePermissionMap.
   * @param array $bundle_info
   *   An array of bundle information, as defined by
   *   \Drupal\Core\Entity\EntityTypeBundleInfoInterface::getBundleInfo().
   *
   * @return array
   *   An array of permission values.
   */
  protected function getPermissionInfoForBundle($permission_key, array $bundle_info): array {
    $singular_label = $this->entityType->getSingularLabel();
    $plural_label = $this->entityType->getPluralLabel();

    $info = [];
    switch ($permission_key) {
      case 'view any published':
        $info = [
          'title' => $this->t('%bundle_name: View any @type', [
            '%bundle_name' => $bundle_info['label'],
            '@type' => $plural_label,
          ]),
        ];
        break;

      case 'view own published':
        $info = [
          'title' => $this->t('%bundle_name: View own @type', [
            '%bundle_name' => $bundle_info['label'],
            '@type' => $plural_label,
          ]),
        ];
        break;

      case 'create':
        $info = [
          'title' => $this->t('%bundle_name: Create @type', [
            '%bundle_name' => $bundle_info['label'],
            '@type' => $plural_label,
          ]),
        ];
        break;

      case 'edit any':
        $info = [
          'title' => $this->t('%bundle_name: Edit any @type', [
            '%bundle_name' => $bundle_info['label'],
            '@type' => $singular_label,
          ]),
        ];
        break;

      case 'edit own':
        $info = [
          'title' => $this->t('%bundle_name: Edit own @type', [
            '%bundle_name' => $bundle_info['label'],
            '@type' => $plural_label,
          ]),
        ];
        break;

      case 'delete any':
        $info = [
          'title' => $this->t('%bundle_name: Delete any @type', [
            '%bundle_name' => $bundle_info['label'],
            '@type' => $singular_label,
          ]),
        ];
        break;

      case 'delete own':
        $info = [
          'title' => $this->t('%bundle_name: Delete own @type', [
            '%bundle_name' => $bundle_info['label'],
            '@type' => $plural_label,
          ]),
        ];
        break;

      default:
        break;
    }

    return $info;
  }

  /**
   * {@inheritdoc}
   */
  public function getAdminPermission() {
    return $this->permissionMap['admin'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionPermission() {
    return $this->permissionMap['collection'];
  }

  /**
   * {@inheritdoc}
   */
  public function getViewPermission($bundle, $scope = 'any') {
    assert(in_array($scope, ['any', 'own'], TRUE));
    if ($this->bundleGranularity && $this->viewBundleGranularity) {
      return $this->getPermissionNameForBundle("view $scope published", $bundle);
    }

    return $this->permissionMap["view $scope published"];
  }

  /**
   * {@inheritdoc}
   */
  public function getViewUnpublishedPermission($scope = 'own') {
    assert($scope === 'own');
    return $this->permissionMap["view $scope unpublished"];
  }

  /**
   * {@inheritdoc}
   */
  public function getUpdatePermission($bundle, $scope = 'any') {
    assert(in_array($scope, ['any', 'own'], TRUE));
    if ($this->bundleGranularity) {
      return $this->getPermissionNameForBundle("edit $scope", $bundle);
    }

    return $this->permissionMap["edit $scope"];
  }

  /**
   * {@inheritdoc}
   */
  public function getDeletePermission($bundle, $scope = 'any') {
    if ($this->bundleGranularity) {
      return $this->getPermissionNameForBundle("delete $scope", $bundle);
    }

    return $this->permissionMap["delete $scope"];

  }

  /**
   * {@inheritdoc}
   */
  public function getCreatePermission($bundle) {
    if ($this->bundleGranularity) {
      return $this->getPermissionNameForBundle($this->permissionMap['create'], $bundle);
    }

    return $this->permissionMap['create'];
  }

  /**
   * Returns the name of a bundle-specific permission.
   *
   * @param string $permission_name
   *   The name of the permission.
   * @param string $bundle_name
   *   The name of the bundle.
   *
   * @return string
   *   The bundle-specific permission
   */
  protected function getPermissionNameForBundle($permission_name, $bundle_name) {
    assert($bundle_name !== NULL);
    return str_replace(static::BUNDLE_NAME_PLACEHOLDER, $bundle_name, $permission_name);
  }

}
