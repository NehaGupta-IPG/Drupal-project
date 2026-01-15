<?php

namespace Drupal\Core\Action\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines an Action attribute object.
 *
 * Plugin Namespace: Plugin\Action
 *
 * @see \Drupal\Core\Action\ActionInterface
 * @see \Drupal\Core\Action\ActionManager
 * @see \Drupal\Core\Action\ActionBase
 * @see \Drupal\Core\Action\Plugin\Action\UnpublishAction
 * @see plugin_api
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Action extends Plugin {

  /**
   * An array of context definitions describing the context used by the plugin.
   *
   * @var \Drupal\Core\Plugin\Context\ContextDefinitionInterface[]|null
   */
  public readonly ?array $context_definitions;

  /**
   * Defines the provided context_definitions from the action plugin.
   *
   * @var \Drupal\Core\Plugin\Context\ContextDefinitionInterface[]|null
   */
  public readonly ?array $provides;

  /**
   * Constructs an Action attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $label
   *   The label of the action.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $action_label
   *   (optional) A label that can be used by the action deriver.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   (optional) A description of the action.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $category
   *   (optional) The category under which the action should be listed in the
   *   UI.
   * @param \Drupal\Core\Plugin\Context\ContextDefinitionInterface[] $context_definitions
   *   (optional) The context definitions.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   * @param \Drupal\Core\Plugin\Context\ContextDefinitionInterface[]|null $provides
   *   (optional) Defines the provided context_definitions.
 */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $label = NULL,
    public readonly ?TranslatableMarkup $action_label = NULL,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly ?TranslatableMarkup $category = NULL,
    array|null $context_definitions = [],
    public readonly ?string $deriver = NULL,
    array|null $provides = [],
  ) {
    $this->context_definitions = $context_definitions;
    $this->provides = $provides;
  }

}
