<?php

declare(strict_types=1);

namespace Drupal\Core\Mailer;

use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Render\RenderableInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Default class used for emails plugins.
 */
class EmailPluginDefault extends PluginBase implements EmailPluginInterface {

  protected array $parameters = [];

  protected array $headers = [];

  protected array|RenderableInterface|null $htmlBody = NULL;

  protected array|RenderableInterface|null $textBody = NULL;

  protected array $environment = [];

  protected ?Email $email = NULL;

  protected bool $send = TRUE;


  /**
   * {@inheritdoc}
   */
  public function label(): string {
    // The title from YAML file discovery may be a TranslatableMarkup object.
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return (string) $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function getModule(): string {
    return (string) $this->pluginDefinition['module'];
  }

  /**
   * {@inheritdoc}
   */
  public function getKey(): string {
    return (string) $this->pluginDefinition['key'];
  }

/**
   * {@inheritdoc}
   */
  public function getLangcode(): ?string {
    return $this->environment['langcode'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getHtmlBodyTemplate(): ?string {
    return $this->pluginDefinition['html_body_template'];
  }

  /**
   * {@inheritdoc}
   */
  public function getTextBodyTemplate(): ?string {
    return $this->pluginDefinition['text_body_template'];
  }

  /**
   * {@inheritdoc}
   */
  public function htmlBody(): RenderableInterface|array|null {
    if ($template_id = $this->getHtmlBodyTemplate()) {
      return [
        '#theme' => $template_id,
        '#email' => $this,
      ];
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function textBody(): RenderableInterface|array|null {
    if ($template_id = $this->getTextBodyTemplate()) {
      return [
        '#theme' => $template_id,
        '#email' => $this,
      ];
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSubject(): \Stringable|string|null {
    return $this->pluginDefinition['default_subject'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultRecipients(): ?array {
    return $this->pluginDefinition['default_recipients'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function preBuild(): static {
    if ($this->getHeader('Subject') === NULL && $default_subject = $this->getDefaultSubject()) {
      $this->subject($default_subject);
    }
    $default_recipients = $this->getDefaultRecipients();
    if (!empty($default_recipients['to']) && $this->getHeader('To') === NULL) {
      $this->to(...$default_recipients['to']);
    }
    if (!empty($default_recipients['cc']) && $this->getHeader('Cc') === NULL) {
      $this->cc(...$default_recipients['cc']);
    }
    if (!empty($default_recipients['bcc']) && $this->getHeader('Bcc') === NULL) {
      $this->bcc(...$default_recipients['bcc']);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHeaders(): array {
    return $this->headers;
  }

  /**
   * {@inheritdoc}
   */
  public function getHeader(string $key): mixed {
    return $this->headers[$key] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setHeader(string $key, mixed $value): static {
    $this->headers[$key] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setHeaders(array $headers): static {
    $this->headers = $headers;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getEnvironment(): array {
    return $this->environment;
  }

  /**
   * {@inheritdoc}
   */
  public function setEnvironmentParameter(string $key, mixed $value): static {
    $this->environment[$key] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setEnvironment(array $environment): static {
    $this->environment = $environment;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getParameters(): array {
    return $this->parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function setParameters(array $parameters): static {
    $this->parameters = $parameters;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getParam(string $key): mixed {
    return $this->parameters[$key] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setParam(string $key, mixed $value): static {
    $this->parameters[$key] = $value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function subject(\Stringable|string $subject): static {
    $this->setHeader('Subject', $subject);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function to(Address|string ...$addresses): static {
    $this->setHeader('To', $addresses);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function cc(Address|string ...$addresses): static {
    $this->setHeader('Cc', $addresses);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function bcc(Address|string ...$addresses): static {
    $this->setHeader('Bcc', $addresses);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function replyTo(Address|string ...$addresses): static {
    $this->setHeader('Reply-To', $addresses);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function sender(Address|string $address): static {
    $this->setHeader('Sender', $address);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function from(Address|string $address): static {
    $this->setHeader('From', $address);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function contentType(string $content_type): static {
    $this->setHeader('Content-Type', $content_type);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function langcode(string $langcode): static {
    $this->setEnvironmentParameter('langcode', $langcode);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setSend(bool $send): static {
    $this->send = $send;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function shouldSend(): bool {
    return $this->send;
  }


}
