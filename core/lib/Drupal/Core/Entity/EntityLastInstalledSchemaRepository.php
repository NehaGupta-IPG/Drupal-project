<?php

namespace Drupal\Core\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

/**
 * Provides a repository for installed entity definitions.
 */
class EntityLastInstalledSchemaRepository implements EntityLastInstalledSchemaRepositoryInterface {

  /**
   * The key-value factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected $keyValueFactory;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * The loaded installed entity type definitions.
   *
   * @var array|null
   */
  protected $entityTypeDefinitions = NULL;

  /**
   * All entity type definitions.
   *
   * @var array|null
   */
  protected ?array $allEntityTypeDefinitions = NULL;

  /**
   * The loaded installed field storage definitions.
   *
   * @var array|null
   */
  protected $entityFieldStorageDefinitions = NULL;

  /**
   * The loaded installed field storage definitions.
   *
   * @var array|null
   */
  protected ?array $allEntityFieldStorageDefinitions = NULL;

  /**
   * Constructs a new EntityLastInstalledSchemaRepository.
   *
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory
   *   The key-value factory.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   */
  public function __construct(KeyValueFactoryInterface $key_value_factory, CacheBackendInterface $cache) {
    $this->keyValueFactory = $key_value_factory;
    $this->cacheBackend = $cache;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastInstalledDefinition($entity_type_id) {
    $cid = $entity_type_id . '.entity_type_definitions.installed';
    if (isset($this->entityTypeDefinitions[$entity_type_id])) {
      return $this->entityTypeDefinitions[$entity_type_id];
    }
    elseif ($cache = $this->cacheBackend->get($cid)) {
      $this->entityTypeDefinitions[$entity_type_id] = $cache->data;
      return $this->entityTypeDefinitions[$entity_type_id];
    }

    // Load all entity schema definitions to avoid having to load individual
    // ones later.
    $this->getLastInstalledDefinitions();
    // An entity type ID might be requested which is not yet installed.
    $definition = $this->allEntityTypeDefinitions[$entity_type_id] ?? [];
    $this->entityTypeDefinitions[$entity_type_id] = $definition;
    $this->cacheBackend->set($cid, $definition);
    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastInstalledDefinitions() {
    if (isset($this->allEntityTypeDefinitions)) {
      return $this->allEntityTypeDefinitions;
    }
    $all_definitions = $this->keyValueFactory->get('entity.definitions.installed')->getAll();

    // Filter out field storage definitions.
    $filtered_keys = array_filter(array_keys($all_definitions), function ($key) {
        return str_ends_with($key, '.entity_type');
    });
    $entity_type_definitions = array_intersect_key($all_definitions, array_flip($filtered_keys));

    // Ensure that the returned array is keyed by the entity type ID.
    $keys = array_keys($entity_type_definitions);
    $keys = array_map(function ($key) {
      $parts = explode('.', $key);
      return $parts[0];
    }, $keys);

    // Prime the last installed field storage definitions too in case they are
    // requested later.
    foreach ($keys as $key) {
      $this->allEntityFieldStorageDefinitions[$key] = $all_definitions[$key . '.field_storage_definitions'] ?? [];
    }

    $this->allEntityTypeDefinitions = array_combine($keys, $entity_type_definitions);
    return $this->allEntityTypeDefinitions;
  }

  /**
   * {@inheritdoc}
   */
  public function setLastInstalledDefinition(EntityTypeInterface $entity_type) {
    $entity_type_id = $entity_type->id();
    $this->keyValueFactory->get('entity.definitions.installed')->set($entity_type_id . '.entity_type', $entity_type);
    $this->cacheBackend->set($entity_type_id . '.entity_type_definitions.installed', $entity_type);
    $this->entityTypeDefinitions[$entity_type_id] = $entity_type;
    $this->allEntityTypeDefinitions = NULL;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteLastInstalledDefinition($entity_type_id) {
    $this->keyValueFactory->get('entity.definitions.installed')->delete($entity_type_id . '.entity_type');
    // Clean up field storage definitions as well. Even if the entity type
    // isn't currently fieldable, there might be legacy definitions or an
    // empty array stored from when it was.
    $this->keyValueFactory->get('entity.definitions.installed')->delete($entity_type_id . '.field_storage_definitions');
    $this->cacheBackend->deleteMultiple([
      $entity_type_id . '.entity_type_definitions.installed',
      $entity_type_id . '.field_storage_definitions.installed',
    ]);
    unset($this->entityTypeDefinitions[$entity_type_id]);
    $this->allEntityTypeDefinitions = NULL;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastInstalledFieldStorageDefinitions($entity_type_id) {
    if (isset($this->entityFieldStorageDefinitions[$entity_type_id])) {
      return $this->entityFieldStorageDefinitions[$entity_type_id];
    }
    if ($cache = $this->cacheBackend->get($entity_type_id . '.field_storage_definitions.installed')) {
      $this->entityFieldStorageDefinitions[$entity_type_id] = $cache->data;
      return $cache->data;
    }
    $this->getLastInstalledDefinitions();
    if (isset($this->allEntityFieldStorageDefinitions[$entity_type_id])) {
      $this->entityFieldStorageDefinitions[$entity_type_id] = $this->allEntityFieldStorageDefinitions[$entity_type_id];
      $this->cacheBackend->set($entity_type_id . '.field_storage_definitions.installed', $this->entityFieldStorageDefinitions[$entity_type_id], Cache::PERMANENT);
      return $this->entityFieldStorageDefinitions[$entity_type_id];
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function setLastInstalledFieldStorageDefinitions($entity_type_id, array $storage_definitions) {
    $this->keyValueFactory->get('entity.definitions.installed')->set($entity_type_id . '.field_storage_definitions', $storage_definitions);
    $this->allEntityTypeDefinitions = NULL;
    unset($this->entityFieldStorageDefinitions[$entity_type_id]);
    $this->cacheBackend->delete($entity_type_id . '.field_storage_definitions.installed');
  }

  /**
   * {@inheritdoc}
   */
  public function setLastInstalledFieldStorageDefinition(FieldStorageDefinitionInterface $storage_definition) {
    $entity_type_id = $storage_definition->getTargetEntityTypeId();
    $this->allEntityTypeDefinitions = NULL;
    unset($this->entityFieldStorageDefinitions[$entity_type_id]);
    $definitions = $this->getLastInstalledFieldStorageDefinitions($entity_type_id);
    $definitions[$storage_definition->getName()] = $storage_definition;
    $this->setLastInstalledFieldStorageDefinitions($entity_type_id, $definitions);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteLastInstalledFieldStorageDefinition(FieldStorageDefinitionInterface $storage_definition) {
    $entity_type_id = $storage_definition->getTargetEntityTypeId();
    $definitions = $this->getLastInstalledFieldStorageDefinitions($entity_type_id);
    $this->allEntityTypeDefinitions = NULL;
    unset($definitions[$storage_definition->getName()]);
    unset($this->entityFieldStorageDefinitions[$entity_type_id]);
    $this->setLastInstalledFieldStorageDefinitions($entity_type_id, $definitions);
  }

}
