<?php

declare(strict_types=1);

namespace Drupal\Core\Mailer;

use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;
use Symfony\Component\Mailer\MailerInterface;

/**
 * Provides the email builder factory.
 */
class EmailFactory implements EmailFactoryInterface {

  /**
   * Constructs a new email builder.
   *
   * @param \Closure():\Drupal\Component\Plugin\Factory\FactoryInterface $emailManager
   *   Email manager service.
   * @param \Closure():\Drupal\Core\Mailer\EmailRendererInterface $emailRenderer
   *   Email renderer service closure.
   * @param \Closure():\Symfony\Component\Mailer\MailerInterface $mailer
   *   The mailer service closure.
   * @param \Closure():\Psr\Log\LoggerInterface $logger
   *   The logger service closure.
   */
  public function __construct(
    #[AutowireServiceClosure('plugin.manager.email')]
    protected readonly \Closure $emailManager,
    #[AutowireServiceClosure(EmailRendererInterface::class)]
    protected readonly \Closure $emailRenderer,
    #[AutowireServiceClosure(MailerInterface::class)]
    protected readonly \Closure $mailer,
    #[AutowireServiceClosure('logger.channel.mail')]
    protected readonly \Closure $logger,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function create(string $id): EmailBuilderInterface {
    return (new EmailBuilder($this->emailManager, $this->emailRenderer, $this->mailer, $this->logger))->id($id);
  }

}
