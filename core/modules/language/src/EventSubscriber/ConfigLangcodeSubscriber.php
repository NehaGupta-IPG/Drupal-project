<?php

declare(strict_types=1);

namespace Drupal\language\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigLangcodeContext;
use Drupal\Core\Config\ConfigLangcodeResolveEvent;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Rejects extension-shipped configuration in an unsupported language.
 *
 * Supplying a default langcode when none was shipped is core's job (the
 * site's default language needs no module at all) - see
 * \Drupal\Core\Config\EventSubscriber\ConfigLangcodeSiteDefaultSubscriber.
 * This only handles the part core cannot: an extension can ship
 * configuration with an explicit langcode of its own, and only language
 * module knows which languages are actually configured on this site.
 */
class ConfigLangcodeSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected readonly LanguageManagerInterface $languageManager,
  ) {}

  /**
   * Rejects an explicit shipped langcode this site does not support.
   */
  public function onResolveLangcode(ConfigLangcodeResolveEvent $event): void {
    if ($event->getContext() !== ConfigLangcodeContext::ExtensionInstall) {
      return;
    }
    $current = $event->getCurrentLangcode();
    if ($current === NULL || $current === 'en') {
      // Missing or merely-English: core's subscriber handles this case.
      return;
    }
    if (!array_key_exists($current, $this->languageManager->getLanguages())) {
      $event->reject("The '$current' language is not supported on this site.");
    }
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
