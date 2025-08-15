<?php

declare(strict_types=1);

namespace Drupal\Core\Mailer;

use Symfony\Component\Mime\Address;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Utility\Error;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Email;

/**
 * Default email builder.
 */
class EmailBuilder implements EmailBuilderInterface {

  /**
   * Constructs a new email builder.
   *
   * @param \Closure():\Drupal\Core\Mailer\EmailRendererInterface $emailRenderer
   *   Email renderer service closure.
   * @param \Closure():\Drupal\Core\Mailer\EmailManagerInterface $emailManager
   *   Email manager service closure.
   * @param \Closure():\Symfony\Component\Mailer\MailerInterface $mailer
   *   The mailer service closure.
   * @param \Closure():\Psr\Log\LoggerInterface $logger
   *   The logger service closure.
   * @param string $id
   *   Email id.
   * @param string $langcode
   *   Email language code.
   * @param array $params
   *   All email parameters.
   * @param array<\Symfony\Component\Mime\Address|string> $to
   *   The email recipients.
   * @param array<\Symfony\Component\Mime\Address|string> $cc
   *   The email cc recipients.
   * @param array<\Symfony\Component\Mime\Address|string> $bcc
   *   The email bcc recipients.
   * @param array<\Symfony\Component\Mime\Address|string> $replyTo
   *   The email reply-to addresses.
   *
   * @internal Use EmailManagerInterface::getEmailBuilder() instead.
   */
  public function __construct(
    protected \Closure $emailRenderer,
    protected \Closure $emailManager,
    protected \Closure $mailer,
    protected \Closure $logger,
    protected string $id = '',
    protected string $langcode = '',
    protected array $params = [],
    protected array $to = [],
    protected array $cc = [],
    protected array $bcc = [],
    protected array $replyTo = [],
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function id(string $id): static {
    $this->id = $id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function langcode(string $langcode): static {
    $this->langcode = $langcode;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function params(array $params): static {
    $this->params = $params;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function param(string $name, mixed $value = NULL): static {
    if (isset($value)) {
      $this->params[$name] = $value;
    }
    else {
      unset($this->params[$name]);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function to(Address|string ...$addresses): static {
    $this->to = $addresses;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function cc(Address|string ...$addresses): static {
    $this->cc = $addresses;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function bcc(Address|string ...$addresses): static {
    $this->bcc = $addresses;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function replyTo(Address|string ...$addresses): static {
    $this->replyTo = $addresses;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function build(): Email {
    if (empty($this->id)) {
      throw new \InvalidArgumentException('Email builders must explicitly set the email id. See Drupal\Core\Mailer\EmailBuilderInterface::id().');
    }
    if (empty($this->langcode)) {
      throw new \InvalidArgumentException('Email builders must explicitly set the email langcode. See Drupal\Core\Mailer\EmailBuilderInterface::langcode().');
    }

    $definition = ($this->emailManager)()->getEmailDefinition($this->id);
    $email = ($this->emailRenderer)()->render($definition, $this->params, $this->langcode);
    if (!empty($this->to)) {
      $email->to(...$this->to);
    }
    if (!empty($this->cc)) {
      $email->cc(...$this->cc);
    }
    if (!empty($this->bcc)) {
      $email->bcc(...$this->bcc);
    }
    if (!empty($this->replyTo)) {
      $email->replyTo(...$this->replyTo);
    }
    return $email;
  }

  /**
   * {@inheritdoc}
   */
  public function send(
    ?\Closure $logger = NULL,
  ): bool {
    $result = FALSE;
    try {
      ($this->mailer)()->send($this->build());
      $result = TRUE;
    }
    catch (\InvalidArgumentException | PluginException | TransportException $e) {
      Error::logException($logger ? $logger() : ($this->logger)(), $e);
    }
    return $result;
  }

}
