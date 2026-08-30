<?php

namespace Drupal\user\Authentication\Provider;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\SessionConfigurationInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Cookie based authentication provider.
 */
class Cookie implements AuthenticationProviderInterface, EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The session configuration.
   *
   * @var \Drupal\Core\Session\SessionConfigurationInterface
   */
  protected $sessionConfiguration;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new cookie authentication provider.
   *
   * @param \Drupal\Core\Session\SessionConfigurationInterface $session_configuration
   *   The session configuration.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The Entity Type Manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(SessionConfigurationInterface $session_configuration, EntityTypeManagerInterface $entityTypeManager, MessengerInterface $messenger) {
    $this->sessionConfiguration = $session_configuration;
    $this->entityTypeManager = $entityTypeManager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request) {
    $applies = $this->sessionConfiguration->hasSession($request);
    if (!$applies && $request->query->has('check_logged_in')) {
      $domain = ltrim(ini_get('session.cookie_domain'), '.') ?: $request->getHttpHost();
      $this->messenger->addMessage($this->t('To log in to this site, your browser must accept cookies from the domain %domain.', ['%domain' => $domain]), 'error');
    }
    return $applies;
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request) {
    if ($uid = $request->getSession()->get('uid')) {
      /** @var \Drupal\user\UserInterface $user */
      if ($user = $this->entityTypeManager->getStorage('user')->load($uid)) {
        if ($user->isActive()) {
          return $user;
        }
      }
    }
  }

  /**
   * Adds a query parameter to check successful log in redirect URL.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The Event to process.
   */
  public function addCheckToUrl(ResponseEvent $event) {
    $response = $event->getResponse();
    if ($response instanceof RedirectResponse) {
      if ($event->getRequest()->getSession()->has('check_logged_in')) {
        $event->getRequest()->getSession()->remove('check_logged_in');
        $url = $response->getTargetUrl();
        $options = UrlHelper::parse($url);
        $options['query']['check_logged_in'] = '1';
        $url = $options['path'] . '?' . UrlHelper::buildQuery($options['query']);
        if (!empty($options['fragment'])) {
          $url .= '#' . $options['fragment'];
        }
        // In the case of trusted redirect, we have to update the list of
        // trusted URLs because here we've just modified its target URL
        // which is in the list.
        if ($response instanceof TrustedRedirectResponse) {
          $response->setTrustedTargetUrl($url);
        }
        $response->setTargetUrl($url);
      }
    }
  }

  /**
   * Registers the methods in this class that should be listeners.
   *
   * @return array
   *   An array of event listener definitions.
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::RESPONSE][] = ['addCheckToUrl', -1000];
    return $events;
  }

}
