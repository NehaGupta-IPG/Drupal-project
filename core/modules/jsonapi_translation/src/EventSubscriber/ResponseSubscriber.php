<?php

namespace Drupal\jsonapi_translation\EventSubscriber;

use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\jsonapi_translation\Controller\EntityResource;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Response subscriber to handle response language.
 *
 * @internal JSON:API Translation maintains no PHP API. The API is the HTTP API.
 *   This class may change at any time and could break any dependencies on it.
 *
 * @see https://www.drupal.org/project/drupal/issues/3032787
 * @see \Drupal\Core\EventSubscriber\FinishResponseSubscriber
 */
class ResponseSubscriber implements EventSubscriberInterface {

  /**
   * Sets the response language.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The event to process.
   */
  public function onRespond(ResponseEvent $event) {
    $request = $event->getRequest();
    $response = $event->getResponse();

    // Set the response language header based on the resource translation
    // language, if available.
    $translation = $request->attributes->get(EntityResource::ATTR_TRANSLATION_RESOURCE);
    if ($translation instanceof ContentEntityInterface) {
      $langcode = $translation->language()->getId();
      $response->headers->set(EntityResource::HEADER_CONTENT_LANGUAGE, $langcode);
    }

    // If the resource translation was negotiated via the "Accept-Language"
    // header, we also need to set the "Vary" header accordingly, otherwise HTTP
    // caching would not handle independent translations correctly.
    $key = 'Accept-Language';
    if ($response instanceof CacheableResponseInterface && $request->headers->has($key)) {
      $vary = $response->headers->get('Vary');
      $vary .= $vary ? ',' . $key : $key;
      $response->headers->set('Vary', $vary);
    }
  }

  /**
   * Registers the methods in this class that should be listeners.
   *
   * @return array
   *   An array of event listener definitions.
   */
  public static function getSubscribedEvents() {
    // \Drupal\Core\EventSubscriber\FinishResponseSubscriber sets the response
    // language based on the negotiated interface language. We need to override
    // that, if a resource translation is part of the response.
    $events[KernelEvents::RESPONSE][] = ['onRespond', -10];
    return $events;
  }

}
