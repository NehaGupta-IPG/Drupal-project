<?php

declare(strict_types=1);

namespace Drupal\Core\Mailer;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RenderableInterface;
use Symfony\Component\Mime\Address;

/**
 * Interface for email plugins.
 */
interface EmailPluginInterface extends PluginInspectionInterface, ContainerFactoryPluginInterface {

  /**
   * Returns the translated plugin label.
   */
  public function label(): string;

  /**
   * Returns the translated plugin description.
   */
  public function getDescription(): string;

  /**
   * Returns the email HTML body.
   */
  public function htmlBody(): RenderableInterface|array|null;

  /**
   * Returns the email text body.
   */
  public function textBody(): RenderableInterface|\Stringable|array|null;

  /**
   * Performs pre-build operations.
   */
  public function preBuild(): void;

  /**
   * Returns all email headers.
   */
  public function getHeaders(): array;

  /**
   * Returns a specific header value.
   *
   * @param string $key
   *   The header key.
   *
   * @return mixed
   *   The header value or NULL if not set.
   */
  public function getHeader(string $key): mixed;

  /**
   * Sets a single header.
   *
   * @param string $key
   *   The header key.
   * @param mixed $value
   *   The header value.
   *
   * @return static
   */
  public function setHeader(string $key, mixed $value): static;

  /**
   * Sets multiple headers at once.
   *
   * @param array $headers
   *   An associative array of headers.
   *
   * @return static
   */
  public function setHeaders(array $headers): static;

  /**
   * Returns the email environment.
   */
  public function getEnvironment(): array;

  /**
   * Sets an environment parameter.
   *
   * @param string $key
   *   The parameter key.
   * @param mixed $value
   *   The parameter value.
   *
   * @return static
   */
  public function setEnvironmentParameter(string $key, mixed $value): static;

  /**
   * Sets the entire environment.
   *
   * @param array $environment
   *   An associative array of environment parameters.
   *
   * @return static
   */
  public function setEnvironment(array $environment): static;

  /**
   * Returns all email parameters.
   */
  public function getParameters(): array;

  /**
   * Sets multiple email parameters.
   *
   * @param array $parameters
   *   An associative array of parameters.
   *
   * @return static
   */
  public function setParameters(array $parameters): static;

  /**
   * Returns a specific parameter value.
   *
   * @param string $key
   *   The parameter key.
   *
   * @return mixed
   *   The parameter value or NULL if not set.
   */
  public function getParameter(string $key): mixed;

  /**
   * Sets a single parameter.
   *
   * @param string $key
   *   The parameter key.
   * @param mixed $value
   *   The parameter value.
   *
   * @return static
   */
  public function setParameter(string $key, mixed $value): static;

  /**
   * Returns the language code.
   */
  public function getLangcode(): ?string;

  /**
   * Sets the email subject.
   *
   * @param \Stringable|string $subject
   *   The subject value.
   *
   * @return static
   */
  public function subject(\Stringable|string $subject): static;

  /**
   * Gets the email subject.
   *
   * @return \Stringable|string|null
   *   The email subject
   */
  public function getSubject(): \Stringable|string|null;

  /**
   * Sets the To recipients.
   *
   * @param \Symfony\Component\Mime\Address|string ...$addresses
   *   The recipient addresses.
   *
   * @return static
   */
  public function to(Address|string ...$addresses): static;

  /**
   * Gets the email To recipients.
   *
   * @return array|null
   *   The email To recipients
   */
  public function getTo(): ?array;

  /**
   * Sets the Cc recipients.
   *
   * @param \Symfony\Component\Mime\Address|string ...$addresses
   *   The recipient addresses.
   *
   * @return static
   */
  public function cc(Address|string ...$addresses): static;

  /**
   * Sets the Bcc recipients.
   *
   * @param \Symfony\Component\Mime\Address|string ...$addresses
   *   The recipient addresses.
   *
   * @return static
   */
  public function bcc(Address|string ...$addresses): static;

  /**
   * Sets the Reply-To address.
   *
   * @param \Symfony\Component\Mime\Address|string ...$addresses
   *   The address(es).
   *
   * @return static
   */
  public function replyTo(Address|string ...$addresses): static;

  /**
   * Sets the Sender address.
   *
   * @param \Symfony\Component\Mime\Address|string $address
   *   The sender address.
   *
   * @return static
   */
  public function sender(Address|string $address): static;

  /**
   * Sets the From address.
   *
   * @param \Symfony\Component\Mime\Address|string $address
   *   The from address.
   *
   * @return static
   */
  public function from(Address|string $address): static;

  /**
   * Sets the Content-Type header.
   *
   * @param string $content_type
   *   The content type value.
   *
   * @return static
   */
  public function contentType(string $content_type): static;

  /**
   * Sets the language code.
   *
   * @param string $langcode
   *   The language code.
   *
   * @return static
   */
  public function langcode(string $langcode): static;

  /**
   * Sets whether the email should be sent.
   *
   * @param bool $send
   *   Whether to send the email.
   *
   * @return static
   */
  public function setSend(bool $send): static;

  /**
   * Returns whether the email should be sent.
   */
  public function shouldSend(): bool;

}
