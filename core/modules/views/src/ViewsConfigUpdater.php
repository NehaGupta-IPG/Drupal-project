<?php

namespace Drupal\views;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Drupal\views\Plugin\views\filter\BooleanOperator;
use Drupal\views\Plugin\ViewsHandlerManager;

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
    #[Autowire(service: 'plugin.manager.views.filter')]
    protected ViewsHandlerManager $filterPluginManager,
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
      $changed = FALSE;
      if ($this->processBooleanFilterAcceptNull($handler, $handler_type)) {
        $changed = TRUE;
      }
      return $changed;
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
   * Update boolean filter settings.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   The View to update.
   *
   * @return bool
   *   Whether the view was updated.
   */
  public function needsBooleanFilterAcceptNullUpdate(ViewEntityInterface $view): bool {
    return $this->processDisplayHandlers($view, TRUE, function (array &$handler, string $handler_type) {
      return $this->processBooleanFilterAcceptNull($handler, $handler_type);
    });
  }

  /**
   * Processes the boolean filter settings to accept null.
   *
   * @param array $handler
   *   A display handler.
   * @param string $handler_type
   *   The handler type.
   *
   * @return bool
   *   Whether the handler was updated.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   *   If the filter plugin instance cannot be created.
   */
  protected function processBooleanFilterAcceptNull(array &$handler, string $handler_type): bool {
    if ($handler_type === 'filter' && !isset($handler['accept_null'])) {
      // Any of the children of the modified classes will also be inheriting
      // the new settings.
      $filter_instance = $this->filterPluginManager->getHandler($handler);
      if ($filter_instance instanceof BooleanOperator) {
        $handler['accept_null'] = FALSE;
        return TRUE;
      }
    }
    return FALSE;
  }

}
