<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Theme\Icon;

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

    $this->iconSearch = new IconSearch($this->iconPackManager);
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
   * Tests search trims the query before checking its length.
   */
  public function testSearchWithShortQueryAfterTrim(): void {
    $this->iconPackManager->expects($this->never())
      ->method('getIcons');

    $this->assertSame([], $this->iconSearch->search(' '));
    $this->assertSame([], $this->iconSearch->search(' a '));
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

    $result = $this->iconSearch->search('icon', [], 500);

    $this->assertCount(300, $result);
  }

  /**
   * Tests search treats query length as characters, not bytes.
   */
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

    $result = $this->iconSearch->search('你好');

    $this->assertSame(['core:你好'], $result);
  }

  /**
   * Tests search returns nothing for a non-positive max result.
   */
  public function testSearchWithNonPositiveMaxResult(): void {
    $this->iconPackManager->expects($this->never())
      ->method('getIcons');

    $this->assertSame([], $this->iconSearch->search('add', [], 0));
    $this->assertSame([], $this->iconSearch->search('add', [], -1));
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
      $many["core:chrome-$i"] = ['source' => "chrome-$i.svg"];
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
          'core:settings-users' => ['source' => 'settings-users.svg'],
          'core:users-settings' => ['source' => 'users-settings.svg'],
        ],
        'user settings',
        ['core:users-settings', 'core:settings-users'],
      ],
      'whole words before substrings' => [
        [
          'core:ladder' => ['source' => 'ladder.svg'],
          'core:add-new' => ['source' => 'add-new.svg'],
        ],
        'add',
        ['core:add-new', 'core:ladder'],
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

  /**
   * Tests the complete search result, with and without an icon pack limit.
   *
   * @param string $query
   *   The search query.
   * @param string[] $allowed_icon_pack
   *   Icon pack ids to limit the search to.
   * @param string[] $icons
   *   Full icon ids, in pack declaration order.
   * @param string[] $expected
   *   The complete expected result, in order.
   */
  #[DataProvider('providerSearchMatches')]
  public function testSearchMatches(string $query, array $allowed_icon_pack, array $icons, array $expected): void {
    // Limit the icons to the allowed packs, as the icon pack manager does.
    $available = array_filter($icons, static fn (string $id): bool => !$allowed_icon_pack || in_array(strstr($id, ':', TRUE), $allowed_icon_pack, TRUE));

    $this->iconPackManager->expects($this->once())
      ->method('getIcons')
      ->with($allowed_icon_pack)
      ->willReturn(array_fill_keys($available, []));

    $this->assertSame($expected, $this->iconSearch->search($query, $allowed_icon_pack));
  }

  /**
   * Provides id and word queries for ::testSearchMatches.
   *
   * @return array
   *   Sets of query, allowed icon packs, full icon ids and expected result.
   */
  public static function providerSearchMatches(): array {
    $exact = ['bar:_match_', 'baz:quux', 'foo:_match_'];
    $exact_multiple = [
      'foo:_match_',
      'bar:_match_',
      'bar:bar',
      'baz:_match_',
      'baz:baz',
      'quux:_match_',
    ];
    $words = ['foo:_partial_barista_', 'qux:quux', 'baz: some foolish', 'baz:baz'];
    $words_pack = [
      '_match_pack_:_partial_barista_',
      'qux:quux',
      '_match_pack_: some foolish',
      'baz:baz',
    ];

    return [
      'punctuation only' => [
        '?!%$*+',
        [],
        ['foo:bar', 'baz:quux'],
        [],
      ],
      'exact full id' => [
        '_pack_match_:_match_',
        [],
        ['foo:bar', '_pack_match_:_match_', 'baz:quux'],
        ['_pack_match_:_match_'],
      ],
      'exact full id, pack not allowed' => [
        '_match_:_match_',
        ['other'],
        ['foo:bar', '_match_:_match_', 'baz:quux'],
        [],
      ],
      'exact full id, pack allowed' => [
        '_match_pack_:_match_',
        ['_match_pack_'],
        ['foo:bar', '_match_pack_:_match_', 'baz:quux'],
        ['_match_pack_:_match_'],
      ],
      'exact icon id' => [
        '_match_',
        [],
        $exact,
        ['bar:_match_', 'foo:_match_'],
      ],
      'exact icon id, pack not allowed' => [
        '_match_',
        ['other'],
        $exact,
        [],
      ],
      'exact icon id, pack allowed' => [
        '_match_',
        ['_match_pack_'],
        ['bar:_match_', 'baz:quux', '_match_pack_:_match_'],
        ['_match_pack_:_match_'],
      ],
      'exact icon id in several packs' => [
        '_match_',
        [],
        $exact_multiple,
        ['foo:_match_', 'bar:_match_', 'baz:_match_', 'quux:_match_'],
      ],
      'exact icon id in several packs, pack not allowed' => [
        '_match_',
        ['other'],
        $exact_multiple,
        [],
      ],
      'exact icon id in several packs, packs allowed' => [
        '_match_',
        ['_match_pack_', '_match_pack_2_'],
        [
          'bar:_match_',
          'bar:bar',
          '_match_pack_:_match_',
          'baz:_match_',
          'baz:baz',
          '_match_pack_2_:_match_',
        ],
        ['_match_pack_:_match_', '_match_pack_2_:_match_'],
      ],
      'name prefix before substring' => [
        'fo',
        [],
        [
          'bar:foo',
          'bar:bar',
          'baz: this is foo',
          'baz:baz',
          'quux:bar',
          'quux:foo or what? ',
          'qux:bar',
        ],
        ['bar:foo', 'quux:foo or what? ', 'baz: this is foo'],
      ],
      'partial words in order' => [
        'part bar',
        [],
        $words,
        ['foo:_partial_barista_'],
      ],
      'partial words in order, pack not allowed' => [
        'part bar',
        ['other'],
        $words,
        [],
      ],
      'partial words in order, pack allowed' => [
        'part bar',
        ['_match_pack_'],
        ['_match_pack_:_partial_barista_', 'qux:quux', 'baz:quux', 'baz:baz'],
        ['_match_pack_:_partial_barista_'],
      ],
      'every query word must match' => [
        'foo bar',
        [],
        $words,
        ['foo:_partial_barista_'],
      ],
      'every query word must match, pack allowed' => [
        'foo bar',
        ['foo'],
        $words,
        ['foo:_partial_barista_'],
      ],
      'partial words in order, one of two allowed packs has icons' => [
        'part bar',
        ['_match_pack_', '_other_match_pack'],
        $words_pack,
        ['_match_pack_:_partial_barista_'],
      ],
      'query punctuation is matched literally' => [
        '!foo? !ùµ$::!bar.çà',
        [],
        $words,
        [],
      ],
    ];
  }

}
