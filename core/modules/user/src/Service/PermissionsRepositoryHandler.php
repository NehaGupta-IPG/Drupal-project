<?php

namespace Drupal\user\Service;

use Drupal\Core\Discovery\YamlDiscovery;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Utility\CallableResolver;
use Drupal\user\PermissionHandlerInterface;
use Drupal\user\Service\PermissionsRepositoryFactoryInterface;
use Drupal\user\Permissions\PermissionsRepositoryReturnStyle;

class PermissionsRepositoryHandler implements PermissionHandlerInterface {

  use StringTranslationTrait;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The YAML discovery class to find all .permissions.yml files.
   *
   * @var \Drupal\Core\Discovery\YamlDiscovery
   */
  protected $yamlDiscovery;

  /**
   * The callable resolver.
   *
   * @var \Drupal\Core\Utility\CallableResolver
   */
  protected CallableResolver $callableResolver;

  /**
   * Constructs a new PermissionsRepositoryHandler.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation.
   * @param \Drupal\Core\Utility\CallableResolver $callable_resolver
   *   The callable resolver.
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   The module extension list.
   */
  public function __construct(ModuleHandlerInterface $module_handler, TranslationInterface $string_translation, CallableResolver $callable_resolver, protected ModuleExtensionList $moduleExtensionList, protected PermissionsRepositoryFactoryInterface $permissionsRepositoryFactory) {
    $this->callableResolver = $callable_resolver;

    // @todo It would be nice if you could pull all module directories from the
    //   container.
    $this->moduleHandler = $module_handler;
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public function getPermissions() {
    $permissionObj = $this->permissionsRepositoryFactory->createPermissionsRepositoryFromPermissionFiles();

    return $permissionObj->getAllPermissions(PermissionsRepositoryReturnStyle::SectionArray);
  }

  /**
   * {@inheritdoc}
   */
  public function moduleProvidesPermissions($module_name) {
    // @todo Static cache this information.
    //   https://www.drupal.org/node/2339487
    $permissions = $this->getPermissions();

    foreach ($permissions as $permission) {
      if ($permission['provider'] == $module_name) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
