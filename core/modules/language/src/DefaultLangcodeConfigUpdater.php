<?php

declare(strict_types=1);

namespace Drupal\language;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Schema\Mapping;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\TraversableTypedDataInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\language\Config\LanguageConfigOverride;
use Drupal\language\Event\LanguageDefaultLangcodeChangeEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Builds the batch that reconciles configuration langcode with a new default.
 */
class DefaultLangcodeConfigUpdater implements DefaultLangcodeConfigUpdaterInterface {

  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly TypedConfigManagerInterface $typedConfigManager,
    protected readonly EventDispatcherInterface $eventDispatcher,
    #[Autowire(service: 'config.storage')]
    protected readonly StorageInterface $configStorage,
    protected readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function buildBatch(string $old_langcode, string $new_langcode): BatchBuilder {
    $batch = new BatchBuilder()
      ->setTitle(new TranslatableMarkup('Updating configuration for the new default language'))
      ->addOperation(static::class . ':reconcileConfigLangcodeOperation', [$old_langcode, $new_langcode]);

    $event = new LanguageDefaultLangcodeChangeEvent($old_langcode, $new_langcode, $batch);
    $this->eventDispatcher->dispatch($event);

    return $event->batch;
  }

  /**
   * {@inheritdoc}
   */
  public function reconcileConfigLangcodeOperation(string $old_langcode, string $new_langcode, &$context): void {
    if (!isset($context['sandbox']['names'])) {
      $names = $this->configFactory->listAll();
      $this->moduleHandler->alter('language_update_config_langcode_list', $names, $old_langcode, $new_langcode);
      $context['sandbox']['names'] = $names;
      $context['sandbox']['max'] = count($context['sandbox']['names']);
    }

    $old_override_storage = $this->configStorage->createCollection('language.' . $old_langcode);
    $new_override_storage = $this->configStorage->createCollection('language.' . $new_langcode);
    $names = array_splice($context['sandbox']['names'], 0, Settings::get('entity_update_batch_size', 50));
    foreach ($names as $name) {
      if (!$this->typedConfigManager->hasConfigSchema($name)) {
        continue;
      }

      $config = $this->configFactory->getEditable($name);
      $data = $config->getRawData();
      if (!array_key_exists('langcode', $data)) {
        // The configuration does not have a langcode, so it is not
        // translatable.
        continue;
      }
      $typed_config = $this->typedConfigManager->createFromNameAndData($name, $data);
      if (!$typed_config instanceof Mapping || !in_array('langcode', $typed_config->getValidKeys(), TRUE)) {
        // The configuration schema does not have a langcode, so it is not
        // translatable.
        continue;
      }
      if ($data['langcode'] === $old_langcode) {
        $translatable_data = static::extractTranslatableData($typed_config);
        if ($translatable_data !== []) {
          $this->getRawLanguageConfigOverride($old_override_storage, $name)
            ->setData($translatable_data)
            ->save();
        }

        $override = $this->getRawLanguageConfigOverride($new_override_storage, $name);
        $had_override = !$override->isNew();
        $override_data = $had_override ? $override->get() : [];
        if ($had_override) {
          // The override predates the new default and holds deliberately
          // authored values for it - more authoritative than whatever the
          // updated configuration currently says. Merge it in before the active
          // configuration is saved, so a subscriber reacting to the override's
          // removal below sees the merged content already in place, not the
          // pre-merge values it is replacing.
          $config->setData(NestedArray::mergeDeepArray([$data, $override_data], TRUE));
        }
        $config->set('langcode', $new_langcode)->save();
        if ($had_override) {
          // The new default language can no longer have an override of its
          // own: the active configuration just saved above is now its
          // canonical copy.
          $override->delete();
        }
        // Record which names were actually updated to the new default, so
        // other batch operations (for example locale module translating
        // them) know what to act on.
        $context['results']['updated_names'][] = $name;

        // The override may not have existed at all, or it may only have
        // translated some of the translatable keys. Record the translatable
        // keys not covered by the override separately, so a subscriber
        // reconciling actual translation content (for example, the locale
        // module) knows which keys are still in the original language and which
        // have been translated.
        $untranslated_keys = static::extractUntranslatedKeys($translatable_data, $override_data);
        if (!empty($untranslated_keys)) {
          $context['results']['untranslated_keys'][$name] = $untranslated_keys;
        }
      }
    }

    $context['sandbox']['progress'] = ($context['sandbox']['progress'] ?? 0) + count($names);
    $context['finished'] = empty($context['sandbox']['max']) ? 1 : $context['sandbox']['progress'] / $context['sandbox']['max'];
  }

