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
 * should get - unless something more specific already decided, such as a
 * module with its own default-langcode policy: this always attempts to
 * resolve, but \Drupal\Core\Config\ConfigLangcodeResolveEvent::setLangcode()
 * itself enforces that the first subscriber to resolve wins, so an earlier
 * decision is never overwritten here. It is also always supported, so this
 * vouches for it regardless - even where language module is not installed
 * to vouch for anything else. English is not vouched for separately: a
 * missing or merely-English langcode is always resolved to the site
 * default below instead, so 'en' only ever needs to be supported when it
 * also happens to be that default.
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
    $site_default_langcode = $this->getSiteDefaultLangcode();
    // Vouch for the site default regardless of whether anything below
    // resolves to it: shipped configuration can perfectly validly already
    // carry an explicit langcode that happens to match it - nothing else
    // ever vouches for that case.
    $event->allowLangcode($site_default_langcode);

    $current = $event->getCurrentLangcode();

    if ($event->getContext() === ConfigLangcodeContext::Runtime) {
      // Runtime creation only ever asks when nothing was supplied at all.
      if ($current === NULL) {
        $event->setLangcode($site_default_langcode);
      }
      return;
    }

    // ConfigLangcodeContext::ExtensionInstall: extension-shipped default
    // configuration is assumed to be English unless it ships with its own
    // explicit langcode.
    if ($current === NULL || $current === 'en') {
      $event->setLangcode($site_default_langcode);
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
