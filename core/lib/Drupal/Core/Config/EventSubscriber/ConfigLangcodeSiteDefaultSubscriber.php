<?php

declare(strict_types=1);

namespace Drupal\Core\Config\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigLangcodeContext;
use Drupal\Core\Config\ConfigLangcodeResolveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Resolves configuration langcode to the site's default language.
 *
 * The site's default language (system.site:default_langcode) is the single
 * source of truth for what langcode configuration with none of its own
 * should get.
 *
 * @see \Drupal\language\EventSubscriber\ConfigLangcodeSubscriber
 */
class ConfigLangcodeSiteDefaultSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Resolves langcode to the site default.
   */
  public function onResolveLangcode(ConfigLangcodeResolveEvent $event): void {
    $current = $event->getCurrentLangcode();

    if ($event->getContext() === ConfigLangcodeContext::Runtime) {
      // Runtime creation only ever asks when nothing was supplied at all.
      if ($current === NULL) {
        $event->setLangcode($this->getSiteDefaultLangcode());
      }
      return;
    }

    // ConfigLangcodeContext::ExtensionInstall: extension-shipped default
    // configuration is assumed to be English unless it ships with its own
    // explicit langcode.
    if ($current === NULL || $current === 'en') {
      $event->setLangcode($this->getSiteDefaultLangcode());
    }
  }

  /**
   * Gets the site's default language.
   *
   * @return string
   *   The langcode.
   */
  protected function getSiteDefaultLangcode(): string {
    return $this->configFactory->get('system.site')->get('default_langcode') ?? 'en';
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ConfigEvents::RESOLVE_LANGCODE => ['onResolveLangcode', -10000],
    ];
  }

}
