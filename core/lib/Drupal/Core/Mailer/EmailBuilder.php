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
   * @param \Closure|null $controller
   *   Controller used to build the email render array.
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
    protected ?\Closure $controller = NULL,
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
  public function controller(?\Closure $controller): static {
    $this->controller = $controller;
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

    $definition = ($this->emailManager)()->getEmailDefinition($this->id, $this->controller);
    return ($this->emailRenderer)()->render($definition, $this->params, $this->langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function send(
    Address|string|array|null $to = NULL,
    Address|string|array|null $cc = NULL,
    Address|string|array|null $bcc = NULL,
    Address|string|array|null $replyTo = NULL,
    ?\Closure $logger = NULL,
  ): bool {
    $result = FALSE;
    try {
      $email = $this->build();
      if (!empty($to)) {
        $email->to(...(is_array($to) ? $to : [$to]));
      }
      if (!empty($cc)) {
        $email->cc(...(is_array($cc) ? $cc : [$cc]));
      }
      if (!empty($bcc)) {
        $email->bcc(...(is_array($bcc) ? $bcc : [$bcc]));
      }
      if (!empty($replyTo)) {
        $email->replyTo(...(is_array($replyTo) ? $replyTo : [$replyTo]));
      }
      ($this->mailer)()->send($email);
      $result = TRUE;
    }
    catch (\InvalidArgumentException | PluginException | TransportException $e) {
      Error::logException($logger ? $logger() : ($this->logger)(), $e);
    }
    return $result;
  }

}
