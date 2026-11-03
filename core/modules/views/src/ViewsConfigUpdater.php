<?php

namespace Drupal\views;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Plugin\views\display\DisplayRouterInterface;
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
    return $this->processDisplayHandlers($view, FALSE, function (&$handler, $handler_type, $key, $display_id) {
      // @todo leaving this here now but new update hooks will need to update.
      return FALSE;
    });
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
    ];

    $compound_display_handlers = [
      'pager',
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

  /**
   * Checks for unrouted displays missing link_display.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   The view entity.
   *
   * @return bool
   *   TRUE if the view needed updates.
   */
  public function needsUnroutedLinkDisplayUpdate(ViewEntityInterface $view): bool {
    return $this->processUnroutedLinkDisplayUpdate($view);
  }

  /**
   * Updates unrouted displays to set link_display when a routed display exists.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   The view entity.
   *
   * @return bool
   *   TRUE if the view was updated.
   */
  public function processUnroutedLinkDisplayUpdate(ViewEntityInterface $view): bool {
    $displays = $view->get('display');
    $changed = FALSE;

    $executable = $view->getExecutable();
    foreach ($displays as $display_id => $display) {
      if ($display_id === 'default') {
        // Default display is inherited by others and does not need backfilling.
        continue;
      }

      if (!$executable || !$executable->setDisplay($display_id)) {
        // Skip invalid or non-loadable displays.
        continue;
      }

      $display_handler = $executable->display_handler;
      if (!$display_handler || $display_handler instanceof DisplayRouterInterface) {
        // Routed displays already have a stable URL.
        continue;
      }

      $linked_display_id = $display_handler->getOption('link_display');
      if ($linked_display_id === 'custom_url') {
        // Keep explicit custom URLs as-is.
        continue;
      }
      if (!empty($linked_display_id) && $executable->displayHandlers->has($linked_display_id)) {
        $linked_display = $executable->displayHandlers->get($linked_display_id);
        if ($linked_display instanceof DisplayRouterInterface) {
          // Keep explicit routed link displays as-is.
          continue;
        }
      }

      // Reproduce the previous fallback once during the update so existing
      // behavior is preserved as explicit configuration. Runtime resolution
      // no longer guesses based on display order.
      $guessed_display_id = NULL;
      foreach ($executable->displayHandlers as $candidate_display_id => $candidate_display) {
        if (!empty($candidate_display) && $candidate_display->hasPath()) {
          $guessed_display_id = $candidate_display_id;
          break;
        }
      }
      if (empty($guessed_display_id) || $guessed_display_id === 'custom_url' || !$executable->displayHandlers->has($guessed_display_id)) {
        // No prior routed guess to preserve.
        continue;
      }

      $guessed_display = $executable->displayHandlers->get($guessed_display_id);
      if (!$guessed_display instanceof DisplayRouterInterface) {
        // Only persist guesses that resolve to routed displays.
        continue;
      }

      if (empty($displays[$display_id]['display_options'])) {
        $displays[$display_id]['display_options'] = [];
      }
      if (empty($displays[$display_id]['display_options']['defaults'])) {
        $displays[$display_id]['display_options']['defaults'] = [];
      }

      $displays[$display_id]['display_options']['link_display'] = $guessed_display_id;
      $displays[$display_id]['display_options']['defaults']['link_display'] = FALSE;
      $changed = TRUE;
    }

    if ($changed) {
      $view->set('display', $displays);
    }

    return $changed;
  }

  /**
   * Checks if a filter's expose section is missing min_label or max_label.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   The view entity.
   *
   * @return bool
   *   TRUE if any filter needs the update.
   */
  public function needsMinMaxLabelUpdate(ViewEntityInterface $view): bool {
    return $this->processDisplayHandlers($view, FALSE, function (&$handler, $handler_type) {
      return $this->processMinMaxLabelUpdate($handler, $handler_type);
    });
  }

  /**
   * Adds default min_label and max_label to exposed numeric-type filters.
   *
   * Only filters that already have min_placeholder (indicating they derive from
   * NumericFilter) but lack min_label are updated. The legacy hardcoded labels
   * "Min" and "Max" are used so existing sites are visually unchanged.
   *
   * @param array $handler
   *   A display handler.
   * @param string $handler_type
   *   The handler type.
   *
   * @return bool
   *   Whether the handler was updated.
   */
  public function processMinMaxLabelUpdate(array &$handler, string $handler_type): bool {
    if (
      $handler_type === 'filter' &&
      array_key_exists('min_placeholder', $handler['expose'] ?? []) &&
      !array_key_exists('min_label', $handler['expose'] ?? [])
    ) {
      $handler['expose']['min_label'] = 'Min';
      $handler['expose']['max_label'] = 'Max';
      return TRUE;
    }
    return FALSE;
  }

}
