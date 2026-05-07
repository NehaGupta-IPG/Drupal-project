<?php

namespace Drupal\Core\DependencyInjection;

use Drupal\Component\DependencyInjection\ReverseContainer;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\DeprecatedSleepTrait;
use Drupal\Core\Utility\DeprecatedWakeupTrait;

/**
 * Provides dependency injection friendly methods for serialization.
 */
trait DependencySerializationTrait {

  use DeprecatedSleepTrait;
  use DeprecatedWakeupTrait;

  /**
   * An array of service IDs keyed by property name used for serialization.
   *
   * @var array
   */
  // phpcs:ignore Drupal.Classes.PropertyDeclaration, Drupal.NamingConventions.ValidVariableName.LowerCamelName
  protected $_serviceIds = [];

  /**
   * An array of entity type IDs keyed by the property name of their storages.
   *
   * @var array
   */
  // phpcs:ignore Drupal.Classes.PropertyDeclaration, Drupal.NamingConventions.ValidVariableName.LowerCamelName
  protected $_entityStorages = [];

  /**
   * {@inheritdoc}
   */
  protected function doSerialize(): array {
    $vars = get_object_vars($this);
    try {
      $container = \Drupal::getContainer();
      $reverse_container = $container->get(ReverseContainer::class);
      foreach ($vars as $key => $value) {
        if (!is_object($value) || $value instanceof TranslatableMarkup) {
          // Ignore properties that cannot be services.
          continue;
        }
        if ($value instanceof EntityStorageInterface) {
          // If a class member is an entity storage, only store the entity type
          // ID the storage is for, so it can be used to get a fresh object on
          // unserialization. By doing this we prevent possible memory leaks
          // when the storage is serialized and it contains a static cache of
          // entity objects. Additionally we ensure that we'll not have multiple
          // storage objects for the same entity type and therefore prevent
          // returning different references for the same entity.
          $this->_entityStorages[$key] = $value->getEntityTypeId();
          unset($vars[$key]);
        }
        elseif ($service_id = $reverse_container->getId($value)) {
          // If a class member was instantiated by the dependency injection
          // container, only store its ID so it can be used to get a fresh
          // object on unserialization.
          $this->_serviceIds[$key] = $service_id;
          unset($vars[$key]);
        }
      }
    }
    catch (ContainerNotInitializedException) {
      // No container, no problem.
    }
    $vars['_entityStorages'] = $this->_entityStorages;
    $vars['_serviceIds'] = $this->_serviceIds;
    return $vars;
  }

  /**
   * {@inheritdoc}
   */
  protected function doUnserialize(array $vars): void {
    $service_ids = $vars['_serviceIds'] ?? [];
    unset($vars['_serviceIds']);
    $this->_serviceIds = [];
    $entity_storages = $vars['_entityStorages'] ?? [];
    unset($vars['_entityStorages']);
    $this->_entityStorages = [];
    foreach ($vars as $property => $value) {
      $normalized = $this->normalizeSerializedKey($property);
      if ($normalized === NULL) {
        continue;
      }
      $this->$normalized = $value;
    }
    // Avoid trying to repopulate from container if there's nothing to do.
    if (empty($service_ids) && empty($entity_storages)) {
      return;
    }

    $container = \Drupal::getContainer();
    foreach ($service_ids as $key => $service_id) {
      $this->$key = $container->get($service_id);
    }

    if ($entity_storages) {
      /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
      $entity_type_manager = $container->get('entity_type.manager');
      foreach ($entity_storages as $key => $entity_type_id) {
        $this->$key = $entity_type_manager->getStorage($entity_type_id);
      }
    }
  }

}
