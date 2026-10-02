<?php

declare(strict_types=1);

namespace Drupal\Core\Config\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigLangcodeResolveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Resolves configuration langcode to the site's default language.
 */
class ConfigLangcodeSiteDefaultSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Resolves langcode to the site default.
   */
  public function onResolveLangcode(ConfigLangcodeResolveEvent $event): void {
    $event->setLangcode($this->configFactory->get('system.site')->get('default_langcode') ?? 'en');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // Registered with a low priority to allow other subscribers to answer
      // first.
      ConfigLangcodeResolveEvent::class => ['onResolveLangcode', -10000],
    ];
  }

}
