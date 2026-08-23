<?php

declare(strict_types=1);

namespace Drupal\Core\Hook;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

/**
 * Provides compiler-generated hook data.
 *
 * @internal
 */
class HookData {

  /**
   * Module hook data for the current request.
   *
   * @var array|null
   */
  private ?array $moduleHookData = NULL;

  /**
   * Theme hook data for the current request.
   *
   * @var array|null
   */
  private ?array $themeHookData = NULL;

  public function __construct(
    private readonly KeyValueFactoryInterface $keyValueFactory,
    private readonly CacheBackendInterface $cache,
  ) {
  }

  /**
   * Gets module hook data.
   *
   * @return array
   *   The module hook list and packed ordering rules.
   */
  public function getModuleHookData(): array {
    return $this->moduleHookData ??= $this->getData('hook_data', [
      'hook_list',
      'packed_order_operations',
    ]);
  }

  /**
   * Gets theme hook data.
   *
   * @return array
   *   The theme hook list and preprocess suggestions.
   */
  public function getThemeHookData(): array {
    return $this->themeHookData ??= $this->getData('theme_hook_data', [
      'theme_hook_list',
      'preprocess_for_suggestions',
    ]);
  }

  /**
   * Gets a group of hook data from cache or key-value storage.
   *
   * @param string $cid
   *   The bootstrap cache item ID.
   * @param list<string> $keys
   *   The key-value keys in the group.
   *
   * @return array
   *   The hook data group.
   */
  private function getData(string $cid, array $keys): array {
    if ($cache = $this->cache->get($cid)) {
      return $cache->data;
    }

    $data = $this->keyValueFactory->get('hook_data')->getMultiple($keys);
    $this->cache->set($cid, $data);
    return $data;
  }

}
