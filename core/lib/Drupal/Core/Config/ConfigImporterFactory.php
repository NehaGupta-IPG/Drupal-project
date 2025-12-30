<?php

namespace Drupal\Core\Config;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Factory class to create config importer objects.
 *
 * This class is declared as final because the ConfigImporter class is not
 * intended to be swappable.
 */
final class ConfigImporterFactory {

  public function __construct(
    protected EventDispatcherInterface $eventDispatcher,
    protected ConfigManagerInterface $configManager,
    #[Autowire(service: 'lock.persistent')]
    protected LockBackendInterface $lock,
    protected TypedConfigManagerInterface $typedConfigManager,
    protected ModuleHandlerInterface $moduleHandler,
    protected ModuleInstallerInterface $moduleInstaller,
    protected ThemeHandlerInterface $themeHandler,
    protected TranslationInterface $stringTranslation,
    protected ModuleExtensionList $moduleExtensionList,
    protected ThemeExtensionList $themeExtensionList,
  ) {}

  /**
   * Creates a ConfigImporter instance.
   *
   * @param \Drupal\Core\Config\StorageComparer $storage_comparer
   *   The storage comparer object. The type is the class and not
   *   StorageComparerInterface because that is due to be removed: see
   *   https://www.drupal.org/project/drupal/issues/3410037.
   *
   * @return \Drupal\Core\Config\ConfigImporter
   *   A config importer instance.
   */
  public function get(StorageComparer $storage_comparer): ConfigImporter {
    return new ConfigImporter(
      $storage_comparer,
      $this->eventDispatcher,
      $this->configManager,
      $this->lock,
      $this->typedConfigManager,
      $this->moduleHandler,
      $this->moduleInstaller,
      $this->themeHandler,
      $this->stringTranslation,
      $this->moduleExtensionList,
      $this->themeExtensionList,
    );
  }

}
