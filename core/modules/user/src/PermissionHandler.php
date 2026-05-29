<?php

namespace Drupal\user;

use Drupal\Core\Discovery\YamlDiscovery;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Utility\CallableResolver;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Provides the available permissions based on yml files and service providers.
 *
 * To define permissions you can use a $module.permissions.yml file. This file
 * defines machine names, human-readable names, restrict access (if required for
 * security warning), and optionally descriptions for each permission type. The
 * machine names are the canonical way to refer to permissions for access
 * checking.
 *
 * If your module needs to define dynamic permissions, declare a service
 * implementing \Drupal\user\PermissionProviderInterface. Any such service is
 * automatically discovered and its getPermissions() return value is merged
 * with permissions declared in *.permissions.yml.
 *
 * (Deprecated) If your module needs to define dynamic permissions, you can
 * also use the permission_callbacks key to declare a callable that will
 * return an array of permissions, keyed by machine name. Each item in the
 * array can contain the same keys as an entry in $module.permissions.yml.
 *
 * Here is an example from the core filter module (comments have been added):
 *
 * @code
 * # The key is the permission machine name, and is required.
 * administer filters:
 *   # (required) Human readable name of the permission used in the UI.
 *   title: 'Administer text formats and filters'
 *   # (optional) Additional description fo the permission used in the UI.
 *   description: 'Define how text is handled by combining filters into text formats.'
 *   # (optional) Boolean, when set to true a warning about site security will
 *   # be displayed on the Permissions page. Defaults to false.
 *   restrict access: false
 *
 * # (Deprecated) An array of callables used to generate dynamic permissions.
 * permission_callbacks:
 *   # The callable should return an associative array with one or more
 *   # permissions. Each permission array can use the same keys as the example
 *   # permission defined above. Additionally, a dependencies key is supported.
 *   # For more information about permission dependencies see
 *   # PermissionHandlerInterface::getPermissions().
 *   - Drupal\filter\FilterPermissions::permissions
 * @endcode
 *
 * @see \Drupal\user\PermissionHandlerInterface::getPermissions()
 * @see \Drupal\user\PermissionProviderInterface
 * @see filter.permissions.yml
 * @see \Drupal\filter\FilterPermissions
 * @see user_api
 */
class PermissionHandler implements PermissionHandlerInterface {

  use StringTranslationTrait;

  /**
   * The YAML discovery class to find all .permissions.yml files.
   *
   * @var \Drupal\Core\Discovery\YamlDiscovery
   */
  protected $yamlDiscovery;

