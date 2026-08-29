<?php

declare(strict_types=1);

namespace Drupal\language\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigLangcodeContext;
use Drupal\Core\Config\ConfigLangcodeResolveEvent;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Resolves configuration langcode using the site's configured languages.
 */
class ConfigLangcodeSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected readonly LanguageManagerInterface $languageManager,
    protected readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Resolves or rejects a configuration langcode.
   */
  public function onResolveLangcode(ConfigLangcodeResolveEvent $event): void {
    $current = $event->getCurrentLangcode();

    if ($event->getContext() === ConfigLangcodeContext::Runtime) {
      // Runtime creation only ever asks when nothing was supplied at all.
      if ($current === NULL) {
        $event->setLangcode($this->getDefaultConfigLangcode());
      }
      return;
    }

    // ConfigLangcodeContext::ExtensionInstall: extension-shipped default
    // configuration is definitionally English unless it ships with its own
    // explicit langcode.
    if ($current === NULL || $current === 'en') {
      $event->setLangcode($this->getDefaultConfigLangcode());
      return;
    }
    if (!array_key_exists($current, $this->languageManager->getLanguages())) {
      $event->reject("The '$current' language is not supported on this site.");
    }
  }

  /**
   * Gets the site's configuration default language.
   *
   * @return string
   *   The langcode.
   */
  protected function getDefaultConfigLangcode(): string {
    return $this->configFactory->get('language.settings')->get('default_config_langcode') ?? 'en';
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
