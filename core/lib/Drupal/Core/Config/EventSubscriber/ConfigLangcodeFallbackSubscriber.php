<?php

declare(strict_types=1);

namespace Drupal\Core\Config\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigLangcodeResolveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Resolves configuration langcode to English as a last resort.
 *
 * Runs at the lowest priority so any other subscriber gets a chance to
 * resolve or reject the langcode first.
 */
class ConfigLangcodeFallbackSubscriber implements EventSubscriberInterface {

  /**
   * Sets the langcode to English if nothing else has resolved one.
   */
  public function onResolveLangcode(ConfigLangcodeResolveEvent $event): void {
    if ($event->getLangcode() === NULL) {
      $event->setLangcode('en');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ConfigEvents::RESOLVE_LANGCODE => ['onResolveLangcode', -1000],
    ];
  }

}
