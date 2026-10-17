<?php

declare(strict_types=1);

namespace Drupal\style_test;

use Drupal\Core\Theme\Style\StylePluginManager;

/**
 * Plugin manager used for tests.
 *
 * @phpstan-ignore-next-line
 */
class TestStylePluginManager extends StylePluginManager {

  /**
   * The list of styles.
   *
   * @var array
   */
  protected array $styles = [];

  /**
   * {@inheritdoc}
   */
  public function getDefinitions(): array {
    $definitions = $this->styles;
    foreach ($definitions as $plugin_id => &$definition) {
      $this->processDefinition($definition, $plugin_id);
    }
    $this->alterDefinitions($definitions);
    $this->definitions = $definitions;
    return $definitions;
  }

  /**
   * Getter.
   *
   * @return array
   *   Property value.
   */
  public function getStyles(): array {
    return $this->styles;
  }

  /**
   * Setter.
   *
   * @param array $styles
   *   Property value.
   *
   * @return $this
   */
  public function setStyles(array $styles) {
    $this->styles = $styles;
    return $this;
  }

}
