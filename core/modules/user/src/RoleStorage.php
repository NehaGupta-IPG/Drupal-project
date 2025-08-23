<?php

namespace Drupal\user;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Controller class for user roles.
 *
 * @deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no
 *    replacement.
 * @see https://www.drupal.org/node/3477235
 */
class RoleStorage extends ConfigEntityStorage implements RoleStorageInterface {

  /**
   * Constructs a ConfigEntityStorage object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid_service
   *   The UUID service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface $memory_cache
   *   The memory cache backend.
   */
  public function __construct(EntityTypeInterface $entity_type, ConfigFactoryInterface $config_factory, UuidInterface $uuid_service, LanguageManagerInterface $language_manager, MemoryCacheInterface $memory_cache) {
    @trigger_error(__NAMESPACE__ . '\RoleStorage is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no replacement. See https://www.drupal.org/node/3477235', E_USER_DEPRECATED);
    parent::__construct($entity_type, $config_factory, $uuid_service, $language_manager, $memory_cache);
  }

  /**
   * {@inheritdoc}
   */
  public function isPermissionInRoles($permission, array $rids) {
    foreach ($this->loadMultiple($rids) as $role) {
      /** @var \Drupal\user\RoleInterface $role */
      if ($role->hasPermission($permission)) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
