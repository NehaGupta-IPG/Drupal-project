<?php

declare(strict_types=1);

namespace Drupal\Core\Mailer;

use Drupal\Component\Plugin\Definition\PluginDefinition;
use Drupal\Component\Plugin\Definition\PluginDefinitionInterface;

/**
 * An email plugin definition as declared in a {module}.email.yml file.
 */
class EmailDefinition extends PluginDefinition implements PluginDefinitionInterface {

  /**
   * Key for the controller.
   */
  const CONTROLLER_NAME = '_controller';

  /**
   * The email label.
   */
  protected string|\Stringable $label;

  /**
   * The email description.
   */
  protected string|\Stringable $description;

  /**
   * The email sending module. Defaults to the plugin provider.
   */
  protected string $module;

  /**
   * A machine name used to identify different emails sent from a module.
   */
  protected string $key;

  /**
   * A dictionary of default parameters.
   */
  protected array $defaults;

  /**
   * Constructs a new email definition.
   */
  public function __construct(array $definition) {
    $this->id = $definition['id'];
    $this->class = '';
    $this->provider = $definition['provider'];
    $this->label = $definition['label'];
    $this->description = $definition['description'] ?? '';
    $this->module = $definition['module'] ?? $definition['provider'];
    $this->key = $definition['key'];
    $this->defaults = $definition['defaults'] ?? [];
  }

  /**
   * Returns the label of the email definition.
   */
  public function getLabel(): string|\Stringable {
    return $this->label;
  }

  /**
   * Returns the description for an email definition.
   */
  public function getDescription(): string|\Stringable {
    return $this->description;
  }

  /**
   * Returns the machine name of the email sending module.
   */
  public function getModule(): string {
    return $this->module;
  }

  /**
   * Returns the machine name used to identify emails sent from the same module.
   */
  public function getKey(): string {
    return $this->key;
  }

  /**
   * Returns key value pairs representing default values for the parameters.
   */
  public function getDefaults(): array {
    return $this->defaults;
  }

}
