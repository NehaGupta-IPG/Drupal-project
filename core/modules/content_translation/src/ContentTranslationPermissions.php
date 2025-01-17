<?php

namespace Drupal\content_translation;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides dynamic permissions for the content_translation module.
 */
class ContentTranslationPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The content translation manager.
   *
   * @var \Drupal\content_translation\ContentTranslationManagerInterface
   */
  protected $contentTranslationManager;

  /**
   * Constructs a ContentTranslationPermissions instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\content_translation\ContentTranslationManagerInterface $content_translation_manager
   *   The content translation manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ContentTranslationManagerInterface $content_translation_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    $this->entityTypeManager = $entity_type_manager;
    $this->contentTranslationManager = $content_translation_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('content_translation.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * Returns an array of content translation permissions.
   *
   * @return array
   *   An associative array of permissions keyed by permission name.
   */
  public function contentPermissions() {
    $permissions = [];
    // Create a translate permission for each enabled entity type and
    // (optionally) bundle.
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($permission_granularity = $entity_type->getPermissionGranularity()) {
        switch ($permission_granularity) {
          case 'bundle':
            foreach ($this->entityTypeBundleInfo->getBundleInfo($entity_type_id) as $bundle => $bundle_info) {
              if ($this->contentTranslationManager->isEnabled($entity_type_id, $bundle)) {
                $permissions[static::permissionKey($entity_type, $bundle)] = $this->buildBundlePermission($entity_type, $bundle, $bundle_info);
              }
            }
            break;

          case 'entity_type':
            if ($this->contentTranslationManager->isEnabled($entity_type_id)) {
              $permissions[static::permissionKey($entity_type)] = [
                'title' => $this->t('Translate @entity_label', ['@entity_label' => $entity_type->getSingularLabel()]),
                'dependencies' => ['module' => [$entity_type->getProvider()]],
              ];
            }
            break;
        }
      }
    }

    return $permissions;
  }

  /**
   * Gets the permission key for an entity type and bundle.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type to get the permission for.
   * @param string|null $bundle
   *   The bundle to get the permission for.
   *
   * @return string|null
   *   The permission name.
   */
  public static function permissionKey(EntityTypeInterface $entity_type, ?string $bundle = NULL): ?string {
    return match ($entity_type->getPermissionGranularity()) {
      'bundle' => $bundle ? "translate $bundle " . $entity_type->id() : NULL,
      'entity_type' => "translate " . $entity_type->id(),
      default => NULL,
    };
  }

  /**
   * Builds a content translation permission array for a bundle.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle to build the translation permission for.
   * @param array $bundle_info
   *   The bundle info.
   *
   * @return array
   *   The permission details, keyed by 'title' and 'dependencies'.
   */
  private function buildBundlePermission(EntityTypeInterface $entity_type, string $bundle, array $bundle_info) {
    $permission = [
      'title' => $this->t('Translate %bundle_label @entity_label', [
        '@entity_label' => $entity_type->getSingularLabel(),
        '%bundle_label' => $bundle_info['label'] ?? $bundle,
      ]),
    ];

    // If the entity type uses bundle entities, add a dependency on the bundle.
    $bundle_entity_type = $entity_type->getBundleEntityType();
    if ($bundle_entity_type && $bundle_entity = $this->entityTypeManager->getStorage($bundle_entity_type)->load($bundle)) {
      $permission['dependencies'][$bundle_entity->getConfigDependencyKey()][] = $bundle_entity->getConfigDependencyName();
    }
    else {
      $permission['dependencies']['module'][] = $entity_type->getProvider();
    }

    $config = $this->entityTypeManager->getStorage('language_content_settings')->load($entity_type->id() . '.' . $bundle);
    if ($config) {
      $permission['dependencies'][$config->getConfigDependencyKey()][] = $config->getConfigDependencyName();
    }
    return $permission;
  }

}
