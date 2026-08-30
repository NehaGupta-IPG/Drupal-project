<?php

declare(strict_types=1);

namespace Drupal\locale\EventSubscriber;

use Drupal\language\Event\LanguageDefaultLangcodeChangeEvent;
use Drupal\language\LanguageEvents;
use Drupal\locale\LocaleConfigManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Translates configuration relabelled by a default language change.
 *
 * @see \Drupal\language\DefaultLangcodeConfigUpdater
 */
class LanguageDefaultLangcodeChangeSubscriber implements EventSubscriberInterface {

  /**
   * Adds a batch operation to translate whatever the sweep relabels.
   */
  public function onDefaultLangcodeChange(LanguageDefaultLangcodeChangeEvent $event): void {
    $event->addOperation([static::class, 'translateRelabelledConfigOperation'], [$event->getNewLangcode()]);
  }

  /**
   * Batch operation: translates configuration the sweep just relabelled.
   *
   * Runs after \Drupal\language\DefaultLangcodeConfigUpdater's own operation
   * completes, so $context['results']['relabelled_names'] holds every name
   * it relabelled to the new default. Applying translations here reuses
   * LocaleConfigManager::updateConfigTranslations(), the same mechanism that
   * already merges real translated strings into a config object's active
   * record when the target langcode matches the object's own langcode -
   * which, having just been relabelled, it now does.
   *
   * @param string $new_langcode
   *   The new default langcode.
   * @param array|\ArrayAccess $context
   *   The batch context.
   */
  public static function translateRelabelledConfigOperation(string $new_langcode, &$context): void {
    $names = $context['results']['relabelled_names'] ?? [];
    if (!empty($names)) {
      \Drupal::service(LocaleConfigManager::class)->updateConfigTranslations($names, [$new_langcode]);
    }
    $context['finished'] = 1;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      LanguageEvents::DEFAULT_LANGCODE_CHANGE => 'onDefaultLangcodeChange',
    ];
  }

}
