<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\Kernel;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Menu\MenuTreeStorageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\menu_tree_ui\SearchProvider\MenuTreeSearchProviderInterface;
use Drupal\menu_tree_ui\Service\MenuTreeLoader;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests MenuTreeLoader::search() provider collection, deduplication, and alter hook.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeLoaderSearchTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'link',
    'menu_link_content',
    'menu_tree_ui',
    'treegrid',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('menu_link_content');
  }

  /**
   * Empty query must short-circuit without calling any provider.
   */
  public function testEmptyQueryReturnsEmpty(): void {
    $called = FALSE;
    $provider = $this->makeProvider(function () use (&$called): array {
      $called = TRUE;
      return [];
    });

    $loader = $this->buildLoader([$provider]);
    $results = $loader->search('main', '', 25);

    $this->assertSame([], $results);
    $this->assertFalse($called, 'Provider must not be called for an empty query.');
  }

  /**
   * Whitespace-only query must also short-circuit.
   */
  public function testWhitespaceQueryReturnsEmpty(): void {
    $provider = $this->makeProvider(fn() => [
      $this->makeResult('menu_link_content:abc', 'Test'),
    ]);

    $loader = $this->buildLoader([$provider]);
    $this->assertSame([], $loader->search('main', '   ', 25));
  }

  /**
   * Results from a single provider must be returned as-is.
   */
  public function testSingleProviderResultsReturned(): void {
    $fixture = [
      $this->makeResult('menu_link_content:abc', 'Alpha'),
      $this->makeResult('menu_link_content:def', 'Beta'),
    ];
    $provider = $this->makeProvider(fn() => $fixture);

    $loader = $this->buildLoader([$provider]);
    $results = $loader->search('main', 'test', 25);

    $this->assertCount(2, $results);
    $this->assertSame('menu_link_content:abc', $results[0]['id']);
  }

  /**
   * Overlapping IDs from multiple providers must be deduplicated (first wins).
   */
  public function testMultipleProviderResultsAreMerged(): void {
    $shared = $this->makeResult('menu_link_content:shared', 'Shared');
    $unique1 = $this->makeResult('menu_link_content:only1', 'Only-1');
    $unique2 = $this->makeResult('menu_link_content:only2', 'Only-2');

    $provider1 = $this->makeProvider(fn() => [$shared, $unique1]);
    $provider2 = $this->makeProvider(fn() => [$shared, $unique2]);

    $loader = $this->buildLoader([$provider1, $provider2]);
    $results = $loader->search('main', 'test', 25);

    $ids = array_column($results, 'id');
    $this->assertCount(3, $results, 'Shared ID must appear exactly once.');
    $this->assertContains('menu_link_content:shared', $ids);
    $this->assertContains('menu_link_content:only1', $ids);
    $this->assertContains('menu_link_content:only2', $ids);
    // First-provider title must win deduplication.
    $shared_result = current(array_filter($results, fn($r) => $r['id'] === 'menu_link_content:shared'));
    $this->assertSame('Shared', $shared_result['title']);
  }

  /**
   * The limit must cap the slice before the alter hook fires.
   */
  public function testLimitIsRespected(): void {
    $fixture = array_map(
      fn(int $i) => $this->makeResult("menu_link_content:item$i", "Item $i"),
      range(0, 9),
    );
    $provider = $this->makeProvider(fn() => $fixture);

    $loader = $this->buildLoader([$provider]);
    $results = $loader->search('main', 'item', 3);

    $this->assertCount(3, $results);
  }

  /**
   * The menu_tree_ui_search_results alter hook must be able to inject results.
   */
  public function testAlterHookCanModifyResults(): void {
    $provider = $this->makeProvider(fn() => [
      $this->makeResult('menu_link_content:real', 'Real'),
    ]);

    $injected = $this->makeResult('menu_link_content:injected', 'Injected');
    $module_handler = $this->createStub(ModuleHandlerInterface::class);
    $module_handler
      ->method('alter')
      ->willReturnCallback(function (string $hook, array &$data) use ($injected): void {
        if ($hook === 'menu_tree_ui_search_results') {
          $data[] = $injected;
        }
      });

    $loader = $this->buildLoader([$provider], $module_handler);
    $results = $loader->search('main', 'real', 25);

    $ids = array_column($results, 'id');
    $this->assertContains('menu_link_content:injected', $ids);
    $this->assertContains('menu_link_content:real', $ids);
  }

  // Helpers.

  /**
   * Builds a MenuTreeLoader with inline providers.
   *
   * @param \Drupal\menu_tree_ui\SearchProvider\MenuTreeSearchProviderInterface[] $providers
   *   The search providers to hand to the loader.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface|null $module_handler
   *   An optional module handler; defaults to the container's.
   *
   * @return \Drupal\menu_tree_ui\Service\MenuTreeLoader
   *   The configured loader.
   */
  private function buildLoader(array $providers, ?ModuleHandlerInterface $module_handler = NULL): MenuTreeLoader {
    return new MenuTreeLoader(
      $this->container->get('menu.link_tree'),
      $this->createStub(MenuTreeStorageInterface::class),
      $module_handler ?? $this->container->get('module_handler'),
      $providers,
      $this->container->get('menu_tree_ui.working_copy'),
      $this->container->get('plugin.manager.menu.link'),
    );
  }

  /**
   * Creates an anonymous search provider delegating to $callback.
   */
  private function makeProvider(callable $callback): MenuTreeSearchProviderInterface {
    return new class($callback) implements MenuTreeSearchProviderInterface {

      public function __construct(private readonly \Closure $callback) {}

      /**
       * {@inheritdoc}
       */
      public function search(string $menu_name, string $query, int $limit): array {
        return ($this->callback)($menu_name, $query, $limit);
      }

    };
  }

  /**
   * Returns a minimal result array conforming to the search result shape.
   */
  private function makeResult(string $id, string $title): array {
    return [
      'id' => $id,
      'title' => $title,
      'enabled' => TRUE,
      'edit_url' => NULL,
      'breadcrumb' => [],
      'breadcrumb_ids' => [],
    ];
  }

}
