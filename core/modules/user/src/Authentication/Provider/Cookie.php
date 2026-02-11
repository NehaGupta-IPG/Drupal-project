<?php

declare(strict_types=1);

namespace Drupal\user\Authentication\Provider;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\EventSubscriber\RedirectResponseSubscriber;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\UserSession;
use Drupal\Core\Session\SessionConfigurationInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie as ResponseCookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
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
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Indicates if we need to verify if cookies are enabled.
   *
   * Cookie Authentication will not work if cookies are disabled.
   *
   * @var bool
   *
   * @see Cookie::applies()
   * @see \Cookie::onKernelRequestVerifyCookies()
   */
  private $verifyCookies = FALSE;

  /**
   * The redirect response subscriber.
   *
   * @var \Drupal\Core\EventSubscriber\RedirectResponseSubscriber
   */
  protected RedirectResponseSubscriber $redirectResponseSubscriber;

  /**
   * Constructs a new cookie authentication provider.
   *
   * @param \Drupal\Core\Session\SessionConfigurationInterface $session_configuration
   *   The session configuration.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\EventSubscriber\RedirectResponseSubscriber $redirect_response_subscriber
   *   The redirect response subscriber.
   */
  public function __construct(SessionConfigurationInterface $session_configuration, Connection $connection, MessengerInterface $messenger, RedirectResponseSubscriber $redirect_response_subscriber) {
    $this->sessionConfiguration = $session_configuration;
    $this->connection = $connection;
    $this->messenger = $messenger;
    $this->redirectResponseSubscriber = $redirect_response_subscriber;
  }

  /**
   * {@inheritdoc}
   *
   * @see Cookie::addCheckToUrl()
   *   Where the `check_logged_in` query parameter is set.
   * @see Cookie::onKernelRequestVerifyCookies()
   *   Event Listener that acts on verifying cookie functionality.
   */
  public function applies(Request $request): bool {
    $applies = $this->sessionConfiguration->hasSession($request);
    if (!$applies && $request->query->has('check_logged_in')) {
      // If there is no session for this request, but there is a query parameter
      // indicating the user has logged in, we need to verify that cookies are
      // enabled.
      $this->verifyCookies = TRUE;
    }
    return $applies;
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request) {
    return $this->getUserFromSession($request->getSession());
  }

  /**
   * Returns the UserSession object for the given session.
   *
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
   *   The session.
   *
   * @return \Drupal\Core\Session\AccountInterface|null
   *   The UserSession object for the current user, or NULL if this is an
   *   anonymous session.
   */
  protected function getUserFromSession(SessionInterface $session) {
    if ($uid = $session->get('uid')) {
      // @todo Load the User entity in SessionHandler so we don't need queries.
      // @see https://www.drupal.org/node/2345611
      $values = $this->connection
        ->query('SELECT * FROM {users_field_data} [u] WHERE [u].[uid] = :uid AND [u].[default_langcode] = 1', [':uid' => $uid])
        ->fetchAssoc();

      // Check if the user data was found and the user is active.
      if (!empty($values) && $values['status'] == 1) {
        // Add the user's roles.
        $rids = $this->connection
          ->query('SELECT [roles_target_id] FROM {user__roles} WHERE [entity_id] = :uid', [':uid' => $values['uid']])
          ->fetchCol();
        $values['roles'] = array_merge([AccountInterface::AUTHENTICATED_ROLE], $rids);

        return new UserSession($values);
      }
    }

    // This is an anonymous session.
    return NULL;
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
   * Kernel Request Event listener that verifies cookies are enabled if needed.
   *
   * The `applies()` method will set the `verifyCookie` property to TRUE if
   * there is a `check_logged_in` query parameter on the request, which
   * indicates a user has previously logged in but there is no session for the
   * request. There are two possible situations where this may occur:
   * 1. Cookies are disabled or
   * 2. Cookies are enabled and a user has accessed the url containing the
   *    `check_logged_in` query parameter directly while not logged in.
   *
   * If it is situation #1 we need to display a helpful message to the user
   * to inform them that cookies need to be enabled to log in. We do not want
   * to display this message in situation #2 as it is incorrect and confusing.
   *
   * To determine if cookies are disabled, this method:
   *  - Redirects to the `user/verify-cookies` page which checks if a cookie
   *    added by this method is present. If the cookie isn't present, this page
   *    assumes cookies are disabled and displays an error message. If the
   *    cookie is present the user is redirected back to the original request.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The Event to process.
   *
   * @see Cookie::applies()
   * @see \Drupal\user\Controller\UserAuthenticationController::verifyCookiesEnabled()
   */
  public function onKernelRequestVerifyCookies(RequestEvent $event): void {
    if (!$this->verifyCookies) {
      return;
    }
    $this->verifyCookies = FALSE;
    // Work with a clone so we’re not mutating the original request object.
    $original_request = clone $event->getRequest();
    // Remove the `check_logged_in` parameter so it is not checked again.
    $original_request->query->remove('check_logged_in');
    // Redirect the user to the `user.verify_cookies` route but also add a
    // destination query parameter to redirect the user back to the original
    // request if cookies are enabled.
    $query_params = $original_request->query->all();
    $destination_url = Url::fromUri(
      'internal:' . $original_request->getPathInfo(),
      ['query' => $query_params]
    )->toString();
    $verify_cookies_url = Url::fromRoute('user.verify_cookies', [], ['query' => ['destination' => $destination_url]]);
    $response = new LocalRedirectResponse($verify_cookies_url->toString());
    $cookie = ResponseCookie::create('verify_cookies', '1');
    $response->headers->setCookie($cookie);
    $event->setResponse($response);
    // In case the original request contains a destination parameter, set
    // RedirectResponseSubscriber::$ignoreDestination to TRUE. This will
    // ensure the user is redirected to the verify_cookies page and not the
    // original request's destination.
    $this->redirectResponseSubscriber->setIgnoreDestination();
    // We need to kill the page cache, so that the redirect works more
    // than just the first time. Otherwise, subsequent requests will
    // never hit this method.
    \Drupal::service('page_cache_kill_switch')->trigger();
  }

  /**
   * Registers the methods in this class that should be listeners.
   *
   * @return array
   *   An array of event listener definitions.
   */
  public static function getSubscribedEvents(): array {
    // Add the kernel request listener immediately after
    // AuthenticationSubscriber::onKernelRequestAuthenticate() which will call
    // the applies() method and may set the `verifyCookies` property to TRUE.
    $events[KernelEvents::REQUEST][] = ['onKernelRequestVerifyCookies', 299];
    $events[KernelEvents::RESPONSE][] = ['addCheckToUrl', -1000];
    return $events;
  }

}
