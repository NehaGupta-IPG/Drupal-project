<?php

declare(strict_types=1);

namespace Drupal\locale\EventSubscriber;

use Drupal\language\Event\LanguageDefaultLangcodeChangeEvent;
use Drupal\locale\LocaleConfigManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Translates configuration relabelled by a default language change.
 *
 * @see \Drupal\language\DefaultLangcodeConfigUpdater
 */
class LanguageDefaultLangcodeChangeSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected LocaleConfigManager $localeConfigManager,
  ) {
  }

  /**
   * Adds a batch operation to translate whatever the sweep relabels.
   */
  public function onDefaultLangcodeChange(LanguageDefaultLangcodeChangeEvent $event): void {
    $event->addOperation(
      static::class . ':translateRelabelledConfigOperation',
      [$event->newLangcode]
    );
  }

  /**
   * Batch operation: translates configuration the sweep just relabelled.
   *
   * Runs after \Drupal\language\DefaultLangcodeConfigUpdater's own operation
   * completes, so $context['results']['relabelled_names'] holds every name
   * it relabelled to the new default, and its pre-change content already
   * saved as an override for the previous default. Applying translations
   * here reuses LocaleConfigManager::updateConfigTranslations(), the same
   * mechanism that already merges real translated strings into a config
   * object's active record when the target langcode matches the object's
   * own langcode - which, having just been relabelled, it now does.
   *
   * Only the new default is passed: updateConfigTranslations() would happily
   * accept the previous one too, but asking it for a translation "into" that
   * langcode is not what's wanted here - it would look up locale's stored
   * translation of the shipped default text, not preserve whatever the
   * configuration's real content actually was, which is what
   * DefaultLangcodeConfigUpdater's own override save already did.
   *
   * @param string $new_langcode
   *   The new default langcode.
   * @param array|\ArrayAccess $context
   *   The batch context.
   */
  public function translateRelabelledConfigOperation(string $new_langcode, &$context): void {
    $names = $context['results']['relabelled_names'] ?? [];
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
