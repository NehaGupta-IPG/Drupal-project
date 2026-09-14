<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Theme\Icon;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Theme\Icon\IconSearch;
use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\Core\Theme\Icon\IconSearch.
 */
#[CoversClass(IconSearch::class)]
#[Group('icon')]
class IconSearchTest extends UnitTestCase {

  /**
   * The icon pack manager mock.
   *
   * @var \Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $iconPackManager;

  /**
   * The cache backend mock.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $cache;

  /**
   * The icon search service.
   *
   * @var \Drupal\Core\Theme\Icon\IconSearchInterface
   */
  protected $iconSearch;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->iconPackManager = $this->createMock(IconPackManagerInterface::class);
    $this->cache = $this->createMock(CacheBackendInterface::class);

    $this->iconSearch = new IconSearch(
      $this->iconPackManager,
      $this->cache,
    );
  }

  /**
   * Tests search returns empty for a query that is too short.
   */
  #[AllowMockObjectsWithoutExpectations]
  public function testSearchWithShortQuery(): void {
    $result = $this->iconSearch->search('a');
    $this->assertEmpty($result);
  }

  /**
   * Tests search returns empty for an empty query.
   */
  #[AllowMockObjectsWithoutExpectations]
  public function testSearchWithEmptyQuery(): void {
    $result = $this->iconSearch->search('');
    $this->assertEmpty($result);
  }

  /**
   * Tests search returns matching icon IDs.
   */
  public function testSearchReturnsResults(): void {
    $icons = [
      'core:add' => ['source' => 'add.svg'],
      'core:add-new' => ['source' => 'add-new.svg'],
      'core:edit' => ['source' => 'edit.svg'],
      'other:home' => ['source' => 'home.svg'],
    ];

    $this->iconPackManager->expects($this->once())
      ->method('getIcons')
      ->with([])
      ->willReturn($icons);

    $this->cache->expects($this->once())
      ->method('get')
      ->willReturn(FALSE);

    $this->cache->expects($this->once())
      ->method('set')
      ->with(
        $this->isString(),
        $this->isArray(),
        CacheBackendInterface::CACHE_PERMANENT,
        ['icon_pack_plugin']
      );

    $result = $this->iconSearch->search('add');

    // 'core:add' matches exactly, 'core:add-new' has 'add' as a whole word.
    $this->assertSame(['core:add', 'core:add-new'], $result);
  }

  /**
   * Tests search respects allowed icon packs.
   */
  public function testSearchWithAllowedIconPacks(): void {
    $this->iconPackManager->expects($this->once())
      ->method('getIcons')
      ->with(['core'])
      ->willReturn(['core:add' => ['source' => 'add.svg']]);

    $this->cache->expects($this->once())
      ->method('get')
      ->willReturn(FALSE);

    $this->cache->expects($this->once())
      ->method('set');

    $result = $this->iconSearch->search('add', ['core']);

    $this->assertCount(1, $result);
    $this->assertEquals('core:add', $result[0]);
  }

  /**
   * Tests search respects the max result limit.
   */
  public function testSearchWithMaxResults(): void {
    $icons = [];
    for ($i = 0; $i < 100; $i++) {
      $icons["core:icon-$i"] = ['source' => "icon-$i.svg"];
    }

    $this->iconPackManager->expects($this->once())
      ->method('getIcons')
      ->willReturn($icons);

    $this->cache->expects($this->once())
      ->method('get')
      ->willReturn(FALSE);

    $this->cache->expects($this->once())
      ->method('set');

    $result = $this->iconSearch->search('icon', [], 10);

    $this->assertCount(10, $result);
  }

  /**
   * Tests search result prioritization across the ranking buckets.
   */
  public function testSearchPrioritization(): void {
    $icons = [
      'core:home' => ['source' => 'home.svg'],
      'core:home-outline' => ['source' => 'home-outline.svg'],
      'other:my-home' => ['source' => 'my-home.svg'],
      'pack:has-home-icon' => ['source' => 'has-home-icon.svg'],
    ];

    $this->iconPackManager->expects($this->once())
      ->method('getIcons')
      ->willReturn($icons);

    $this->cache->expects($this->once())
      ->method('get')
      ->willReturn(FALSE);

    $this->cache->expects($this->once())
      ->method('set');

    $result = $this->iconSearch->search('home');

    // The icon part of 'core:home' equals the query, so it comes first. The
    // others all have 'home' as a whole word and keep their declared order.
    $this->assertSame([
      'core:home',
      'core:home-outline',
      'other:my-home',
      'pack:has-home-icon',
    ], $result);
  }

  /**
   * Tests search returns cached results without querying icon packs.
   */
  public function testSearchUsesCachedResults(): void {
    $cachedResults = [
      ['value' => 'core:add', 'label' => 'core:add'],
    ];

    $cacheObject = (object) ['data' => $cachedResults];
    $this->cache->expects($this->once())
      ->method('get')
      ->willReturn($cacheObject);

    $this->iconPackManager->expects($this->never())
      ->method('getIcons');

    $this->cache->expects($this->never())
      ->method('set');

    $result = $this->iconSearch->search('add');

    $this->assertEquals($cachedResults, $result);
  }

  /**
   * Tests search is case-insensitive.
   */
  public function testSearchCaseInsensitive(): void {
    $icons = [
      'core:Add' => ['source' => 'Add.svg'],
      'core:ADD' => ['source' => 'ADD.svg'],
    ];

    $this->iconPackManager->expects($this->once())
      ->method('getIcons')
      ->willReturn($icons);

    $this->cache->expects($this->once())
      ->method('get')
      ->willReturn(FALSE);

    $this->cache->expects($this->once())
      ->method('set');

    $result = $this->iconSearch->search('add');

    $this->assertCount(2, $result);
  }

  /**
   * Tests that every query word must match for an icon to be returned.
   */
  public function testSearchFuzzyMatching(): void {
    $icons = [
      'core:user-profile' => ['source' => 'user-profile.svg'],
      'core:my-user-settings' => ['source' => 'my-user-settings.svg'],
    ];

    $this->iconPackManager->expects($this->once())
      ->method('getIcons')
      ->willReturn($icons);

    $this->cache->expects($this->once())
      ->method('get')
      ->willReturn(FALSE);

    $this->cache->expects($this->once())
      ->method('set');

    $result = $this->iconSearch->search('user profile');

    $this->assertCount(1, $result);
    $this->assertEquals('core:user-profile', $result[0]);
  }

  /**
   * Tests search honors a max result larger than the former hard cap.
   */
  public function testSearchMaxResultIsNotCapped(): void {
    $icons = [];
    for ($i = 0; $i < 300; $i++) {
      $icons["core:icon-$i"] = ['source' => "icon-$i.svg"];
    }

    $this->iconPackManager->expects($this->once())
      ->method('getIcons')
      ->willReturn($icons);

    $this->cache->expects($this->once())
      ->method('get')
      ->willReturn(FALSE);

    $this->cache->expects($this->once())
      ->method('set');

    $result = $this->iconSearch->search('icon', [], 500);

    $this->assertCount(300, $result);
  }

  /**
   * Tests search treats query length as characters, not bytes.
   */
  #[AllowMockObjectsWithoutExpectations]
  public function testSearchWithSingleMultibyteCharacter(): void {
    $this->iconPackManager->expects($this->never())
      ->method('getIcons');

    $this->assertSame([], $this->iconSearch->search('é'));
    $this->assertSame([], $this->iconSearch->search('好'));
  }

  /**
   * Tests search accepts a two character multibyte query.
   */
  public function testSearchWithTwoMultibyteCharacters(): void {
    $this->iconPackManager->expects($this->once())
      ->method('getIcons')
      ->willReturn(['core:你好' => ['source' => 'ni-hao.svg']]);

    $this->cache->expects($this->once())
      ->method('get')
      ->willReturn(FALSE);

    $result = $this->iconSearch->search('你好');

    $this->assertSame(['core:你好'], $result);
  }

  /**
   * Tests an empty search result is not written to the cache.
   */
  public function testSearchDoesNotCacheEmptyResults(): void {
    $this->iconPackManager->expects($this->once())
      ->method('getIcons')
      ->willReturn(['core:add' => ['source' => 'add.svg']]);

    $this->cache->expects($this->once())
      ->method('get')
      ->willReturn(FALSE);

    $this->cache->expects($this->never())
      ->method('set');

    $this->assertSame([], $this->iconSearch->search('zzz'));
  }

  /**
   * Tests the cache id is a hash of the search parameters.
   */
  public function testSearchCacheIdIsHashed(): void {
    $cid = NULL;

    $this->iconPackManager->expects($this->once())
      ->method('getIcons')
      ->willReturn(['core:add' => ['source' => 'add.svg']]);

    $this->cache->expects($this->once())
      ->method('get')
      ->with($this->callback(function ($value) use (&$cid) {
        $cid = $value;
        return TRUE;
      }))
      ->willReturn(FALSE);

    $this->cache->expects($this->once())
      ->method('set');

    $this->iconSearch->search('add', ['core'], 10);

    $this->assertMatchesRegularExpression('/^icon_search:[A-Za-z0-9_-]{43}$/', $cid);
  }

  /**
   * Tests search returns nothing for a non-positive max result.
   */
  #[AllowMockObjectsWithoutExpectations]
  public function testSearchWithNonPositiveMaxResult(): void {
    $this->iconPackManager->expects($this->never())
      ->method('getIcons');

    $this->assertSame([], $this->iconSearch->search('add', [], 0));
    $this->assertSame([], $this->iconSearch->search('add', [], -1));
  }

  /**
   * Tests the cache id ignores the max result and the pack order.
   */
  public function testSearchCacheIdIgnoresMaxResultAndPackOrder(): void {
    $cids = [];

    $this->iconPackManager->expects($this->exactly(2))
      ->method('getIcons')
      ->willReturn(['core:add' => ['source' => 'add.svg']]);

    $this->cache->expects($this->exactly(2))
      ->method('get')
      ->with($this->callback(function ($value) use (&$cids) {
        $cids[] = $value;
        return TRUE;
      }))
      ->willReturn(FALSE);

    $this->cache->expects($this->exactly(2))
      ->method('set');

    $this->iconSearch->search('add', ['other', 'core'], 10);
    $this->iconSearch->search('add', ['core', 'other'], 20);

    $this->assertCount(2, $cids);
    $this->assertSame($cids[0], $cids[1]);
  }

  /**
   * Tests the full ranked list is cached and sliced on read.
   */
  public function testSearchSlicesCachedResults(): void {
    $this->iconPackManager->expects($this->once())
      ->method('getIcons')
      ->willReturn([
        'core:add' => ['source' => 'add.svg'],
        'core:add-new' => ['source' => 'add-new.svg'],
        'core:add-old' => ['source' => 'add-old.svg'],
      ]);

    $this->cache->expects($this->exactly(2))
      ->method('get')
      ->willReturnOnConsecutiveCalls(FALSE, (object) ['data' => ['core:add', 'core:add-new', 'core:add-old']]);

    $this->cache->expects($this->once())
      ->method('set')
      ->with($this->isString(), ['core:add', 'core:add-new', 'core:add-old']);

    $this->assertSame(['core:add', 'core:add-new'], $this->iconSearch->search('add', [], 2));
    $this->assertSame(['core:add'], $this->iconSearch->search('add', [], 1));
  }

  /**
   * Tests the ranking order of search results.
   *
   * @param array $icons
   *   Icon definitions keyed by full icon id, in pack declaration order.
   * @param string $query
   *   The search query.
   * @param array $expected_prefix
   *   The expected first results, in order. An empty array asserts that the
   *   search returned nothing at all.
   */
  #[DataProvider('providerRanking')]
  public function testRanking(array $icons, string $query, array $expected_prefix): void {
    $this->iconPackManager->expects($this->once())
      ->method('getIcons')
      ->willReturn($icons);

    $this->cache->expects($this->once())
      ->method('get')
      ->willReturn(FALSE);

    $result = $this->iconSearch->search($query);

    $this->assertSame($expected_prefix, array_slice($result, 0, count($expected_prefix) ?: 1));
  }

  /**
   * Provides ranking cases for ::testRanking.
   *
   * @return array
   *   Sets of icon definitions, query and expected leading results.
   */
  public static function providerRanking(): array {
    $many = [];
    for ($i = 1; $i <= 60; $i++) {
      $many["core:xhomex$i"] = ['source' => "xhomex$i.svg"];
    }
    $many['core:home'] = ['source' => 'home.svg'];

    return [
      'exact full id' => [
        [
          'other:core-home' => ['source' => 'core-home.svg'],
          'core:home' => ['source' => 'home.svg'],
        ],
        'core:home',
        ['core:home'],
      ],
      'exact icon id part' => [
        [
          'core:home-page' => ['source' => 'home-page.svg'],
          'core:home' => ['source' => 'home.svg'],
        ],
        'home',
        ['core:home', 'core:home-page'],
      ],
      'name prefix before whole word' => [
        [
          'other:my-home' => ['source' => 'my-home.svg'],
          'core:home-outline' => ['source' => 'home-outline.svg'],
        ],
        'home',
        ['core:home-outline', 'other:my-home'],
      ],
      'whole words in order before any order' => [
        [
          'core:settings-user' => ['source' => 'settings-user.svg'],
          'core:user-settings' => ['source' => 'user-settings.svg'],
        ],
        'user settings',
        ['core:user-settings', 'core:settings-user'],
      ],
      'substrings in order before any order' => [
        [
          'core:xsettingsx-xuserx' => ['source' => 'a.svg'],
          'core:xuserx-xsettingsx' => ['source' => 'b.svg'],
        ],
        'user settings',
        ['core:xuserx-xsettingsx', 'core:xsettingsx-xuserx'],
      ],
      'whole words before substrings' => [
        [
          'core:xaddx' => ['source' => 'xaddx.svg'],
          'core:add-new' => ['source' => 'add-new.svg'],
        ],
        'add',
        ['core:add-new', 'core:xaddx'],
      ],
      'same name in several packs keeps pack order' => [
        [
          'core:home' => ['source' => 'home.svg'],
          'other:home' => ['source' => 'home.svg'],
        ],
        'home',
        ['core:home', 'other:home'],
      ],
      'pack id is not a whole word match' => [
        [
          'core:homepage' => ['source' => 'homepage.svg'],
          'home:widget' => ['source' => 'widget.svg'],
          'core:my-home' => ['source' => 'my-home.svg'],
        ],
        'home',
        ['core:homepage', 'core:my-home', 'home:widget'],
      ],
      'case insensitive' => [
        ['core:HOME' => ['source' => 'HOME.svg']],
        'HoMe',
        ['core:HOME'],
      ],
      'no match' => [
        ['core:add' => ['source' => 'add.svg']],
        'zzz',
        [],
      ],
      'exact match wins over a full page of substring matches' => [
        $many,
        'home',
        ['core:home'],
      ],
    ];
  }

}
