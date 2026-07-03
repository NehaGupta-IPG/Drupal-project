<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui\SearchProvider;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuTreeStorageInterface;

/**
 * Search provider for menu_link_content entities.
 *
 * Covers user-created menu links (the dominant link type in large menus).
 * Static links defined by *.links.menu.yml are intentionally excluded here;
 * a separate provider can be added to cover them.
 *
 * @internal
 */
final class MenuLinkContentSearchProvider implements MenuTreeSearchProviderInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MenuTreeStorageInterface $treeStorage,
    private readonly Connection $database,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function search(string $menu_name, string $query, int $limit): array {
    $storage = $this->entityTypeManager->getStorage('menu_link_content');
    $like = '%' . $this->database->escapeLike($query) . '%';
    $entity_query = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('menu_name', $menu_name)
      ->condition('title', $like, 'LIKE')
      ->sort('title')
      ->range(0, $limit);
    $ids = $entity_query->execute();
    if (!$ids) {
      return [];
    }

    /** @var \Drupal\menu_link_content\MenuLinkContentInterface[] $entities */
    $entities = $storage->loadMultiple($ids);
    $results = [];
    foreach ($entities as $entity) {
      $plugin_id = $entity->getPluginId();
      [$breadcrumb_ids, $breadcrumb_titles] = $this->buildAncestorChain($plugin_id);
      $results[] = [
        'id' => $plugin_id,
        'title' => (string) $entity->getTitle(),
        'enabled' => (bool) $entity->isEnabled(),
        'edit_url' => $entity->toUrl('edit-form')->toString(),
        'breadcrumb' => $breadcrumb_titles,
        'breadcrumb_ids' => $breadcrumb_ids,
      ];
    }
    return $results;
  }

  /**
   * Returns the ancestor chain of a plugin as [ids[], titles[]], root first.
   *
   * @param string $plugin_id
   *   The plugin ID to build the ancestor chain for.
   *
   * @return array
   *   A two-element array: [ancestor IDs, ancestor titles], both root-first.
   */
  private function buildAncestorChain(string $plugin_id): array {
    $path_ids = $this->treeStorage->getRootPathIds($plugin_id);
    // getRootPathIds includes the link itself at index 0 — drop it so the
    // chain only contains ancestors.
    array_shift($path_ids);
    $path_ids = array_reverse($path_ids);
    if (!$path_ids) {
      return [[], []];
    }
    $definitions = $this->treeStorage->loadMultiple($path_ids);
    $ids = [];
    $titles = [];
    foreach ($path_ids as $id) {
      if (isset($definitions[$id]['title'])) {
        $ids[] = $id;
        $titles[] = (string) $definitions[$id]['title'];
      }
    }
    return [$ids, $titles];
  }

}
