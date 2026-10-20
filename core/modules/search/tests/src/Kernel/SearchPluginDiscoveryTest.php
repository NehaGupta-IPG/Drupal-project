<?php

declare(strict_types=1);

namespace Drupal\Tests\search\Kernel;

use Drupal\Core\Cache\MemoryBackend;
use Drupal\KernelTests\KernelTestBase;
use Drupal\search\SearchPluginManager;
use Drupal\search_help\Plugin\Search\SearchHelpSearch;
use Drupal\search_node\Plugin\Search\SearchNode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests discovery of replacement search plugins during updates.
 */
#[Group('search')]
#[RunTestsInSeparateProcesses]
class SearchPluginDiscoveryTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'help',
    'search',
    'search_help',
    'search_node',
  ];

  /**
   * Tests that replacement plugins win independently of namespace order.
   */
  #[DataProvider('namespaceOrderProvider')]
  public function testReplacementPlugins(bool $reverse): void {
    $namespaces = iterator_to_array($this->container->get('container.namespaces'));
    ksort($namespaces);
    if ($reverse) {
      $namespaces = array_reverse($namespaces, TRUE);
    }
    $manager = new SearchPluginManager(new \ArrayObject($namespaces), new MemoryBackend($this->container->get('datetime.time')), $this->container->get('module_handler'));
    $this->assertSame(SearchHelpSearch::class, $manager->getDefinition('help_search')['class']);
    $this->assertSame('search_help', $manager->getDefinition('help_search')['provider']);
    $this->assertSame(SearchNode::class, $manager->getDefinition('node_search')['class']);
    $this->assertSame('search_node', $manager->getDefinition('node_search')['provider']);
    $manager->clearCachedDefinitions();
    $this->assertSame(SearchHelpSearch::class, $manager->getDefinition('help_search')['class']);
    $this->assertSame(SearchNode::class, $manager->getDefinition('node_search')['class']);
  }

  /**
   * Tests replacement plugins when the original modules have a higher weight.
   *
   * Sites can give help and node a higher weight than the replacement modules,
   * which install with weight 0. The container then lists the replacement
   * namespaces first.
   */
  public function testReplacementPluginsWithModuleWeights(): void {
    module_set_weight('help', 1);
    module_set_weight('node', 1);
    $this->container->get('kernel')->updateModules($this->container->get('module_handler')->getModuleList());
    $this->container = \Drupal::getContainer();

    $order = array_keys(iterator_to_array($this->container->get('container.namespaces')));
    $this->assertLessThan(array_search('Drupal\\help', $order, TRUE), array_search('Drupal\\search_help', $order, TRUE));
    $this->assertLessThan(array_search('Drupal\\node', $order, TRUE), array_search('Drupal\\search_node', $order, TRUE));

    $manager = $this->container->get('plugin.manager.search');
    $manager->clearCachedDefinitions();
    $this->assertSame(SearchHelpSearch::class, $manager->getDefinition('help_search')['class']);
    $this->assertSame(SearchNode::class, $manager->getDefinition('node_search')['class']);
  }

  /**
   * Tests that legacy plugins remain available before installing replacements.
   */
  public function testLegacyPlugins(): void {
    $this->disableModules(['search_help', 'search_node']);
    $namespaces = $this->container->get('container.namespaces');
    $manager = new SearchPluginManager($namespaces, new MemoryBackend($this->container->get('datetime.time')), $this->container->get('module_handler'));
    $this->assertSame('Drupal\\help\\Plugin\\Search\\HelpSearch', $manager->getDefinition('help_search')['class']);
    $this->assertSame('Drupal\\node\\Plugin\\Search\\NodeSearch', $manager->getDefinition('node_search')['class']);
  }

  /**
   * Provides namespace iteration orders.
   */
  public static function namespaceOrderProvider(): array {
    return [
      'alphabetical' => [FALSE],
      'reverse alphabetical' => [TRUE],
    ];
  }

}
