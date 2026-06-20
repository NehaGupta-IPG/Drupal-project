<?php

declare(strict_types=1);

namespace Drupal\treegrid;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\treegrid\Attribute\TreegridColumn;
use Drupal\treegrid\Plugin\TreegridColumn\TreegridColumnInterface;

/**
 * Discovers and instantiates TreegridColumn plugins.
 *
 * Modules add columns by placing a class implementing TreegridColumnInterface
 * inside src/Plugin/TreegridColumn/ and decorating it with #[TreegridColumn].
 * The alter hook hook_treegrid_column_info_alter() lets modules modify or
 * remove definitions after discovery.
 */
final class TreegridColumnManager extends DefaultPluginManager {

  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct(
      'Plugin/TreegridColumn',
      $namespaces,
      $module_handler,
      TreegridColumnInterface::class,
      TreegridColumn::class,
    );
    $this->alterInfo('treegrid_column_info');
    $this->setCacheBackend($cache_backend, 'treegrid_column_plugins');
  }

  /**
   * Memoised sorted column instances.
   *
   * @var \Drupal\treegrid\Plugin\TreegridColumn\TreegridColumnInterface[]|null
   */
  private ?array $sortedColumns = NULL;

  /**
   * Returns all column plugin instances sorted by weight.
   *
   * Result is memoised: instances are shared across calls within the same
   * request to avoid redundant createInstance() work.
   *
   * @return \Drupal\treegrid\Plugin\TreegridColumn\TreegridColumnInterface[]
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
