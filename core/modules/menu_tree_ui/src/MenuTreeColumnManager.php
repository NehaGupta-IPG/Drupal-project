<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\menu_tree_ui\Attribute\MenuTreeColumn;
use Drupal\menu_tree_ui\Plugin\MenuTreeColumn\MenuTreeColumnInterface;

/**
 * Discovers and instantiates MenuTreeColumn plugins.
 *
 * Modules add columns by placing a class implementing MenuTreeColumnInterface
 * inside src/Plugin/MenuTreeColumn/ and decorating it with #[MenuTreeColumn].
 * The alter hook hook_menu_tree_column_info_alter() lets modules modify or
 * remove definitions after discovery.
 */
final class MenuTreeColumnManager extends DefaultPluginManager {

  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct(
      'Plugin/MenuTreeColumn',
      $namespaces,
      $module_handler,
      MenuTreeColumnInterface::class,
      MenuTreeColumn::class,
    );
    $this->alterInfo('menu_tree_column_info');
    $this->setCacheBackend($cache_backend, 'menu_tree_column_plugins');
  }

  /**
   * Memoised sorted column instances.
   *
   * @var \Drupal\menu_tree_ui\Plugin\MenuTreeColumn\MenuTreeColumnInterface[]|null
   */
  private ?array $sortedColumns = NULL;

  /**
   * Returns all column plugin instances sorted by weight.
   *
   * Result is memoised: instances are shared across calls within the same
   * request to avoid redundant createInstance() work.
   *
   * @return \Drupal\menu_tree_ui\Plugin\MenuTreeColumn\MenuTreeColumnInterface[]
   *   Keyed by plugin ID, sorted by the 'weight' attribute property.
   */
  public function getSortedColumns(): array {
    if ($this->sortedColumns !== NULL) {
      return $this->sortedColumns;
    }
    $definitions = $this->getDefinitions();
    uasort($definitions, static fn(array $a, array $b): int => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));
    $columns = [];
    foreach (array_keys($definitions) as $id) {
      $columns[$id] = $this->createInstance($id);
    }
    $this->sortedColumns = $columns;
    return $this->sortedColumns;
  }

}
