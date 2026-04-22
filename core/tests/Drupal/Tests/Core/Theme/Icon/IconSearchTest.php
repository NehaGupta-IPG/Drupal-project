<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Theme\Icon;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Theme\Icon\IconSearch;
use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
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
   * @var \Drupal\Core\Theme\Icon\IconSearch
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
  public function testSearchWithShortQuery(): void {
    $result = $this->iconSearch->search('a');
    $this->assertEmpty($result);
  }

  /**
   * Tests search returns empty for an empty query.
   */
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

    $this->assertNotEmpty($result);
    // Should find 'core:add' and 'core:add-new'.
    $this->assertCount(2, $result);
    $this->assertEquals('core:add', $result[0]);
    $this->assertEquals('core:add-new', $result[1]);
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
   * Tests search result prioritization (exact → starts-with → contains).
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

    // 1. Exact match: 'core:home'
    // 2. Starts with: 'core:home-outline'
    // 3. Contains: 'other:my-home', 'pack:has-home-icon'.
    $this->assertEquals('core:home', $result[0]);
    $this->assertEquals('core:home-outline', $result[1]);
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
   * Tests search fuzzy matching (all query words must appear in icon ID).
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
   * Tests search result count is capped at SEARCH_RESULT_MAX.
   */
  public function testSearchMaxResultLimit(): void {
    $icons = [];
    for ($i = 0; $i < 250; $i++) {
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

    // Request more than max allowed (200).
    $result = $this->iconSearch->search('icon', [], 300);

    // Should be limited to SEARCH_RESULT_MAX (200).
    $this->assertCount(200, $result);
  }

}
