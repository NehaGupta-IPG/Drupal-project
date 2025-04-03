<?php

declare(strict_types=1);

namespace Drupal\user\Service;

use Drupal\Core\Discovery\YamlDiscovery;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\user\Permissions\PermissionsRepository;
use Drupal\user\Permissions\PermissionsRepositoryInterface;
use Drupal\user\PermissionsParserPluginManager;

/**
 * Implementation of the PermissionsRepositoryFactoryInterface interface.
 *
 * @see \Drupal\user\Service\PermissionsRepositoryFactoryInterface
 */
class PermissionsRepositoryFactory implements PermissionsRepositoryFactoryInterface {

  /**
   * Constructs a PermissionsRepositoryFactory object.
   */
  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly PermissionsParserPluginManager $pluginManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function createPermissionsRepositoryFromPermissionFiles($legacyOnly = FALSE): PermissionsRepositoryInterface {
    $yamlDiscovery = new YamlDiscovery('permissions', $this->moduleHandler->getModuleDirectories());

    $list = $yamlDiscovery->findAll();

    return $this->createPermissionsRepository($list, $legacyOnly);
  }

  /**
   * {@inheritdoc}
   */
  public function createPermissionsRepository(array $list, $legacyOnly = FALSE): PermissionsRepositoryInterface {
    $permissionObj = new PermissionsRepository();

    $definitions = $this->pluginManager->getDefinitions();
    usort($definitions, function ($a, $b) {
      return $a['weight'] <=> $b['weight'];
    });

    if ($legacyOnly) {
      $definitions = array_filter($definitions, function ($definition) {
        return !empty($definition['legacy']);
      });
    }

    foreach ($list as $provider => $permissions) {
      foreach ($definitions as $definition) {
        $plugin = $this->pluginManager->createInstance($definition['id']);
        $plugin->parse($permissions, $provider, $permissionObj);
      }
    }

    $permissionObj->sortAll();

    return $permissionObj;
  }

}
