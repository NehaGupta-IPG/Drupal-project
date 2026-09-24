<?php

declare(strict_types=1);

namespace Drupal\locale\EventSubscriber;

use Drupal\Core\Site\Settings;
use Drupal\language\Event\LanguageDefaultLangcodeChangeEvent;
use Drupal\locale\LocaleConfigManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Translates configuration updated by a default language change.
 *
 * @see \Drupal\language\DefaultLangcodeConfigUpdater
 */
class LanguageDefaultLangcodeChangeSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected LocaleConfigManager $localeConfigManager,
  ) {
  }

  /**
   * Adds a batch operation to translate updated configuration.
   */
  public function onDefaultLangcodeChange(LanguageDefaultLangcodeChangeEvent $event): void {
    $event->addOperation(
      static::class . ':translateUpdatedConfigOperation',
      [$event->newLangcode]
    );
  }

  /**
   * Translates configuration just updated by DefaultLangcodeConfigUpdater.
   *
   * Runs after \Drupal\language\DefaultLangcodeConfigUpdater's own operation
   * completes, so $context['results']['updated_names'] holds every name
   * it updated to the new default, and its pre-change content already
   * saved as an override for the previous default. Applying translations
   * here reuses LocaleConfigManager::updateConfigTranslations(), the same
   * mechanism that already merges real translated strings into a config
   * object's active record when the target langcode matches the object's
   * own langcode - which, having just been updated, it now does.
   *
   * @param string $new_langcode
   *   The new default langcode.
   * @param array|\ArrayAccess $context
   *   The batch context.
   *
   * @see \Drupal\language\DefaultLangcodeConfigUpdater::reconcileConfigLangcodeOperation()
   */
  public function translateUpdatedConfigOperation(string $new_langcode, &$context): void {
    if (!isset($context['sandbox']['names'])) {
      $context['sandbox']['names'] = $context['results']['updated_names'] ?? [];
      $context['sandbox']['max'] = count($context['sandbox']['names']);
    }

    $names = array_splice($context['sandbox']['names'], 0, Settings::get('entity_update_batch_size', 50));
    if (!empty($names)) {
      // Key paths DefaultLangcodeConfigUpdater found no override for. These
      // keys still have value from the previous default language and should
      // fall back to the English value, if it exists.
      $untranslated_keys = array_intersect_key($context['results']['untranslated_keys'] ?? [], array_flip($names));
      $this->localeConfigManager->updateConfigTranslations($names, [$new_langcode], $untranslated_keys);
    }

    $context['sandbox']['progress'] = ($context['sandbox']['progress'] ?? 0) + count($names);
    $context['finished'] = empty($context['sandbox']['max']) ? 1 : $context['sandbox']['progress'] / $context['sandbox']['max'];
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      LanguageDefaultLangcodeChangeEvent::class => 'onDefaultLangcodeChange',
    ];
  }

}
