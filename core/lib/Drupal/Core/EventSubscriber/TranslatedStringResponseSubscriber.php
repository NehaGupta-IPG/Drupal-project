<?php

namespace Drupal\Core\EventSubscriber;

use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\StringTranslation\TranslationManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Detects cacheable responses that output translated strings.
 *
 * Responses with translated strings (output of t()) must vary by the
 * languages:language_interface cache context. Rendering adds it automatically,
 * but responses built without rendering do not get it.
 *
 * This subscriber tracks whether a translation was resolved during the
 * controller execution and, on the response event, throws an exception when a
 * cacheable response lacks that cache context.
 */
class TranslatedStringResponseSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new TranslatedStringResponseSubscriber object.
   *
   * @param \Drupal\Core\StringTranslation\TranslationManager $string_translation
   *   The string translation service.
   */
  public function __construct(protected TranslationManager $string_translation) {
  }

  /**
   * Resets translated strings tracking before the controller runs.
   *
   * @param \Symfony\Component\HttpKernel\Event\ControllerEvent $event
   *   The controller event.
   */
  public function onController(ControllerEvent $event): void {
    $this->string_translation->resetTranslationTracking();
  }

  /**
   * Ensures cacheable responses outputting translations vary by language.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   *
   * @throws \LogicException
   *   When the response is cacheable, contains translated strings and does not
   *   have the languages:language_interface cache context.
   */
  public function onResponse(ResponseEvent $event): void {
    $response = $event->getResponse();
    if (!$response instanceof CacheableResponseInterface) {
      return;
    }

    if (!$this->string_translation->translatedStringsWereResolved()) {
      return;
    }

    $cache_contexts = $response->getCacheableMetadata()->getCacheContexts();
    if (in_array('languages:language_interface', $cache_contexts, TRUE)) {
      return;
    }

    throw new \LogicException('The response claims to contain translated strings, but does not provide the "languages:language_interface" cache context. Responses that output translated strings must vary by interface language.');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::CONTROLLER][] = ['onController', 10];
    // Run after AjaxResponseSubscriber, which merges the cacheability of the
    // AJAX commands into the response.
    $events[KernelEvents::RESPONSE][] = ['onResponse', -150];

    return $events;
  }

}
