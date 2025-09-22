<?php

namespace Drupal\Core\EventSubscriber;

use Drupal\Core\Authentication\AuthenticationCollectorInterface;
use Drupal\Core\Authentication\AuthenticationProviderChallengeInterface;
use Drupal\Core\Authentication\AuthenticationProviderFilterInterface;
use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Authentication subscriber.
 *
 * Trigger authentication during the request.
 */
class AuthenticationSubscriber implements EventSubscriberInterface {

  /**
   * Authentication provider.
   *
   * @var \Drupal\Core\Authentication\AuthenticationProviderInterface
   */
  protected $authenticationProvider;

  /**
   * Authentication provider filter.
   *
   * @var \Drupal\Core\Authentication\AuthenticationProviderFilterInterface|null
   */
  protected $filter;

  /**
   * Authentication challenge provider.
   *
   * @var \Drupal\Core\Authentication\AuthenticationProviderChallengeInterface|null
   */
  protected $challengeProvider;

  /**
   * Account proxy.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $accountProxy;

  /**
   * Authentication collector.
   *
   * @var \Drupal\Core\Authentication\AuthenticationCollectorInterface
   */
  protected $authenticationCollector;

  /**
   * Constructs an authentication subscriber.
   *
   * @param \Drupal\Core\Authentication\AuthenticationProviderInterface $authentication_provider
   *   An authentication provider.
   * @param \Drupal\Core\Session\AccountProxyInterface $account_proxy
   *   Account proxy.
   * @param \Drupal\Core\Authentication\AuthenticationCollectorInterface $authentication_collector
   *   Authentication collector.
   */
  public function __construct(AuthenticationProviderInterface $authentication_provider, AccountProxyInterface $account_proxy, AuthenticationCollectorInterface $authentication_collector) {
    $this->authenticationProvider = $authentication_provider;
    $this->filter = ($authentication_provider instanceof AuthenticationProviderFilterInterface) ? $authentication_provider : NULL;
    $this->challengeProvider = ($authentication_provider instanceof AuthenticationProviderChallengeInterface) ? $authentication_provider : NULL;
    $this->accountProxy = $account_proxy;
    $this->authenticationCollector = $authentication_collector;
  }

  /**
   * Authenticates user on request.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   *
   * @see \Drupal\Core\Authentication\AuthenticationProviderInterface::authenticate()
   */
  public function onKernelRequestAuthenticate(RequestEvent $event) {
    if ($event->isMainRequest()) {
      $request = $event->getRequest();
      if ($this->authenticationProvider->applies($request)) {
        $account = $this->authenticationProvider->authenticate($request);
        if ($account) {
          $this->accountProxy->setAccount($account);
          return;
        }
      }
    }
  }

  /**
   * Denies access if authentication provider is not allowed on this route.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function onKernelRequestFilterProvider(RequestEvent $event) {
    if (isset($this->filter) && $event->isMainRequest()) {
      $request = $event->getRequest();
      if ($this->authenticationProvider->applies($request) && !$this->filter->appliesToRoutedRequest($request, TRUE)) {
        $message = sprintf('The authentication method %s is not allowed on this route.', $this->getAuthenticationProviderName($request));
        throw new AccessDeniedHttpException($message);
      }
    }
  }

  /**
   * Respond with a challenge on access denied exceptions if appropriate.
   *
   * On a 403 (access denied), if there are no credentials on the request, some
   * authentication methods (e.g. basic auth) require that a challenge is sent
   * to the client.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The exception event.
   */
  public function onExceptionSendChallenge(ExceptionEvent $event) {
    if (isset($this->challengeProvider) && $event->isMainRequest()) {
      $request = $event->getRequest();
      $exception = $event->getThrowable();
      if ($exception instanceof AccessDeniedHttpException && !$this->authenticationProvider->applies($request) && (!isset($this->filter) || $this->filter->appliesToRoutedRequest($request, FALSE))) {
        $challenge_exception = $this->challengeProvider->challengeException($request, $exception);
        if ($challenge_exception) {
          $event->setThrowable($challenge_exception);
        }
      }
    }
  }

  /**
   * Detect disallowed authentication methods on access denied exceptions.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The event.
   */
  public function onExceptionAccessDenied(ExceptionEvent $event) {
    if (isset($this->filter) && $event->isMainRequest()) {
      $request = $event->getRequest();
      $exception = $event->getThrowable();
      if ($exception instanceof AccessDeniedHttpException && $this->authenticationProvider->applies($request) && !$this->filter->appliesToRoutedRequest($request, TRUE)) {
        $message = sprintf('The authentication method %s is not allowed on this route.', $this->getAuthenticationProviderName($request));
        $event->setThrowable(new AccessDeniedHttpException($message, $exception));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // The priority for authentication must be higher than the highest event
    // subscriber accessing the current user. Especially it must be higher than
    // LanguageRequestSubscriber as LanguageManager accesses the current user if
    // the language module is enabled.
    $events[KernelEvents::REQUEST][] = ['onKernelRequestAuthenticate', 300];

    // Access check must be performed after routing.
    $events[KernelEvents::REQUEST][] = ['onKernelRequestFilterProvider', 31];
    $events[KernelEvents::EXCEPTION][] = ['onExceptionSendChallenge', 75];
    $events[KernelEvents::EXCEPTION][] = ['onExceptionAccessDenied', 80];
    return $events;
  }

  /**
   * Gets the name of the authentication provider that applies to the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return string
   *   The authentication provider name if it can be determined,
   *   'unidentified' otherwise.
   */
  protected function getAuthenticationProviderName($request): string {
    // Loop through all registered authentication providers to find the one
    // that applies to this request.
    foreach ($this->authenticationCollector->getSortedProviders() as $provider_id => $provider) {
      if ($provider->applies($request)) {
        return $provider_id;
      }
    }

    return 'unidentified';
  }

}
