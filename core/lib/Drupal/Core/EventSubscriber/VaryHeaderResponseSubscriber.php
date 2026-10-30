<?php

namespace Drupal\Core\EventSubscriber;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponseInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class VaryHeaderResponseSubscriber implements EventSubscriberInterface {

  protected array $vary = [];

  public function addVaryHeader(string $header): void {
    if (!in_array($header, $this->vary)) {
      $this->vary[] = $header;
    }
  }

  public function onRespond(ResponseEvent $event) {
    if (!$event->isMainRequest()) {
      return;
    }

    if ($this->vary) {
      $response = $event->getResponse();
      $response->setVary($this->vary, FALSE);
      if ($response instanceof CacheableResponseInterface) {
        $metadata = new CacheableMetadata();
        $contexts = [];
        foreach ($this->vary as $vary_header) {
          $contexts[] = 'headers:' . $vary_header;
        }
        $metadata->addCacheContexts($contexts);
        $response->addCacheableDependency($metadata);
      }
    }
  }

  public static function getSubscribedEvents(): array {
    /**
     *  Either Drupal\Tests\page_cache\Functional\PageCacheVaryTest::testPageCacheWithVary
     *  fails or Drupal\Tests\language\Functional\LanguageBrowserDetectionAcceptLanguageTest::testAcceptLanguageEmptyDefault
     *  fails if you remove one of those. Not sure why
     */
    $events[KernelEvents::RESPONSE][] = ['onRespond', -100];
    $events[KernelEvents::RESPONSE][] = ['onRespond', 100];
    return $events;
  }

}
