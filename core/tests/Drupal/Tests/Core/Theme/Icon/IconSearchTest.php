<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Theme\Icon;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Theme\Icon\IconSearch;
use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\Core\Theme\Icon\IconSearch
 * @group Theme
 * @group Icon
 */
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
   * @covers ::search
   */
  public function testSearchWithShortQuery(): void {
    // Query too short (less than 2 characters).
    $result = $this->iconSearch->search('a');
    $this->assertEmpty($result);
  }

  /**
   * @covers ::search
   */
  public function testSearchWithEmptyQuery(): void {
    $result = $this->iconSearch->search('');
    $this->assertEmpty($result);
  }

  /**
   * @covers ::search
   */
  public function testSearchReturnsResults(): void {
    // Mock icon data.
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

    // Cache miss.
    $this->cache->expects($this->once())
      ->method('get')
      ->willReturn(FALSE);

    // Cache set.
    $this->cache->expects($this->once())
      ->method('set')
      ->with(
        $this->isType('string'),
        $this->isType('array'),
        $this->isType('int')
      );

    $result = $this->iconSearch->search('add');

    $this->assertIsArray($result);
    $this->assertNotEmpty($result);
    // Should find 'core:add' and 'core:add-new'.
    $this->assertCount(2, $result);
    // Results are simple strings (icon IDs).
    $this->assertEquals('core:add', $result[0]);
    $this->assertEquals('core:add-new', $result[1]);
  }

  /**
   * @covers ::search
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
   * @covers ::search
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
   * @covers ::search
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

    // Verify priority order:
    // 1. Exact match: 'core:home'
    // 2. Starts with: 'core:home-outline'
    // 3. Contains: 'other:my-home', 'pack:has-home-icon'.
    $this->assertEquals('core:home', $result[0]);
    $this->assertEquals('core:home-outline', $result[1]);
  }

  /**
   * @covers ::search
   */
  public function testSearchUsesCachedResults(): void {
    $cachedResults = [
      ['value' => 'core:add', 'label' => 'core:add'],
    ];

    // Cache hit.
    $cacheObject = (object) ['data' => $cachedResults];
    $this->cache->expects($this->once())
      ->method('get')
      ->willReturn($cacheObject);

    // Icon pack manager should not be called when cache hits.
    $this->iconPackManager->expects($this->never())
      ->method('getIcons');

    // Cache set should not be called.
    $this->cache->expects($this->never())
      ->method('set');

    $result = $this->iconSearch->search('add');

    $this->assertEquals($cachedResults, $result);
  }

  /**
   * @covers ::search
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

    // Search with lowercase should match uppercase icons.
    $result = $this->iconSearch->search('add');

    $this->assertCount(2, $result);
  }

  /**
   * @covers ::search
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

    // Search with multiple words should find icons containing all words.
    $result = $this->iconSearch->search('user profile');

    $this->assertCount(1, $result);
    $this->assertEquals('core:user-profile', $result[0]);
  }

  /**
   * @covers ::search
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
