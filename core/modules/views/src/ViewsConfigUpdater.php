<?php

namespace Drupal\views;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\Plugin\views\argument\StringArgument;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;

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
    #[AutowireLocator([
      'field' => new Autowire(service: 'plugin.manager.views.field'),
      'argument' => new Autowire(service: 'plugin.manager.views.argument'),
      'sort' => new Autowire(service: 'plugin.manager.views.sort'),
      'relationship' => new Autowire(service: 'plugin.manager.views.relationship'),
      'filter' => new Autowire(service: 'plugin.manager.views.filter'),
      'pager' => new Autowire(service: 'plugin.manager.views.pager'),
    ])]
    private readonly ContainerInterface $handlerPluginManagers,
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
      $changed = FALSE;
      if (!empty($handler['plugin_id'])) {
        $plugin_manager = $this->handlerPluginManagers->get($handler_type);
        $plugin_definition = $plugin_manager->getDefinition($handler['plugin_id'], FALSE);
      }
      $plugin_definition ??= [];

      if ($this->processStringArgumentExcludeUpdate($handler, $handler_type, $plugin_definition)) {
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
   * Checks for 'string' arguments needing default 'not' config.
   *
   * The config key 'not' appears in UI as 'exclude'.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   The view entity.
   *
   * @return bool
   *   TRUE if the view has any string-based arguments that need a
   *   default config value for 'not' set.
   */
  public function needsStringArgumentExcludeUpdate(ViewEntityInterface $view): bool {
    return $this->processDisplayHandlers($view, FALSE, function (&$handler, $handler_type) {
      if (!empty($handler['plugin_id'])) {
        $plugin_manager = $this->handlerPluginManagers->get($handler_type);
        $plugin_definition = $plugin_manager->getDefinition($handler['plugin_id'], FALSE);
      }
      return $this->processStringArgumentExcludeUpdate($handler, $handler_type, $plugin_definition ?? []);
    });
  }

  /**
   * Processes arguments and sets default 'not' config value if needed.
   *
   * The config key 'not' appears in UI as 'exclude'.
   *
   * @param array $handler
   *   A display handler.
   * @param string $handler_type
   *   The handler type.
   * @param array $plugin_definition
   *   The handler plugin definition.
   *
   * @return bool
   *   Whether the view was updated.
   */
  public function processStringArgumentExcludeUpdate(array &$handler, string $handler_type, array $plugin_definition): bool {
    $changed = FALSE;

    $plugin_class = $plugin_definition['class'] ?? '';
    if ($handler_type === 'argument' && is_a($plugin_class, StringArgument::class, TRUE) && empty($handler['not'])) {
      $handler['not'] = FALSE;
      $changed = TRUE;
    }

    return $changed;
  }

}
