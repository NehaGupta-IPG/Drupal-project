<?php

declare(strict_types=1);

namespace Drupal\locale\EventSubscriber;

use Drupal\language\Event\DefaultLangcodeConfigReconcileEvent;
use Drupal\locale\LocaleConfigManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Translates configuration being updated to a new default langcode.
 */
class DefaultLangcodeConfigReconcileSubscriber implements EventSubscriberInterface {

  public function __construct(protected readonly LocaleConfigManager $localeConfigManager) {
  }

  /**
   * Translates the configuration data into the new default langcode.
   *
   * @param \Drupal\language\Event\DefaultLangcodeConfigReconcileEvent $event
   *   The event.
   */
  public function onReconcile(DefaultLangcodeConfigReconcileEvent $event): void {
    $event->setData($this->localeConfigManager->getDataForDefaultLangcodeChange(
      $event->name,
      $event->getData(),
      $event->newLangcode,
      $event->untranslatedKeys,
    ));
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[DefaultLangcodeConfigReconcileEvent::class][] = ['onReconcile'];
    return $events;
  }

}
