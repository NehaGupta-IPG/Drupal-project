<?php

declare(strict_types=1);

namespace Drupal\Core\Config\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigLangcodeResolveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Resolves configuration langcode to the site's default language.
 *
 * The site's default language (system.site:default_langcode) is the single
 * source of truth for what langcode configuration with none of its own
 * should get - unless something more specific already decided, such as a
 * module with its own default-langcode policy. Registered at the lowest
 * priority, so this only ever runs at all once nothing more specific has:
 * \Drupal\Core\Config\ConfigLangcodeResolveEvent::setLangcode() stops
 * propagation as soon as an earlier subscriber resolves, meaning this one is
 * simply never called.
 */
class ConfigLangcodeSiteDefaultSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Resolves langcode to the site default.
   */
  public function onResolveLangcode(ConfigLangcodeResolveEvent $event): void {
    $event->setLangcode($this->getSiteDefaultLangcode());
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
