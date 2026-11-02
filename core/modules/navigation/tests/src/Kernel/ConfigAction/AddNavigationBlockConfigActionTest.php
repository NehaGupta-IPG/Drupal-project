<?php

declare(strict_types=1);

namespace Drupal\Tests\navigation\Kernel\ConfigAction;

use Drupal\Component\Utility\SortArray;
use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\navigation\Plugin\ConfigAction\AddNavigationBlock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * Tests Add Navigation Block Config Action.
 */
#[Group('navigation')]
#[Group('Recipe')]
#[CoversClass(AddNavigationBlock::class)]
#[RunTestsInSeparateProcesses]
class AddNavigationBlockConfigActionTest extends KernelTestBase {

  use RecipeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'navigation',
    'layout_discovery',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('navigation');
  }

  /**
   * Tests add item logic.
   */
  #[TestWith([NULL, 0])]
  #[TestWith([0, 0])]
  #[TestWith([1, 1])]
  #[TestWith([2, 2])]
  #[TestWith([6, 2])]
  public function testAddBlockToNavigation(?int $delta, int $computed_delta): void {
    $this->assertCount(2, $this->getContentComponents());
    $data = [
      'configuration' => [
        'id' => 'navigation_menu:content',
        'label' => 'Content From Recipe',
        'label_display' => 'visible',
        'provider' => 'navigation',
        'level' => 1,
        'depth' => 2,
      ],
    ];
    if ($delta !== NULL) {
      $data['delta'] = $delta;
    }

    // Use the action to add a new block to Navigation.
    /** @var \Drupal\Core\Config\Action\ConfigActionManager $manager */
    $manager = $this->container->get('plugin.manager.config_action');
    $manager->applyAction('addNavigationBlock', 'navigation.block_layout', $data);

    // Load the config after the execution.
    $components = $this->getContentComponents();
    $this->assertCount(3, $components);
    $component = array_values($components)[$computed_delta];
    $this->assertSame('content', $component['region']);
    $this->assertEquals($data['configuration'], $component['configuration']);
  }

  /**
   * Tests that the delta is a position even if the weights are not sequential.
   */
  public function testAddBlockToNavigationWithNonSequentialWeights(): void {
    $config = $this->config('navigation.block_layout');
    $components = $config->get('sections.0.components');
    // Set the weights of the two content blocks to arbitrary values.
    $weight = 5;
    foreach ($this->getContentComponents() as $uuid => $component) {
      $components[$uuid]['weight'] = $weight;
      $weight += 5;
    }
    $config->set('sections.0.components', $components)->save();
    $existing = array_keys($this->getContentComponents());

    $manager = $this->container->get('plugin.manager.config_action');
    $manager->applyAction('addNavigationBlock', 'navigation.block_layout', [
      'delta' => 0,
      'configuration' => [
        'id' => 'navigation_menu:content',
        'label' => 'First',
        'label_display' => 'visible',
        'provider' => 'navigation',
        'level' => 1,
        'depth' => 2,
      ],
    ]);

    $components = $this->getContentComponents();
    $this->assertCount(3, $components);
    $this->assertSame('First', array_values($components)[0]['configuration']['label']);
    // The existing blocks kept their order after the new one.
    $this->assertSame($existing, array_slice(array_keys($components), 1));
  }

  /**
   * Tests adding a block when the content region is empty.
   */
  public function testAddBlockToEmptyContentRegion(): void {
    $config = $this->config('navigation.block_layout');
    $components = array_diff_key($config->get('sections.0.components'), $this->getContentComponents());
    $config->set('sections.0.components', $components)->save();
    $this->assertCount(0, $this->getContentComponents());

    $manager = $this->container->get('plugin.manager.config_action');
    $manager->applyAction('addNavigationBlock', 'navigation.block_layout', [
      'configuration' => [
        'id' => 'navigation_menu:content',
        'label' => 'Only',
        'label_display' => 'visible',
        'provider' => 'navigation',
        'level' => 1,
        'depth' => 2,
      ],
    ]);

    $components = $this->getContentComponents();
    $this->assertCount(1, $components);
    $this->assertSame(0, reset($components)['weight']);
  }

  /**
   * Returns the blocks of the content region, ordered by weight.
   *
   * @return array[]
   *   The component arrays keyed by UUID.
   */
  protected function getContentComponents(): array {
    $components = \Drupal::config('navigation.block_layout')->get('sections.0.components') ?? [];
    $components = array_filter($components, fn (array $component): bool => $component['region'] === 'content');
    uasort($components, [SortArray::class, 'sortByWeightElement']);
    return $components;
  }

  /**
   * Checks invalid config exception.
   */
  public function testActionOnlySupportsNavigationConfig(): void {
    $this->expectException(ConfigActionException::class);
    $this->expectExceptionMessageIs('addNavigationBlock can only be executed for the navigation.block_layout config.');
    // Try to apply the Config Action against an unexpected config entity.
    /** @var \Drupal\Core\Config\Action\ConfigActionManager $manager */
    $manager = $this->container->get('plugin.manager.config_action');
    $manager->applyAction('addNavigationBlock', 'navigation.settings', []);
  }

}
