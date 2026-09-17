<?php

namespace Drupal\Core\Field;

use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\Uuid\UuidInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Storage class for base field overrides.
 */
class BaseFieldOverrideStorage extends FieldConfigStorageBase {

  /**
   * Constructs a BaseFieldOverrideStorage object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid_service
   *   The UUID service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_manager
   *   The field type plugin manager.
   * @param \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface $memory_cache
   *   The memory cache.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface|null $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(EntityTypeInterface $entity_type, ConfigFactoryInterface $config_factory, UuidInterface $uuid_service, LanguageManagerInterface $language_manager, FieldTypePluginManagerInterface $field_type_manager, MemoryCacheInterface $memory_cache, ?EventDispatcherInterface $event_dispatcher = NULL) {
    parent::__construct($entity_type, $config_factory, $uuid_service, $language_manager, $memory_cache, $event_dispatcher);
    $this->fieldTypeManager = $field_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('config.factory'),
      $container->get('uuid'),
      $container->get('language_manager'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('entity.memory_cache'),
      $container->get('event_dispatcher'),
    );
  }

}