  /**
   * Constructs a new PermissionHandler.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation.
   * @param \Drupal\Core\Utility\CallableResolver $callableResolver
   *   The callable resolver.
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   The module extension list.
   * @param iterable<\Drupal\user\PermissionProviderInterface> $permissionProviders
   *   The set of services tagged with 'user.permission_provider'.
   */
  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    TranslationInterface $string_translation,
    protected CallableResolver $callableResolver,
    protected ModuleExtensionList $moduleExtensionList,
    #[AutowireIterator(tag: 'user.permission_provider')]
    protected iterable $permissionProviders = [],
  ) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * Gets the YAML discovery.
   *
   * @return \Drupal\Core\Discovery\YamlDiscovery
   *   The YAML discovery.
   */
  protected function getYamlDiscovery() {
    if (!isset($this->yamlDiscovery)) {
      $this->yamlDiscovery = new YamlDiscovery('permissions', $this->moduleHandler->getModuleDirectories());
    }
    return $this->yamlDiscovery;
  }

  /**
   * {@inheritdoc}
   */
  public function getPermissions() {
    $all_permissions = $this->buildPermissionsYaml();
    $all_permissions += $this->buildPermissionsFromProviders();

    return $this->sortPermissions($all_permissions);
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

  /**
   * Builds permissions from services tagged 'user.permission_provider'.
   *
   * @return array[]
   *   An array of permissions.
   *
   * @see \Drupal\user\PermissionHandlerInterface::getPermissions()
   * @see \Drupal\user\PermissionProviderInterface
   */
  protected function buildPermissionsFromProviders(): array {
    $all_permissions = [];
    foreach ($this->permissionProviders as $permission_provider) {
      $provider_module = $this->getProviderModule($permission_provider);
      foreach ($permission_provider->getPermissions() as $name => $permission) {
        if (!is_array($permission)) {
          $permission = ['title' => $permission];
        }
        $permission += [
          'description' => NULL,
          'provider' => $provider_module,
        ];
        $all_permissions[$name] = $permission;
      }
    }
    return $all_permissions;
  }

  /**
   * Infers the providing module of a permission provider from its namespace.
   *
   * @param \Drupal\user\PermissionProviderInterface $permission_provider
   *   The permission provider service.
   *
   * @return string|null
   *   The module machine name, or NULL if it cannot be inferred.
   *
   * @see \Drupal\Core\Plugin\Discovery\AttributeClassDiscovery::getProviderFromNamespace()
   */
  protected function getProviderModule(PermissionProviderInterface $permission_provider): ?string {
    preg_match('|^Drupal\\\\(?<provider>[\w]+)\\\\|', $permission_provider::class, $matches);
    if (isset($matches['provider'])) {
      return mb_strtolower($matches['provider']);
    }
    return NULL;
  }

  /**
   * Builds all permissions provided by .permissions.yml files.
   *
   * @return array[]
   *   An array with the same structure as
   *   PermissionHandlerInterface::getPermissions().
   *
   * @see \Drupal\user\PermissionHandlerInterface::getPermissions()
   */
  protected function buildPermissionsYaml() {
    $all_permissions = [];
    $all_callback_permissions = [];

    foreach ($this->getYamlDiscovery()->findAll() as $provider => $permissions) {
      // The top-level 'permissions_callback' is a list of methods in callable
      // syntax, see \Drupal\Core\Utility\CallableResolver. These methods
      // should return an array of permissions in the same structure.
      if (isset($permissions['permission_callbacks'])) {
        @trigger_error("The 'permission_callbacks' key in {$provider}.permissions.yml is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Declare a service implementing \\Drupal\\user\\PermissionProviderInterface instead. See https://www.drupal.org/node/3421580", E_USER_DEPRECATED);
        foreach ($permissions['permission_callbacks'] as $permission_callback) {
          $callback = $this->callableResolver->getCallableFromDefinition($permission_callback);
          if ($callback_permissions = call_user_func($callback)) {
            // Add any callback permissions to the array of permissions. Any
            // defaults can then get processed below.
            foreach ($callback_permissions as $name => $callback_permission) {
              if (!is_array($callback_permission)) {
                $callback_permission = [
                  'title' => $callback_permission,
                ];
              }

              $callback_permission += [
                'description' => NULL,
                'provider' => $provider,
              ];

              $all_callback_permissions[$name] = $callback_permission;
            }
          }
        }

        unset($permissions['permission_callbacks']);
      }

      foreach ($permissions as &$permission) {
        if (!is_array($permission)) {
          $permission = [
            'title' => $permission,
          ];
        }
        // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
        $permission['title'] = $this->t($permission['title']);
        // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
        $permission['description'] = isset($permission['description']) ? $this->t($permission['description']) : NULL;
        $permission['provider'] = !empty($permission['provider']) ? $permission['provider'] : $provider;
      }

      $all_permissions += $permissions;
    }

    return $all_permissions + $all_callback_permissions;
  }

  /**
   * Sorts the given permissions by provider name and title.
   *
   * @param array $all_permissions
   *   The permissions to be sorted.
   *
   * @return array[]
   *   An array with the same structure as
   *   PermissionHandlerInterface::getPermissions().
   *
   * @see \Drupal\user\PermissionHandlerInterface::getPermissions()
   */
  protected function sortPermissions(array $all_permissions = []) {
    // Get a list of all the modules providing permissions and sort by
    // display name.
    $modules = $this->getModuleNames();

    uasort($all_permissions, function (array $permission_a, array $permission_b) use ($modules) {
      if ($modules[$permission_a['provider']] == $modules[$permission_b['provider']]) {
        return $permission_a['title'] <=> $permission_b['title'];
      }
      else {
        return $modules[$permission_a['provider']] <=> $modules[$permission_b['provider']];
      }
    });
    return $all_permissions;
  }

  /**
   * Returns all module names.
   *
   * @return string[]
   *   Returns the human readable names of all modules keyed by machine name.
   */
  protected function getModuleNames() {
    $modules = [];
    foreach (array_keys($this->moduleHandler->getModuleList()) as $module) {
      $modules[$module] = $this->moduleExtensionList->getName($module);
    }
    asort($modules);
    return $modules;
  }

}
