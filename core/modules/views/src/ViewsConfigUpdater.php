<?php

namespace Drupal\views;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provides a BC layer for modules providing old configurations.
 *
 * @internal
 */
class ViewsConfigUpdater {

  /**
   * Flag determining whether deprecations should be triggered.
   */
  protected bool $deprecationsEnabled = TRUE;

  /**
   * Stores which deprecations were triggered.
   */
  protected array $triggeredDeprecations = [];

  /**
   * ViewsConfigUpdater constructor.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly TypedConfigManagerInterface $typedConfigManager,
    private readonly ViewsData $viewsData,
    #[Autowire(service: 'plugin.manager.field.formatter')]
    private readonly PluginManagerInterface $formatterPluginManager,
    protected EntityDisplayRepositoryInterface $entityDisplayRepository,
  ) {
  }

  /**
   * Sets the deprecations enabling status.
   *
   * @param bool $enabled
   *   Whether deprecations should be enabled.
   */
  public function setDeprecationsEnabled(bool $enabled): void {
    $this->deprecationsEnabled = $enabled;
  }

  /**
   * Whether deprecations are enabled.
   */
  public function areDeprecationsEnabled(): bool {
    return $this->deprecationsEnabled;
  }

  /**
   * Performs all required updates.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   The View to update.
   *
   * @return bool
   *   Whether the view was updated.
   */
  public function updateAll(ViewEntityInterface $view) {
    return $this->processDisplayHandlers($view, FALSE, function (&$handler, $handler_type, $key, $display_id) use ($view) {
      $changed = FALSE;
      if ($this->processTimeCacheUpdate($handler, $handler_type, $view)) {
        $changed = TRUE;
      }
      return $changed;
    });
  }

  /**
   * Removes the custom lifespan options of time-based cache plugins.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   The View to update.
   *
   * @return bool
   *   Whether the view was updated.
   */
  public function needsTimeCacheUpdate(ViewEntityInterface $view): bool {
    return $this->processDisplayHandlers($view, FALSE, function (&$handler, $handler_type) use ($view) {
      return $this->processTimeCacheUpdate($handler, $handler_type, $view);
    });
  }

  /**
   * Processes the options of time-based cache plugins.
   *
   * @param array $compound_handler
   *   A compound display handler.
   * @param string $handler_type
   *   The handler type.
   * @param \Drupal\views\ViewEntityInterface $view
   *   The View being updated.
   *
   * @return bool
   *   Whether the handler was updated.
   */
  protected function processTimeCacheUpdate(array &$compound_handler, string $handler_type, ViewEntityInterface $view): bool {
    if ($handler_type !== 'cache' || ($compound_handler['type'] ?? NULL) !== 'time' || !isset($compound_handler['options'])) {
      return FALSE;
    }

    $changed = FALSE;
    $options = &$compound_handler['options'];
    foreach (['results_lifespan', 'output_lifespan'] as $field) {
      if (!array_key_exists($field . '_custom', $options)) {
        continue;
      }
      if (($options[$field] ?? NULL) === 'custom') {
        $options[$field] = (int) $options[$field . '_custom'];
      }
      unset($options[$field . '_custom']);
      $changed = TRUE;
    }

    $deprecations_triggered = &$this->triggeredDeprecations['3233105'][$view->id()];
    if ($this->deprecationsEnabled && $changed && !$deprecations_triggered) {
      $deprecations_triggered = TRUE;
      @trigger_error(sprintf('Support for the results_lifespan_custom and output_lifespan_custom settings of time-based cache plugins, as used in the "%s" view, is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Store the custom lifespan directly in results_lifespan and output_lifespan instead. Profile, module and theme provided configuration should be updated. See https://www.drupal.org/node/9999999', $view->id()), E_USER_DEPRECATED);
    }

    return $changed;
  }

  /**
   * Processes all display handlers.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   The View to update.
   * @param bool $return_on_changed
   *   Whether processing should stop after a change is detected.
   * @param callable $handler_processor
   *   A callback performing the actual update.
   *
   * @return bool
   *   Whether the view was updated.
   */
  protected function processDisplayHandlers(ViewEntityInterface $view, $return_on_changed, callable $handler_processor) {
    $changed = FALSE;
    $displays = $view->get('display');
    $handler_types = [
      'field' => 'fields',
      'argument' => 'arguments',
      'sort' => 'sorts',
      'relationship' => 'relationships',
      'filter' => 'filters',
      'pager' => 'pager',
      'cache' => 'cache',
    ];

    $compound_display_handlers = [
      'pager',
      'cache',
    ];

    foreach ($displays as $display_id => &$display) {
      foreach ($handler_types as $handler_type => $handler_type_lookup) {
        if (!empty($display['display_options'][$handler_type_lookup])) {
          if (in_array($handler_type_lookup, $compound_display_handlers)) {
            if ($handler_processor($display['display_options'][$handler_type_lookup], $handler_type, NULL, $display_id)) {
              $changed = TRUE;
              if ($return_on_changed) {
                return $changed;
              }
            }
            continue;
          }
          foreach ($display['display_options'][$handler_type_lookup] as $key => &$handler) {
            if (is_array($handler) && $handler_processor($handler, $handler_type, $key, $display_id)) {
              $changed = TRUE;
              if ($return_on_changed) {
                return $changed;
              }
            }
          }
        }
      }
    }

    if ($changed) {
      $view->set('display', $displays);
    }

    return $changed;
  }

}
