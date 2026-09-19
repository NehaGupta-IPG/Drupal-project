<?php

declare(strict_types=1);

namespace Drupal\Core\Theme\Style;

use Drupal\Component\Plugin\CategorizingPluginManagerInterface;
use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Interface for style plugin managers.
 *
 * @internal
 *   This API is experimental.
 */
interface StylePluginManagerInterface extends PluginManagerInterface, CategorizingPluginManagerInterface {

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\Core\Theme\Style\StyleDefinitionInterface|null
   *   The plugin definition. NULL if not found.
   *
   * @throws \Drupal\Core\Theme\Style\Exception\StyleDefinitionErrorException
   *   Thrown when the definition is not valid.
   */
  public function getDefinition($plugin_id, $exception_on_invalid = TRUE);

  /**
   * {@inheritdoc}
   *
   * @return \Drupal\Core\Theme\Style\StyleDefinitionInterface[]
   *   The plugins definitions.
   */
  public function getDefinitions();

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Theme\Style\StyleDefinitionInterface[]|null $definitions
   *   (optional) The plugin definitions to sort. If omitted, all plugin
   *   definitions are used.
   * @param string $label_key
   *   (optional) The key to be used as a label for sorting.
   *
   * @return \Drupal\Core\Theme\Style\StyleDefinitionInterface[]
   *   The sorted definitions.
   */
  public function getSortedDefinitions(?array $definitions = NULL, string $label_key = 'label'): array;

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Theme\Style\StyleDefinitionInterface[]|null $definitions
   *   (optional) The plugin definitions to group. If omitted, all plugin
   *   definitions are used.
   * @param string $label_key
   *   (optional) The key to be used as a label for sorting.
   *
   * @return \Drupal\Core\Theme\Style\StyleDefinitionInterface[][]
   *   The sorted definitions grouped by category.
   */
  public function getGroupedDefinitions(?array $definitions = NULL, string $label_key = 'label'): array;

}
