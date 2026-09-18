<?php

declare(strict_types=1);

namespace Drupal\locale\EventSubscriber;

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
    $names = $context['results']['updated_names'] ?? [];
    if (!empty($names)) {
      $this->localeConfigManager->updateConfigTranslations($names, [$new_langcode]);
    }
    $context['finished'] = 1;
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
