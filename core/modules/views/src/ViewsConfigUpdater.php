<?php

namespace Drupal\views;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Uuid\Uuid;
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
    return $this->processTaxonomyTermViewsFilterUpdate($view);
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
   * Checks if a view's taxonomy term filter values need converting to UUIDs.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   The view entity.
   *
   * @return bool
   *   TRUE if any filter needs the update.
   */
  public function needsTaxonomyTermViewsFilterUpdate(ViewEntityInterface $view): bool {
    return $this->processDisplayHandlers($view, TRUE, function (&$handler, $handler_type) use ($view) {
      return $this->processTaxonomyTermViewsFilterUpdate($view);
    });
  }

  /**
   * Converts taxonomy term filter values from entity IDs to UUIDs.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   The view entity.
   *
   * @return bool
   *   TRUE if the view was updated.
   */
  public function processTaxonomyTermViewsFilterUpdate(ViewEntityInterface $view): bool {
    $changed = FALSE;
    $displays = $view->get('display');

    foreach ($displays as &$display) {
      $filters = $display['display_options']['filters'] ?? [];
      foreach ($filters as $id => $filter) {
        if (isset($filter['plugin_id']) && $filter['plugin_id'] === 'taxonomy_index_tid' && isset($filter['value'])) {
          // Filter all the IDs and UUIDs.
          // If someone has applied that patch, we can already have UUIDs.
          $valid_uuids = array_filter($filter['value'], fn($v) => Uuid::isValid($v));
          $ids_to_load = array_filter($filter['value'], fn($v) => !Uuid::isValid($v));
          if (!empty($ids_to_load)) {
            $changed = TRUE;
            $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($ids_to_load);
            $loaded_uuids = array_map(fn($term) => $term->uuid(), $terms);
            $filters[$id]['value'] = array_merge($valid_uuids, $loaded_uuids);
            $display['display_options']['filters'] = $filters;
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
