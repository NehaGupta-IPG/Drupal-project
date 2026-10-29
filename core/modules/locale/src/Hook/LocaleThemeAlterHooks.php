<?php

namespace Drupal\locale\Hook;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Asset\AttachedAssetsInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\locale\LocaleJs;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;

/**
 * Hook theme alter implementations for locale.
 */
class LocaleThemeAlterHooks {

  public function __construct(
    /**
     * @var \Closure(): \Drupal\locale\LocaleJs
     */
    #[AutowireServiceClosure(LocaleJs::class)]
    protected readonly \Closure $localeJs,
  ) {}

  /**
   * Implements hook_js_alter().
   */
  #[Hook('js_alter')]
  public function jsAlter(array &$javascript, AttachedAssetsInterface $assets, LanguageInterface $language): void {
    $placeholder_file = 'core/modules/locale/locale.translation.js';
    $files = [];
    foreach ($javascript as $item) {
      if (isset($item['type']) && $item['type'] == 'file') {
        // Ignore the JS translation placeholder file.
        if ($item['data'] === $placeholder_file) {
          continue;
        }
        $files[] = $item['data'];
      }
    }
    // Always run through the files through JavaScript translation so that any
    // strings will be available to the translation file for the language.
    $translation_file = ($this->localeJs)()->jsTranslate($files, $language);
    // If the locale placeholder is loaded by this request, add the translation
    // file. This only needs to happen once per page because all JavaScript
    // translations for a language are contained in a single file.
    if (isset($files[$placeholder_file]) && $translation_file) {
      $js_translation_asset =& $javascript[$placeholder_file];
      $js_translation_asset['data'] = $translation_file;
      // @todo Remove this when https://www.drupal.org/node/1945262 lands.
      // Decrease the weight so that the translation file is loaded first.
      $js_translation_asset['weight'] = $javascript['core/misc/drupal.js']['weight'] - 0.001;
    }
    unset($javascript[$placeholder_file]);
  }

  /**
   * Implements hook_library_info_alter().
   *
   * Provides language support.
   */
  #[Hook('library_info_alter')]
  public function libraryInfoAlter(array &$libraries, string $module): void {
    // When the locale module is enabled, we update the core/drupal library to
    // have a dependency on the locale/translations library, which provides
    // window.drupalTranslations, containing the translations for all strings in
    // JavaScript assets in the current language.
    // @see locale_js_alter()
    if ($module === 'core' && isset($libraries['drupal'])) {
      $libraries['drupal']['dependencies'][] = 'locale/translations';
    }
  }

}
