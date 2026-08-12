<?php

declare(strict_types=1);

namespace Drupal\field\Hook;

use Drupal\field\FieldConfigInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\DynamicallyFieldableEntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\field\EntityDisplayRebuilder;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;

/**
 * Entity hook implementations for field.
 */
readonly class FieldEntityHooks {

  public function __construct(
    #[Autowire(service: 'cache.memory')]
    protected readonly CacheBackendInterface $memoryCache,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EntityFieldManagerInterface $entityFieldManager,
    protected readonly ClassResolverInterface $classResolver,
    protected readonly FieldTypePluginManagerInterface $fieldTypeManager,
    protected readonly LoggerChannelFactoryInterface $loggerFactory,
    protected readonly SelectionPluginManagerInterface $entityReferenceSelectionManager,
  ) {}

  /**
   * Implements hook_entity_field_storage_info().
   */
  #[Hook('entity_field_storage_info')]
  public function entityFieldStorageInfo(EntityTypeInterface $entity_type): array {
    if ($this->entityTypeManager->getStorage($entity_type->id()) instanceof DynamicallyFieldableEntityStorageInterface) {
      // Query by filtering on the ID as this is more efficient than filtering
      // on the entity_type property directly.
      $ids = $this->entityTypeManager->getStorage('field_storage_config')->getQuery('AND')->condition('id', $entity_type->id() . '.', 'STARTS_WITH')->execute();
      // Fetch all fields and key them by field name.
      $field_storages = $this->entityTypeManager->getStorage('field_storage_config')->loadMultiple($ids);
      $result = [];
      foreach ($field_storages as $field_storage) {
        $result[$field_storage->getName()] = $field_storage;
      }
      return $result;
    }
    return [];
  }

  /**
   * Implements hook_entity_bundle_field_info().
   */
  #[Hook('entity_bundle_field_info')]
  public function entityBundleFieldInfo(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions): array {
    if ($this->entityTypeManager->getStorage($entity_type->id()) instanceof DynamicallyFieldableEntityStorageInterface) {
      $cid = 'field_bundle_field_info:' . $entity_type->id();
      if ($cached = $this->memoryCache->get($cid)) {
        $fields = $cached->data;
      }
      else {
        $fields = [];
        // Query by filtering on the ID as this is more efficient than filtering
        // on the entity_type property directly.
        $ids = $this->entityTypeManager->getStorage('field_config')->getQuery('AND')->condition('id', $entity_type->id() . '.', 'STARTS_WITH')->execute();
        // Fetch all fields and key them by field name.
        $field_configs = $this->entityTypeManager->getStorage('field_config')->loadMultiple($ids);
        foreach ($field_configs as $field_instance) {
          $fields[$field_instance->getTargetBundle()][] = $field_instance->id();
        }
        $this->memoryCache->set($cid, $fields, Cache::PERMANENT, ['entity_field_info']);
        $field_configs = array_filter($field_configs, static fn(FieldConfigInterface $field_instance) => $field_instance->getTargetBundle() === $bundle);
      }
      if (isset($fields[$bundle])) {
        // Rely on the entity static cache for the field config entity loading
        // so that it's not duplicated, to avoid memory and cache invalidation
        // issues.
        $bundle_fields = [];
        $field_configs ??= $this->entityTypeManager->getStorage('field_config')->loadMultiple($fields[$bundle]);
        foreach ($field_configs as $field_config) {
          $bundle_fields[$field_config->getName()] = $field_config;
        }
        return $bundle_fields;
      }
    }
    return [];
  }

  /**
   * Implements hook_entity_bundle_delete().
   */
  #[Hook('entity_bundle_delete')]
  public function entityBundleDelete($entity_type_id, $bundle): void {
    $storage = $this->entityTypeManager->getStorage('field_config');
    // Get the fields on the bundle.
    $fields = $storage->loadByProperties(['entity_type' => $entity_type_id, 'bundle' => $bundle]);
    // This deletes the data for the field as well as the field themselves. This
    // function actually just marks the data and fields as deleted, leaving the
    // garbage collection for a separate process, because it is not always
    // possible to delete this much data in a single page request (particularly
    // since for some field types, the deletion is more than just a simple
    // DELETE query).
    foreach ($fields as $field) {
      $field->delete();
    }
    // We are duplicating the work done by
    // \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem::onDependencyRemoval()
    // because we need to take into account bundles that are not provided by a
    // config entity type so they are not part of the config dependencies.
    // Gather a list of all entity reference fields.
    $map = $this->entityFieldManager->getFieldMapByFieldType('entity_reference');
    $ids = [];
    foreach ($map as $type => $info) {
      foreach ($info as $name => $data) {
        foreach ($data['bundles'] as $bundle_name) {
          $ids[] = "{$type}.{$bundle_name}.{$name}";
        }
      }
    }
    // Update the 'target_bundles' handler setting if needed.
    $field_configs = $this->entityTypeManager->getStorage('field_config')->loadMultiple($ids);
    foreach ($field_configs as $field_config) {
      if ($field_config->getSetting('target_type') == $entity_type_id) {
        $handler_settings = $field_config->getSetting('handler_settings');
        if (isset($handler_settings['target_bundles'][$bundle])) {
          unset($handler_settings['target_bundles'][$bundle]);
          $field_config->setSetting('handler_settings', $handler_settings);
          $field_config->save();
        }
      }
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_insert() for 'field_config'.
   */
  #[Hook('field_config_insert')]
  public function fieldConfigInsert(FieldConfigInterface $field): void {
    if ($field->isSyncing()) {
      // Don't change anything during a configuration sync.
      return;
    }
    // Allow other view modes to update their configuration for the new field.
    // Otherwise, configuration for view modes won't get updated until the mode
    // is used for the first time, creating noise in config diffs.
    $this->classResolver->getInstanceFromDefinition(EntityDisplayRebuilder::class)->rebuildEntityTypeDisplays($field->getTargetEntityTypeId(), $field->getTargetBundle());
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for 'field_storage_config'.
   *
   * Reset the field handler settings, when the storage target_type is changed
   * on an entity reference field.
   */
  #[Hook('field_storage_config_update')]
  public function fieldStorageConfigUpdate(FieldStorageConfigInterface $field_storage): void {
    if ($field_storage->isSyncing()) {
      // Don't change anything during a configuration sync.
      return;
    }
    // Act on all sub-types of the entity_reference field type.
    $item_class = 'Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem';
    $class = $this->fieldTypeManager->getPluginClass($field_storage->getType());
    if ($class !== $item_class && !is_subclass_of($class, $item_class)) {
      return;
    }
    // If target_type changed, reset the handler in the fields using that
    // storage.
    if ($field_storage->getSetting('target_type') !== $field_storage->getOriginal()->getSetting('target_type')) {
      foreach ($field_storage->getBundles() as $bundle) {
        $id = $field_storage->getTargetEntityTypeId() . '.' . $bundle . '.' . $field_storage->getName();
        $field = $this->entityTypeManager->getStorage('field_config')->load($id);
        // Reset the handler settings. This triggers
        // \Drupal\field\Hook\FieldHooks::fieldConfigPresave(), which will take
        // care of reassigning the handler to the correct derivative for the new
        // target_type.
        $field->setSetting('handler_settings', []);
        $field->save();
      }
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_create() for 'field_config'.
   *
   * Determine the selection handler plugin ID for an entity reference field.
   */
  #[Hook('field_config_create')]
  public function fieldConfigCreate(FieldConfigInterface $field): void {
    if ($field->isSyncing()) {
      return;
    }
    // Act on all sub-types of the entity_reference field type.
    $item_class = 'Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem';
    $class = $this->fieldTypeManager->getPluginClass($field->getType());
    if ($class !== $item_class && !is_subclass_of($class, $item_class)) {
      return;
    }
    // If we don't know the target type yet, there's nothing else we can do.
    $target_type = $field->getFieldStorageDefinition()->getSetting('target_type');
    if (empty($target_type)) {
      return;
    }
    // Make sure the selection handler plugin is the correct derivative for the
    // target entity type.
    [$current_handler] = explode(':', $field->getSetting('handler'), 2);
    $field->setSetting('handler', $this->entityReferenceSelectionManager->getPluginId($target_type, $current_handler));
  }

  /**
   * Implements hook_ENTITY_TYPE_presave() for 'field_config'.
   *
   * Determine the selection handler plugin ID for an entity reference field.
   */
  #[Hook('field_config_presave')]
  public function fieldConfigPresave(FieldConfigInterface $field): void {
    // Don't change anything during a configuration sync.
    if ($field->isSyncing()) {
      return;
    }
    $this->fieldConfigCreate($field);
    // Act on all sub-types of the entity_reference field type.
    $item_class = 'Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem';
    $class = $this->fieldTypeManager->getPluginClass($field->getType());
    if ($class !== $item_class && !is_subclass_of($class, $item_class)) {
      return;
    }
    // In case we removed all the target bundles allowed by the field in
    // EntityReferenceItem::onDependencyRemoval() or
    // \Drupal\field\Hook\FieldHooks::entityBundleDelete() we have to log a
    // critical message because the field will not function correctly anymore.
    $handler_settings = $field->getSetting('handler_settings');
    if (isset($handler_settings['target_bundles']) && $handler_settings['target_bundles'] === []) {
      $this->loggerFactory->get('entity_reference')->critical('The %field_name entity reference field (entity_type: %entity_type, bundle: %bundle) no longer has any valid bundle it can reference. The field is not working correctly anymore and has to be adjusted.', [
        '%field_name' => $field->getName(),
        '%entity_type' => $field->getTargetEntityTypeId(),
        '%bundle' => $field->getTargetBundle(),
      ]);
    }
  }

}
