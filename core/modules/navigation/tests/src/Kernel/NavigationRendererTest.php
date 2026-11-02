<?php

declare(strict_types=1);

namespace Drupal\Tests\navigation\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\navigation\NavigationRenderer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the navigation renderer.
 */
#[Group('navigation')]
#[RunTestsInSeparateProcesses]
class NavigationRendererTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'block',
    'layout_discovery',
    'navigation',
    'navigation_test_block',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['navigation']);
  }

  /**
   * Tests that blocks without output still contribute their cacheability.
   */
  public function testEmptyBlockCacheability(): void {
    // Replace the default blocks with a single test block.
    $uuid = $this->container->get('uuid')->generate();
    $this->config('navigation.block_layout')->set('sections.0.components', [
      $uuid => [
        'uuid' => $uuid,
        'region' => 'content',
        'weight' => 0,
        'configuration' => [
          'id' => 'navigation_test',
          'label' => 'Test',
          'label_display' => 'visible',
          'provider' => 'navigation_test_block',
        ],
        'additional' => [],
      ],
    ])->save();

    $renderer = $this->container->get(NavigationRenderer::class);
    $build = $renderer->doBuildNavigation();
    $this->assertNotEmpty($build[0]['content'][$uuid]);
    $this->assertNotContains('navigation_test_block.empty', $build['#cache']['tags']);

    // When the block renders nothing, the cache metadata of its content is
    // still needed to know when the block may have something to render again.
    $this->container->get('state')->set('navigation_test_block.empty', TRUE);
    $build = $renderer->doBuildNavigation();
    $this->assertEmpty($build[0]['content'][$uuid]);
    $this->assertContains('navigation_test_block.empty', $build['#cache']['tags']);
  }

}
