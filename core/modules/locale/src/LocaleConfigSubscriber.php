<?php

namespace Drupal\locale;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorableConfigBase;
use Drupal\Core\Installer\InstallerKernel;
use Drupal\language\Config\LanguageConfigOverrideCrudEvent;
use Drupal\language\Config\LanguageConfigOverrideEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Updates strings translation when configuration translations change.
 *
 * This reacts to the updates of translated active configuration and
 * configuration language overrides. When those updates involve configuration
 * which was available as default configuration, we need to feed back changes
 * to any item which was originally part of that configuration to the interface
 * translation storage. Those updated translations are saved as customized, so
 * further community translation updates will not undo user changes.
 *
 * This subscriber does not respond to deleting active configuration or deleting
 * configuration translations. The locale storage is additive and we cannot be
 * sure that only a given configuration translation used a source string. So
 * we should not remove the translations from locale storage in these cases. The
 * configuration or override would itself be deleted either way.
 *
 * By design locale module only deals with sources in English.
 *
 * @see \Drupal\locale\LocaleConfigManager
 */
class LocaleConfigSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected LocaleConfigManager $localeConfigManager,
    protected readonly LocaleLanguages $localeLanguages,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[LanguageConfigOverrideEvents::SAVE_OVERRIDE] = 'onOverrideChange';
    $events[LanguageConfigOverrideEvents::DELETE_OVERRIDE] = 'onOverrideChange';
    $events[ConfigEvents::SAVE] = 'onConfigSave';
    return $events;
  }

  /**
   * Updates the locale strings when a translated active configuration is saved.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The configuration event.
   */
  public function onConfigSave(ConfigCrudEvent $event) {
    // Only attempt to feed back configuration translation changes to locale if
    // the update itself was not initiated by locale data changes.
    if (!InstallerKernel::installationAttempted() && !$this->localeConfigManager->isUpdatingTranslationsFromLocale()) {
      $config = $event->getConfig();
      $original = $config->getOriginal();

      // A brand-new configuration object's values are, by definition, the
      // shipped defaults - never a manual edit - no matter what langcode it
      // was installed with. This matters because ConfigInstaller may resolve
      // such a langcode to something other than 'en' (see
      // \Drupal\Core\Config\ConfigInstaller::createConfiguration()),
      // including for a module installed long after the site itself was,
      // which the InstallerKernel::installationAttempted() check above does
      // not cover.
      if (empty($original)) {
        return;
      }

      $langcode = $config->get('langcode') ?: 'en';
      // Pass the pre-save values through so a value that did not actually
      // change in this save is never fed back as a customized translation.
      // Without this, an unrelated key changing in the same save - for
      // example a module's hook_install() setting a non-translatable key as
      // a follow-up to ConfigInstaller relabelling the object's langcode -
      // would cause the still-untranslated values to be re-saved as
      // customized translations, clobbering any real translation before it
      // gets a chance to apply.
      $this->updateLocaleStorage($config, $langcode, [], $original);
    }
  }

  /**
   * Updates the locale strings when a configuration override is saved/deleted.
   *
   * @param \Drupal\language\Config\LanguageConfigOverrideCrudEvent $event
   *   The language configuration event.
   */
  public function onOverrideChange(LanguageConfigOverrideCrudEvent $event) {
    // Only attempt to feed back configuration override changes to locale if
    // the update itself was not initiated by locale data changes.
    if (!InstallerKernel::installationAttempted() && !$this->localeConfigManager->isUpdatingTranslationsFromLocale()) {
      $translation_config = $event->getLanguageConfigOverride();
      $langcode = $translation_config->getLangcode();
      $reference_config = $this->configFactory->getEditable($translation_config->getName())->get();
      $this->updateLocaleStorage($translation_config, $langcode, $reference_config);
    }
  }

  /**
   * Update locale storage based on configuration translations.
   *
   * @param \Drupal\Core\Config\StorableConfigBase $config
   *   Active configuration or configuration translation override.
   * @param string $langcode
   *   The language code of $config.
   * @param array $reference_config
   *   (Optional) Reference configuration to check against if $config was an
   *   override. This allows us to update locale keys for data not in the
   *   override but still in the active configuration.
   * @param array $original_config
   *   (Optional) The configuration's values before this save, keyed the same
   *   way as $config. When given, a leaf value unchanged from $original_config
   *   is never fed back into locale storage, since it was not actually
   *   (re)authored in $langcode by this save.
   */
  public function updateLocaleStorage(StorableConfigBase $config, $langcode, array $reference_config = [], array $original_config = []) {
    $name = $config->getName();
    if ($this->localeConfigManager->isSupported($name) && $this->localeLanguages->isTranslatable($langcode)) {
      $translatables = $this->localeConfigManager->getTranslatableDefaultConfig($name);
      $this->processTranslatableData($name, $config->get(), $translatables, $langcode, $reference_config, $original_config);
    }
  }

  /**
   * Process the translatable data array with a given language.
   *
   * @param string $name
   *   The configuration name.
   * @param array $config
   *   The active configuration data or override data.
   * @param array|\Drupal\Core\StringTranslation\TranslatableMarkup[] $translatable
   *   The translatable array structure.
   *   @see \Drupal\locale\LocaleConfigManager::getTranslatableData()
   * @param string $langcode
   *   The language code to process the array with.
   * @param array $reference_config
   *   (Optional) Reference configuration to check against if $config was an
   *   override. This allows us to update locale keys for data not in the
   *   override but still in the active configuration.
   * @param array $original_config
   *   (Optional) The configuration's values before this save, keyed the same
   *   way as $config. When given, a leaf value unchanged from $original_config
   *   is never fed back into locale storage, since it was not actually
   *   (re)authored in $langcode by this save.
   */
  protected function processTranslatableData($name, array $config, array $translatable, $langcode, array $reference_config = [], array $original_config = []) {
    foreach ($translatable as $key => $item) {
      if (!isset($config[$key])) {
        if (isset($reference_config[$key])) {
          $this->resetExistingTranslations($name, $translatable[$key], $reference_config[$key], $langcode);
        }
        continue;
      }
      // A value unchanged from before this save was not (re)authored in
      // $langcode now, so there is nothing to feed back. See the @param
      // documentation above for why this matters.
      if (array_key_exists($key, $original_config) && $original_config[$key] === $config[$key]) {
        continue;
      }
      if (is_array($item)) {
        $reference_config_item = $reference_config[$key] ?? [];
        $original_config_item = is_array($original_config[$key] ?? NULL) ? $original_config[$key] : [];
        $this->processTranslatableData($name, $config[$key], $item, $langcode, $reference_config_item, $original_config_item);
      }
      else {
        $this->saveCustomizedTranslation($name, $item->getUntranslatedString(), $item->getOption('context'), $config[$key], $langcode);
      }
    }
  }

  /**
   * Reset existing locale translations to their source values.
   *
   * Goes through $translatable to reset any existing translations to the source
   * string, so prior translations would not reappear in the configuration.
   *
   * @param string $name
   *   The configuration name.
   * @param array|\Drupal\Core\StringTranslation\TranslatableMarkup $translatable
   *   Either a possibly nested array with TranslatableMarkup objects at the
   *   leaf items or a TranslatableMarkup object directly.
   * @param array|string $reference_config
   *   Either a possibly nested array with strings at the leaf items or a string
   *   directly. Only those $translatable items that are also present in
   *   $reference_config will get translations reset.
   * @param string $langcode
   *   The language code of the translation being processed.
   */
  protected function resetExistingTranslations($name, $translatable, $reference_config, $langcode) {
    if (is_array($translatable)) {
      foreach ($translatable as $key => $item) {
        if (isset($reference_config[$key])) {
          // Process further if the key still exists in the reference active
          // configuration and the default translation but not the current
          // configuration override.
          $this->resetExistingTranslations($name, $item, $reference_config[$key], $langcode);
        }
      }
    }
    elseif (!is_array($reference_config)) {
      $this->saveCustomizedTranslation($name, $translatable->getUntranslatedString(), $translatable->getOption('context'), $reference_config, $langcode);
    }
  }

  /**
   * Saves a translation string and marks it as customized.
   *
   * @param string $name
   *   The configuration name.
   * @param string $source
   *   The source string value.
   * @param string $context
   *   The source string context.
   * @param string $new_translation
   *   The translation string.
   * @param string $langcode
   *   The language code of the translation.
   */
  protected function saveCustomizedTranslation($name, $source, $context, $new_translation, $langcode) {
    $locale_translation = $this->localeConfigManager->getStringTranslation($name, $langcode, $source, $context);
    if (!empty($locale_translation)) {
      // If this code is triggered during installation never set the translation
      // to the source string.
      if (InstallerKernel::installationAttempted() && $source === $new_translation) {
        return;
      }
      // Save this translation as custom if it was a new translation and not the
      // same as the source. (The interface prefills translation values with the
      // source). Or if there was an existing (non-empty) translation and the
      // user changed it (even if it was changed back to the original value).
      // Otherwise the translation file would be overwritten with the locale
      // copy again later.
      $existing_translation = $locale_translation->getString();
      if (($locale_translation->isNew() && $source != $new_translation) ||
        (!$locale_translation->isNew() && ((empty($existing_translation) && $source != $new_translation) || ((!empty($existing_translation) && $new_translation != $existing_translation))))) {
        $locale_translation
          ->setString($new_translation)
          ->setCustomized(TRUE)
          ->save();
      }
    }
  }

}
