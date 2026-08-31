<?php

declare(strict_types=1);

namespace Drupal\language\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigLangcodeResolveEvent;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Vouches for every language configured on this site.
 *
 * Core alone can only ever vouch for English and the site's own default -
 * see \Drupal\Core\Config\EventSubscriber\ConfigLangcodeSiteDefaultSubscriber.
 * Only language module knows the full list of languages actually configured,
 * so extension-shipped configuration in any other explicit language is
 * unsupported - and therefore rejected - unless this runs.
 */
class ConfigLangcodeSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected readonly LanguageManagerInterface $languageManager,
  ) {}

  /**
   * Vouches for every configured language.
   */
  public function onResolveLangcode(ConfigLangcodeResolveEvent $event): void {
    $event->allowLangcodes(array_keys($this->languageManager->getLanguages()));
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ConfigEvents::RESOLVE_LANGCODE => ['onResolveLangcode', 0],
    ];
  }

}
