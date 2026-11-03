<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\menu_tree_ui\MenuTreeUiConstants;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests MenuTreeUiConstants::isContentLink() and ::uuidFromPluginId().
 */
#[CoversClass(MenuTreeUiConstants::class)]
#[Group('menu_tree_ui')]
final class MenuTreeUiConstantsTest extends UnitTestCase {

  /**
   * Tests isContentLink() against a table of plugin IDs.
   *
   * @param string $plugin_id
   *   The plugin ID under test.
   * @param bool $expected
   *   The expected result.
   */
  #[DataProvider('providerIsContentLink')]
  public function testIsContentLink(string $plugin_id, bool $expected): void {
    $this->assertSame($expected, MenuTreeUiConstants::isContentLink($plugin_id));
  }

  /**
   * Data provider for testIsContentLink().
   *
   * @return array<string, array{0: string, 1: bool}>
   *   Test cases keyed by description.
   */
  public static function providerIsContentLink(): array {
    return [
      'content link' => ['menu_link_content:abc', TRUE],
      'static link' => ['system.admin', FALSE],
      'empty string' => ['', FALSE],
      'prefix alone, no uuid' => ['menu_link_content:', TRUE],
    ];
  }

  /**
   * Tests uuidFromPluginId() against a table of plugin IDs.
   *
   * @param string $plugin_id
   *   The plugin ID under test.
   * @param string|null $expected
   *   The expected extracted UUID, or NULL for static links.
   */
  #[DataProvider('providerUuidFromPluginId')]
  public function testUuidFromPluginId(string $plugin_id, ?string $expected): void {
    $this->assertSame($expected, MenuTreeUiConstants::uuidFromPluginId($plugin_id));
  }

  /**
   * Data provider for testUuidFromPluginId().
   *
   * @return array<string, array{0: string, 1: string|null}>
   *   Test cases keyed by description.
   */
  public static function providerUuidFromPluginId(): array {
    return [
      'content link returns its uuid' => ['menu_link_content:abc', 'abc'],
      'static link returns NULL' => ['system.admin', NULL],
      'empty string returns NULL' => ['', NULL],
      // The prefix alone is still classified as a content link by
      // isContentLink(), so uuidFromPluginId() extracts whatever follows
      // the prefix: an empty string, not NULL.
      'prefix with no uuid part returns an empty string, not NULL' => ['menu_link_content:', ''],
    ];
  }

}
