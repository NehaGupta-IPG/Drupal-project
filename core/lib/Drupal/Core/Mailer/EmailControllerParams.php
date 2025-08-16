<?php

declare(strict_types=1);

namespace Drupal\Core\Mailer;

/**
 * A value object representing email controller parameters.
 */
class EmailControllerParams {

  /**
   * @param \Drupal\Core\Mailer\EmailDefinition $definition
   *   The email plugin definiton.
   * @param array $attributes
   *   Email parameters merged with defaults from the plugin definition.
   * @param array $environment
   *   Environment parameters for the controller invocation.
   *
   * @todo Change visibility of environment property to protected and add access
   * methods once environment switching is implemented.
   * @see: https://www.drupal.org/i/3536307
   */
  public function __construct(
    public readonly EmailDefinition $definition,
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
   * Non-prefixed attributes (arguments) are made available to the controller
   * and also to the twig template. Additionaly the email id, module, key and
   * langcode are added in order to simplify access to them.
   *
   * Finally the controller params object itself is added to allow generic
   * controllers.
   */
  public function getArguments(): array {
    foreach ($this->attributes as $key => $value) {
      if (!str_starts_with('_', $key)) {
        $arguments[$key] = $value;
      }
    }
    return [
      'id' => $this->definition->id(),
      'module' => $this->definition->getModule(),
      'key' => $this->definition->getKey(),
      'langcode' => $this->environment['langcode'],
      'params' => $this,
    ] + $arguments;
  }

  /**
   * Returns TRUE if a controller is defined.
   */
  public function hasControllerDefinition(): bool {
    return $this->hasAttribute(EmailDefinition::CONTROLLER_NAME);
  }

  /**
   * Returns the controller definition.
   *
   * Looks up the _controller attribute and returns its value. This can be
   * passed on to the callable resolver in order to retrieve the controller
   * method as a callable.
   *
   * @see \Drupal\Core\Utility\CallableResolver::getCallableFromDefinition()
   */
  public function getControllerDefinition(): callable|array|string {
    return $this->getAttribute(EmailDefinition::CONTROLLER_NAME) ?? '';
  }

  /**
   * Sets the controller definition.
   */
  public function setControllerDefinition(callable|array|string|null $value): static {
    return $this->setAttribute(EmailDefinition::CONTROLLER_NAME, $value);
  }

}
