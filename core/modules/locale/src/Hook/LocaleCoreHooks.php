<?php

namespace Drupal\locale\Hook;

use Drupal\Core\Asset\AssetResolverInterface;
use Drupal\Core\Asset\AttachedAssets;
use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\State\StateInterface;

/**
 * Hook theme alter implementations for locale.
 */
class LocaleCoreHooks {

  public function __construct(
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly ThemeHandlerInterface $themeHandler,
    protected readonly LibraryDiscoveryInterface $libraryDiscovery,
    protected readonly AssetResolverInterface $assetResolver,
    protected readonly StateInterface $state,
    protected readonly LanguageManagerInterface $languageManager,
  ) {}

  /**
   * Implements hook_rebuild().
   */
  #[Hook('rebuild')]
  public function rebuild() {
    $extensions = array_merge(
      ['core'],
      array_keys($this->moduleHandler->getModuleList()),
      array_keys($this->themeHandler->listInfo())
    );
    $library_discovery = $this->libraryDiscovery;
    $libraries = array_reduce($extensions, function ($libraries, $extension) use ($library_discovery) {
      $extensionLibraries = array_map(
        function ($library) use ($extension) {
          return $extension . '/' . $library;
        },
        array_keys($library_discovery->getLibrariesByExtension($extension))
      );
      return array_merge($libraries, $extensionLibraries);
    }, []);
    $assets = new AttachedAssets();
    $assets->setLibraries($libraries);
    // This triggers hook_js_alter() where all files are scanned for
    // translatable strings.
    $this->assetResolver->getJsAssets($assets, FALSE);

    // Create the translation map files for all languages.
    $languages = $this->languageManager->getLanguages();
    $parsed = $this->state->get('system.javascript_parsed', []);
    foreach ($languages as $language) {
      // If necessary, rebuild the translation file.
      if (!empty($parsed['refresh:' . $language->getId()])) {
        // Don't clear the refresh flag on failure, so that another try will
        // be performed later.
        if (_locale_rebuild_js($language->getId())) {
          unset($parsed['refresh:' . $language->getId()]);
          // Store any changes if refresh was successful.
          $this->state->set('system.javascript_parsed', $parsed);
        }
      }
    }
  }

}
