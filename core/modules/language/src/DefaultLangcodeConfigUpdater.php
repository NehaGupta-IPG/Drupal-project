<?php

declare(strict_types=1);

namespace Drupal\language;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Schema\Mapping;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\language\Config\LanguageConfigOverride;
use Drupal\language\Event\LanguageDefaultLangcodeChangeEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Builds the batch that reconciles configuration langcode with a new default.
 */
class DefaultLangcodeConfigUpdater {

  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly TypedConfigManagerInterface $typedConfigManager,
    protected readonly EventDispatcherInterface $eventDispatcher,
  ) {}

  /**
   * Builds the batch definition for a default langcode change.
   *
   * @param string $old_langcode
   *   The previous default langcode.
   * @param string $new_langcode
   *   The new default langcode.
   *
   * @return array
   *   A Batch API definition, suitable for batch_set().
   */
  public function buildBatch(string $old_langcode, string $new_langcode): array {
    $event = new LanguageDefaultLangcodeChangeEvent($old_langcode, $new_langcode);
    $event->addOperation([static::class, 'reconcileConfigLangcodeOperation'], [$old_langcode, $new_langcode]);
    $this->eventDispatcher->dispatch($event, LanguageEvents::DEFAULT_LANGCODE_CHANGE);

    return [
      'title' => new TranslatableMarkup('Updating configuration for the new default language'),
      'operations' => $event->getOperations(),
    ];
  }

  /**
   * Batch operation: relabels configuration following the previous default.
   *
   * Only configuration whose langcode currently matches the previous
   * default is touched - anything explicitly set to some other langcode is
   * left alone, the same way an ordinary save would leave it alone. While
   * walking every object anyway, this also enforces the standing rule that
   * configuration without a translatable value should not carry a langcode.
   *
   * A configuration override already existing for the new default langcode
   * is absorbed into the configuration being relabelled, then removed: the
   * new default language can no longer have an override of its own once the
   * active configuration itself becomes its canonical copy.
   *
   * @param string $old_langcode
   *   The previous default langcode.
   * @param string $new_langcode
   *   The new default langcode.
   * @param array|\ArrayAccess $context
   *   The batch context.
   */
  public static function reconcileConfigLangcodeOperation(string $old_langcode, string $new_langcode, &$context): void {
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
    $config_factory = \Drupal::configFactory();
    /** @var \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager */
    $typed_config_manager = \Drupal::service(TypedConfigManagerInterface::class);

    if (!isset($context['sandbox']['names'])) {
      $context['sandbox']['names'] = $config_factory->listAll();
      $context['sandbox']['max'] = count($context['sandbox']['names']);
    }

    $names = array_splice($context['sandbox']['names'], 0, Settings::get('entity_update_batch_size', 50));
    foreach ($names as $name) {
      if (!$typed_config_manager->hasConfigSchema($name)) {
        continue;
      }

      $config = $config_factory->getEditable($name);
      $data = $config->getRawData();
      $typed_config = $typed_config_manager->createFromNameAndData($name, $data);
      if (!$typed_config instanceof Mapping || !in_array('langcode', $typed_config->getValidKeys(), TRUE)) {
        continue;
      }
      $is_translatable = $typed_config->getDataDefinition()['translatable'] || $typed_config->hasTranslatableElements();
      $current_langcode = $data['langcode'] ?? NULL;

      if (!$is_translatable) {
        if ($current_langcode !== NULL) {
          $config->clear('langcode')->save();
        }
        continue;
      }

      if ($current_langcode === NULL || $current_langcode === $old_langcode) {
        $override = static::getRawLanguageConfigOverride($new_langcode, $name);
        $had_override = !$override->isNew();
        if ($had_override) {
          // The override predates the new default and holds deliberately
          // authored values for it - more authoritative than whatever the
          // relabelled configuration currently says. Merge it in before the
          // active configuration is saved, so a subscriber reacting to the
          // override's removal below sees the merged content already in
          // place, not the pre-merge values it is replacing.
          $config->setData(NestedArray::mergeDeepArray([$data, $override->get()], TRUE));
        }
        $config->set('langcode', $new_langcode)->save();
        if ($had_override) {
          // The new default language can no longer have an override of its
          // own: the active configuration just saved above is now its
          // canonical copy.
          $override->delete();
        }
        // Record which names were actually relabelled to the new default, so
        // other batch operations (for example locale module translating
        // them) know what to act on.
        $context['results']['relabelled_names'][] = $name;
      }
    }

    $context['sandbox']['progress'] = ($context['sandbox']['progress'] ?? 0) + count($names);
    $context['finished'] = empty($context['sandbox']['max']) ? 1 : $context['sandbox']['progress'] / $context['sandbox']['max'];
  }

  /**
   * Gets a language override, bypassing the "skip English" shortcut.
   *
   * LanguageConfigFactoryOverride::getStorage() substitutes an empty storage
   * for 'en' when English is both negotiated and default, assuming no 'en'
   * override could exist. That assumption breaks exactly here: switching
   * back to English is when an 'en' override left by an earlier switch away
   * from it needs to be found.
   *
   * @param string $langcode
   *   The langcode of the override to get.
   * @param string $name
   *   The configuration object name.
   *
   * @return \Drupal\language\Config\LanguageConfigOverride
   *   The language override object, reflecting whatever is actually in
   *   storage.
   */
  protected static function getRawLanguageConfigOverride(string $langcode, string $name): LanguageConfigOverride {
    $storage = \Drupal::service('config.storage')->createCollection('language.' . $langcode);
    $override = new LanguageConfigOverride($name, $storage, \Drupal::service(TypedConfigManagerInterface::class), \Drupal::service('event_dispatcher'));
    $data = $storage->read($name);
    if (!empty($data)) {
      $override->initWithData($data);
    }
    return $override;
  }

}
