<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Hook;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Hook\HookData;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests hook data caching.
 */
#[CoversClass(HookData::class)]
#[Group('Hook')]
class HookDataTest extends UnitTestCase {

  /**
   * Tests module hook data is loaded once per request.
   */
  public function testModuleHookDataIsLoadedOnce(): void {
    $store = $this->createMock(KeyValueStoreInterface::class);
    $store->expects($this->once())
      ->method('getMultiple')
      ->with(['hook_list', 'packed_order_operations'])
      ->willReturn([
        'hook_list' => ['test' => ['Test::hook' => 'test']],
        'packed_order_operations' => ['test' => ['test']],
      ]);

    $key_value = $this->createMock(KeyValueFactoryInterface::class);
    $key_value->expects($this->once())
      ->method('get')
      ->with('hook_data')
      ->willReturn($store);

    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->expects($this->once())
      ->method('get')
      ->with('hook_data')
      ->willReturn(FALSE);
    $cache->expects($this->once())
      ->method('set')
      ->with('hook_data', [
        'hook_list' => ['test' => ['Test::hook' => 'test']],
        'packed_order_operations' => ['test' => ['test']],
      ]);

    $hook_data = new HookData($key_value, $cache);
    $this->assertSame(['test' => ['Test::hook' => 'test']], $hook_data->getModuleHookData()['hook_list']);
    $this->assertSame(['test' => ['test']], $hook_data->getModuleHookData()['packed_order_operations']);
  }

  /**
   * Tests module and theme hook data use separate cache entries.
   */
  public function testHookDataGroupsUseSeparateCacheEntries(): void {
    $store = $this->createMock(KeyValueStoreInterface::class);
    $store->expects($this->exactly(2))
      ->method('getMultiple')
      ->willReturnCallback(static fn (array $keys): array => match ($keys) {
        ['hook_list', 'packed_order_operations'] => [
          'hook_list' => [],
          'packed_order_operations' => [],
        ],
        ['theme_hook_list', 'preprocess_for_suggestions'] => [
          'theme_hook_list' => [],
          'preprocess_for_suggestions' => [],
        ],
      });

    $key_value = $this->createMock(KeyValueFactoryInterface::class);
    $key_value->expects($this->exactly(2))
      ->method('get')
      ->with('hook_data')
      ->willReturn($store);

    $cache_cids = [];
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->expects($this->exactly(2))
      ->method('get')
      ->willReturnCallback(static function (string $cid) use (&$cache_cids): bool {
        $cache_cids[] = $cid;
        return FALSE;
      });
    $cache->expects($this->exactly(2))
      ->method('set')
      ->willReturnCallback(static function (string $cid, array $data) use (&$cache_cids): void {
        $cache_cids[] = $cid;
      });

    $hook_data = new HookData($key_value, $cache);
    $hook_data->getModuleHookData();
    $hook_data->getThemeHookData();

    $this->assertSame(['hook_data', 'hook_data', 'theme_hook_data', 'theme_hook_data'], $cache_cids);
  }

}
