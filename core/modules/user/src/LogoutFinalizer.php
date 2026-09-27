<?php

declare(strict_types=1);

namespace Drupal\user;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Session\SessionManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Finalize user logout.
 */
final class LogoutFinalizer {

  public function __construct(
    readonly protected AccountProxyInterface $accountProxy,
    #[Autowire(service: RequestStack::class)]
    protected RequestStack|SessionManagerInterface $requestStack,
    readonly protected ModuleHandlerInterface $moduleHandler,
    #[Autowire(service: 'logger.channel.user')]
    readonly protected LoggerInterface $logger,
  ) {
    if ($requestStack instanceof SessionManagerInterface) {
      @trigger_error('Calling ' . __METHOD__ . '() with a $sessionManager argument is deprecated in drupal:11.5.0 and it will throw an error in drupal:14.0.0. Pass $requestStack instead. See https://www.drupal.org/node/3570844', E_USER_DEPRECATED);
      $this->requestStack = \Drupal::requestStack();
    }
  }

  /**
   * Logs the current user out.
   */
  public function finalizeLogout(): void {
    $this->logger->info('Session closed for %name.', ['%name' => $this->accountProxy->getAccountName()]);

    $this->moduleHandler->invokeAll('user_logout', [$this->accountProxy]);

    $this->requestStack->getSession()->invalidate();
    $this->accountProxy->setAccount(new AnonymousUserSession());
  }

}
