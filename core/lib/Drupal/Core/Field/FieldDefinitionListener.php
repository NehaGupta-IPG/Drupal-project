<?php

namespace Drupal\Core\Field;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

/**
 * Reacts to field definition CRUD on behalf of the Entity system.
 */
class FieldDefinitionListener implements FieldDefinitionListenerInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The key-value factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected $keyValueFactory;

  /**
   * Cache backend instance.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs a new FieldDefinitionListener.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Cache\CacheBackendInterface|\Drupal\Core\KeyValueStore\KeyValueFactoryInterface $cache_backend
   *   The cache backend.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, CacheBackendInterface|KeyValueFactoryInterface $cache_backend) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->cacheBackend = $cache_backend;
    if ($cache_backend instanceof KeyValueFactoryInterface) {
      $this->keyValueFactory = $cache_backend;
      $this->cacheBackend = func_get_arg(3);
      @trigger_error('Calling ' . __METHOD__ . '() with the $key_value_factory argument is deprecated in drupal:11.5.0 and is removed from drupal:12.0.0. See https://www.drupal.org/node/3612113', E_USER_DEPRECATED);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldDefinitionCreate(FieldDefinitionInterface $field_definition) {
    $entity_type_id = $field_definition->getTargetEntityTypeId();
    $bundle = $field_definition->getTargetBundle();
    $field_name = $field_definition->getName();

    // Notify the storage about the new field.
    $this->entityTypeManager->getStorage($entity_type_id)->onFieldDefinitionCreate($field_definition);

    // Delete the cache entry.
    $this->cacheBackend->delete('entity_field_map');
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldDefinitionUpdate(FieldDefinitionInterface $field_definition, FieldDefinitionInterface $original) {
    // Notify the storage about the updated field.
    $this->entityTypeManager->getStorage($field_definition->getTargetEntityTypeId())->onFieldDefinitionUpdate($field_definition, $original);
  }

  /**
   * {@inheritdoc}
   */
  public function onFieldDefinitionDelete(FieldDefinitionInterface $field_definition) {
    $entity_type_id = $field_definition->getTargetEntityTypeId();
    $bundle = $field_definition->getTargetBundle();
    $field_name = $field_definition->getName();

    // Notify the storage about the field deletion.
    $this->entityTypeManager->getStorage($entity_type_id)->onFieldDefinitionDelete($field_definition);

    // Delete the cache entry.
    $this->cacheBackend->delete('entity_field_map');
  }

}
