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
final readonly class LogoutFinalizer {

  public function __construct(
    protected AccountProxyInterface $accountProxy,
    protected SessionManagerInterface $sessionManager,
    protected ModuleHandlerInterface $moduleHandler,
    #[Autowire(service: 'logger.channel.user')]
    protected LoggerInterface $logger,
    protected RequestStack $requestStack,
  ) {}

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
