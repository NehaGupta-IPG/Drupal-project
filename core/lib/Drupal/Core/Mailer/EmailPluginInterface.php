<?php

declare(strict_types=1);

namespace Drupal\Core\Mailer;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RenderableInterface;

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
   * Returns the plugin module.
   */
  public function getModule(): string;

  /**
   * Returns the plugin key.
   */
  public function getKey(): string;

  public function htmlBody(): RenderableInterface|array|null;

  public function textBody(): RenderableInterface|array|null;

  public function getHeaders(): array;

  public function preBuild(): static;

  public function getDefaultRecipients(): ?array;

  public function getDefaultSubject(): \Stringable|string|null;

  public function setParams(array $params): static;

  public function getParams(): array;

  public function setParam(string $key, mixed $value): static;

  public function getParam(string $key): mixed;

  public function getLangcode(): ?string;

}
