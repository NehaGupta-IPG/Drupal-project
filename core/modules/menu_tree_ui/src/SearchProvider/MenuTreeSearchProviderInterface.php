<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui\SearchProvider;

/**
 * Interface for menu tree search providers.
 *
 * @api
 *
 * Tag a service with { name: menu_tree_ui.search_provider, priority: N } to
 * register it. Higher priority providers run first; results are merged and
 * deduplicated by plugin ID before the alter hook fires.
 *
 * The default implementation covers menu_link_content entities. Other modules
 * can add providers to cover, for example, static yml-defined links.
 */
interface MenuTreeSearchProviderInterface {

  /**
   * Returns search results matching $query in $menu_name.
   *
   * @param string $menu_name
   *   Menu entity ID (e.g. 'main').
   * @param string $query
   *   Raw, non-empty search string (trimming is the caller's responsibility).
   * @param int $limit
   *   Maximum results to return from this provider.
   *
   * @return array<int, array{
   *   id: string,
   *   title: string,
   *   enabled: bool,
   *   edit_url: string|null,
   *   breadcrumb: string[],
   *   breadcrumb_ids: string[],
   *   }>
   */
  public function search(string $menu_name, string $query, int $limit): array;

}
