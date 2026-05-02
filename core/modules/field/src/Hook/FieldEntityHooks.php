<?php

namespace Drupal\field\Hook;

use Drupal\field\FieldConfigInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\DynamicallyFieldableEntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Entity hook implementations for field.
 */
class FieldEntityHooks {

  public function __construct(
    #[Autowire(service: 'cache.memory')]
    protected readonly CacheBackendInterface $memoryCache,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EntityFieldManagerInterface $entityFieldManager,
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
      $field_storages = FieldStorageConfig::loadMultiple($ids);
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
        $field_configs = FieldConfig::loadMultiple($ids);
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
        $field_configs ??= FieldConfig::loadMultiple($fields[$bundle]);
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
    foreach (FieldConfig::loadMultiple($ids) as $field_config) {
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

}
