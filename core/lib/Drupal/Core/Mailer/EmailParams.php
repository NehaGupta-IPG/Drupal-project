<?php

declare(strict_types=1);

namespace Drupal\Core\Mailer;

/**
 * Encapsulates email plugin params.
 */
class EmailParams {

  /**
   * @param array $definition
   *   The email plugin definition.
   * @param array $attributes
   *   Email plugin parameters set by the call site.
   * @param array $environment
   *   Environment parameters for the email plugin execution.
   *
   * @todo Change visibility of environment property to protected and add access
   * methods once environment switching is implemented.
   * @see: https://www.drupal.org/i/3536307
   */
  public function __construct(
    public readonly array $definition,
    protected array $attributes,
    public array $environment,
  ) {
  }

  /**
   * Returns all attributes.
   */
  public function getAttributes(): array {
    return $this->attributes;
  }

  /**
   * Returns TRUE if an attribute is defined.
   *
   * @param string $key
   *   The attribute key.
   */
  public function hasAttribute(string $key): bool {
    return isset($this->attributes[$key]);
  }

  /**
   * Returns an attribute.
   */
  public function getAttribute(string $key): mixed {
    return $this->attributes[$key] ?? NULL;
  }

  /**
   * Sets an attribute.
   *
   * @param string $key
   *   The attribute key.
   * @param mixed $value
   *   The attribute value.
   */
  public function setAttribute(string $key, mixed $value): static {
    if (isset($value)) {
      $this->attributes[$key] = $value;
    }
    else {
      unset($this->attributes[$key]);
    }
    return $this;
  }

  /**
   * Returns all arguments.
   *
   * Non-prefixed attributes (arguments) are made available to the email plugin
   * methods and also to the twig template. Additionally the email id, module,
   * key and langcode are added in order to simplify access to them.
   *
   * Finally the parameter value object itself is added to allow generic
   * controllers.
   */
  public function getArguments(): array {
    $arguments = [];
    foreach ($this->attributes as $key => $value) {
      if (!str_starts_with('_', $key)) {
        $arguments[$key] = $value;
      }
    }
    return [
      'id' => $this->definition['id'],
      'module' => $this->definition['module'],
      'key' => $this->definition['key'],
      'langcode' => $this->environment['langcode'],
      'params' => $this,
    ] + $arguments;
  }

}
