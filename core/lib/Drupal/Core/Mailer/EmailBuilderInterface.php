<?php

declare(strict_types=1);

namespace Drupal\Core\Mailer;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Defines an interface for email builders.
 */
interface EmailBuilderInterface {

  /**
   * Sets or replaces the email id (required).
   */
  public function id(string $id): static;

  /**
   * Sets or replaces the language code (required).
   */
  public function langcode(string $langcode): static;

  /**
   * Sets or replaces all email parameters.
   *
   * @param mixed[] $params
   *   Key value pairs representing all parameters.
   */
  public function params(array $params): static;

  /**
   * Sets or replaces one email parameter.
   */
  public function param(string $name, mixed $value = NULL): static;

  /**
   * Sets or replaces the recipients.
   */
  public function to(Address|string ...$addresses): static;

  /**
   * Sets or replaces the Cc recipients.
   */
  public function cc(Address|string ...$addresses): static;

  /**
   * Sets or replaces the Bcc recipients.
   */
  public function bcc(Address|string ...$addresses): static;

  /**
   * Sets or replaces the Reply-To addresses.
   */
  public function replyTo(Address|string ...$addresses): static;

  /**
   * Renders and returns the built email.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If email plugin was not found or if instance couldn't be constructed.
   */
  public function build(): Email;

  /**
   * Renders and sends the built email.
   *
   * @param (\Closure():\Psr\Log\LoggerInterface)|null $logger
   *   The logger closure to use when an error occures.
   *
   * @return bool
   *   TRUE when a mail has been queued successfully, FALSE otherwise.
   */
  public function send(?\Closure $logger = NULL): bool;

}
