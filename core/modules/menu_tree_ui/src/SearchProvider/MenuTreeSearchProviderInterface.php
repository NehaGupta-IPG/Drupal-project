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
   * Matching semantics are the provider's choice; the default provider does a
   * case-insensitive substring match on the link title, with entity access
   * checks applied. Results are returned in the provider's display order (the
   * default provider sorts by title); the loader preserves that order within
   * each provider's slice of the merged list.
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
   *   The results. A NULL edit_url means the result has no edit form; the
   *   results list then renders the title without an Edit link.
   */
  public function search(string $menu_name, string $query, int $limit): array;

}
