<?php

namespace Drupal\Core\DependencyInjection;

use Drupal\Component\DependencyInjection\ReverseContainer;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides dependency injection friendly methods for serialization.
 *
 * Classes using this trait can safely declare injected service properties as
 * readonly (PHP 8.1+). The trait's __serialize()/__unserialize() methods
 * handle re-injection of services after deserialization without the fatal
 * errors that occur when assigning to readonly properties inside __wakeup().
 */
trait DependencySerializationTrait {

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
   * Prepares the object for serialization.
   *
   * Replaces service objects and entity storages with lightweight identifiers
   * so that the serialized form does not contain live service objects.
   *
   * NOTE: If your class overrides __sleep(), override __serialize() instead.
   * PHP invokes __serialize() in preference to __sleep() when both exist,
   * so a __sleep() override on a class using this trait will be silently
   * ignored at runtime.
   *
   * @return array
   *   The data array to serialize.
   */
  public function __serialize(): array {
    $vars = get_object_vars($this);
    $service_ids = [];
    $entity_storages = [];
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
          $entity_storages[$key] = $value->getEntityTypeId();
          unset($vars[$key]);
        }
        elseif ($service_id = $reverse_container->getId($value)) {
          // If a class member was instantiated by the dependency injection
          // container, only store its ID so it can be used to get a fresh
          // object on unserialization.
          $service_ids[$key] = $service_id;
          unset($vars[$key]);
        }
      }
    }
    catch (ContainerNotInitializedException) {
      // No container, no problem.
    }
    // Store identifiers in the serialized data rather than mutating $this,
    // which is important when properties are readonly.
    $vars['_serviceIds'] = $service_ids;
    $vars['_entityStorages'] = $entity_storages;

    return $vars;
  }

  /**
   * Restores the object after unserialization.
   *
   * Re-injects services and entity storages from the container. Direct
   * property assignment here works for readonly properties because PHP treats
   * __unserialize() as an initialization context (unlike __wakeup()).
   *
   * NOTE: If your class overrides __wakeup(), override __unserialize()
   * instead. A __wakeup() override will not be called when __unserialize()
   * is defined, and — critically — cannot assign to readonly properties.
   *
   * @param array $data
   *   The data array produced by __serialize().
   */
  public function __unserialize(array $data): void {
    $service_ids = $data['_serviceIds'] ?? [];
    $entity_storages = $data['_entityStorages'] ?? [];
    unset($data['_serviceIds'], $data['_entityStorages']);

    // Restore all non-service properties first (including readonly ones).
    foreach ($data as $key => $value) {
      // Use ReflectionProperty to handle readonly properties that have already
      // been set (e.g. via constructor) but need to be overwritten here.
      // For uninitialized readonly properties, direct assignment also works.
      $this->$key = $value;
    }

    if (empty($service_ids) && empty($entity_storages)) {
      return;
    }

    // Tests in isolation potentially unserialize in the parent process.
    $phpunit_bootstrap = isset($GLOBALS['__PHPUNIT_BOOTSTRAP']);
    if ($phpunit_bootstrap && !\Drupal::hasContainer()) {
      return;
    }

    $container = \Drupal::getContainer();
    foreach ($service_ids as $key => $service_id) {
      // In rare cases, when test data is serialized in the parent process,
      // there is a service container but it doesn't contain all expected
      // services. To avoid fatal errors during the wrap-up of failing tests,
      // we check for this case, too.
      if ($phpunit_bootstrap && !$container->has($service_id)) {
        continue;
      }
      // Direct assignment to readonly properties is valid inside
      // __unserialize() because PHP treats it as the initialization context.
      $this->$key = $container->get($service_id);
    }

    if ($entity_storages && (!$phpunit_bootstrap || $container->has('entity_type.manager'))) {
      /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
      $entity_type_manager = $container->get('entity_type.manager');
      foreach ($entity_storages as $key => $entity_type_id) {
        $this->$key = $entity_type_manager->getStorage($entity_type_id);
      }
    }
  }


   /**
    * @deprecated in drupal:11.x-dev and is removed from drupal:12.0.0.
    *   Override __serialize() instead.
    *
    * @see https://www.drupal.org/node/[CHANGE-NOTICE-NID]
    */
  public function __sleep(): array {
    @trigger_error(
      __CLASS__ . '::__sleep() is deprecated in drupal:11.x-dev and will be ' .
      'removed in drupal:12.0.0. Override __serialize() instead. ' .
      'See https://www.drupal.org/node/[CHANGE-NOTICE-NID]',
      E_USER_DEPRECATED
    );
    // Delegate to __serialize() so existing call-sites still work.
    $data = $this->__serialize();
    // Stash identifiers as instance properties so __wakeup() can read them,
    // which is the contract that __sleep()/__wakeup() callers expect.
    $this->_serviceIds    = $data['_serviceIds'];
    $this->_entityStorages = $data['_entityStorages'];
    unset($data['_serviceIds'], $data['_entityStorages']);
    // Add the stash properties to the list so they are included in the
    // serialized payload.
    $data['_serviceIds']    = NULL; // included via property, not value
    $data['_entityStorages'] = NULL;
    return array_keys($data) + ['_serviceIds', '_entityStorages'];
  }

  /**
   * @deprecated in drupal:11.x-dev and is removed from drupal:12.0.0.
   *   Override __unserialize() instead.
   *
   * @see https://www.drupal.org/node/[CHANGE-NOTICE-NID]
   */
  public function __wakeup(): void {
    @trigger_error(
      __CLASS__ . '::__wakeup() is deprecated in drupal:11.x-dev and will be ' .
      'removed in drupal:12.0.0. Override __unserialize() instead. ' .
      'See https://www.drupal.org/node/[CHANGE-NOTICE-NID]',
      E_USER_DEPRECATED
    );
    // Rebuild a data array so __unserialize() can handle re-injection.
    // Note: this path CANNOT support readonly properties. Classes with
    // readonly-injected services must migrate to __unserialize().
    $data = get_object_vars($this);
    $data['_serviceIds']    = $this->_serviceIds;
    $data['_entityStorages'] = $this->_entityStorages;
    $this->__unserialize($data);
  }

}