  /**
   * Gets a language override.
   *
   * This method does not use LanguageConfigFactoryOverride::getStorage()
   * because it skips loading overrides when English is the default language and
   * the passed in langcode is English. That assumption breaks exactly here:
   * switching back to English is when an 'en' override left by an earlier
   * switch away from it needs to be found.
   *
   * @param StorageInterface $storage
   *   The config storage in the language's collection.
   * @param string $name
   *   The configuration object name.
   *
   * @return \Drupal\language\Config\LanguageConfigOverride
   *   The language override object, reflecting whatever is actually in
   *   storage.
   *
   * @see \Drupal\language\Config\LanguageConfigFactoryOverride::getStorage()
   */
  protected function getRawLanguageConfigOverride(StorageInterface $storage, string $name): LanguageConfigOverride {
    $override = new LanguageConfigOverride($name, $storage, $this->typedConfigManager, $this->eventDispatcher);
    $data = $storage->read($name);
    if (!empty($data)) {
      $override->initWithData($data);
    }
    return $override;
  }

  /**
   * Extracts the translatable portion of a config object's current data.
   *
   * Mirrors the shape a language config override normally has - a nested
   * array containing only the values under keys the schema marks
   * translatable - built from this element's actual current values rather
   * than from locale module's stored translations, since this is a
   * snapshot of what the configuration currently says, not a translation of
   * it.
   *
   * @param \Drupal\Core\TypedData\TraversableTypedDataInterface $element
   *   The typed config element to walk - always a Mapping in practice,
   *   since that is all callers ever have on hand.
   *
   * @return array
   *   A nested array matching the structure under $element, containing
   *   only the values under keys the schema marks translatable. Empty if
   *   there is nothing translatable here at all.
   */
  protected static function extractTranslatableData(TraversableTypedDataInterface $element): array {
    $translatable = [];
    foreach ($element as $key => $property) {
      $value = static::extractTranslatableValue($property);
      if ($value !== []) {
        $translatable[$key] = $value;
      }
    }
    return $translatable;
  }

  /**
   * Extracts the translatable value of a single typed config element.
   *
   * @param \Drupal\Core\TypedData\TypedDataInterface $element
   *   The typed config element.
   *
   * @return array|string
   *   Recurses via self::extractTranslatableData() for a traversable
   *   structure, so this is a nested array in that case; the element's raw
   *   value directly if it is itself a translatable leaf; or an empty array
   *   if there is nothing translatable here at all.
   */
  private static function extractTranslatableValue(TypedDataInterface $element): array|string {
    if ($element instanceof TraversableTypedDataInterface) {
      return static::extractTranslatableData($element);
    }
    $definition = $element->getDataDefinition();
    $value = $element->getValue();
    if (!empty($definition['translatable']) && $value !== '' && $value !== NULL) {
      return $value;
    }
    return [];
  }

  /**
   * Finds the translatable key paths an override's data does not cover.
   *
   * @param array $translatable_data
   *   A nested array in the shape self::extractTranslatableData() produces:
   *   every translatable key path this configuration currently has a
   *   non-empty value under.
   * @param array $override_data
   *   A nested array of the key paths present in the override data. May be
   *   empty.
   *
   * @return array
   *   A nested array matching the structure of $translatable_data, containing
   *   only the key paths not present in $override_data. Empty if $override_data
   *   accounts for everything in $translatable_data.
   */
  protected static function extractUntranslatedKeys(array $translatable_data, array $override_data): array {
    $untranslated = [];
    foreach ($translatable_data as $key => $value) {
      if (is_array($value)) {
        $override_value = is_array($override_data[$key] ?? NULL) ? $override_data[$key] : [];
        $diff = static::extractUntranslatedKeys($value, $override_value);
        if ($diff !== []) {
          $untranslated[$key] = $diff;
        }
      }
      elseif (!array_key_exists($key, $override_data)) {
        $untranslated[$key] = $value;
      }
    }
    return $untranslated;
  }

}
