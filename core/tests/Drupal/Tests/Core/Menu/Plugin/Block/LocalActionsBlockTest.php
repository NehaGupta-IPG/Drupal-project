<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Menu\Plugin\Block;

use Drupal\Core\Menu\LocalActionManagerInterface;
use Drupal\Core\Menu\Plugin\Block\LocalActionsBlock;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\Core\Menu\Plugin\Block\LocalActionsBlock.
 */
#[CoversClass(LocalActionsBlock::class)]
#[Group('Menu')]
class LocalActionsBlockTest extends UnitTestCase {

  /**
   * The local action manager.
   *
   * @var \Drupal\Core\Menu\LocalActionManagerInterface
   */
  protected $localActionManager;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The actions block instance.
   *
   * @var \Drupal\Core\Menu\Plugin\Block\LocalActionsBlock
   */
  protected $localActionsBlock;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->localActionManager = $this->createMock(LocalActionManagerInterface::class);
    $this->routeMatch = $this->createMock(RouteMatchInterface::class);

    $configuration = [];
    $plugin_definition = ['provider' => 'core'];
    $plugin_id = 'local_actions_block';

    $this->localActionsBlock = new LocalActionsBlock(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $this->localActionManager,
      $this->routeMatch
    );
  }

  /**
   * The build() will return the empty array if the route name is null.
   *
   * @legacy-covers ::build
   */
  public function testBuildWhenRouteNameIsNull(): void {
    $this->routeMatch->expects($this->once())
      ->method('getRouteName')
      ->willReturn(NULL);

    $this->localActionManager->expects($this->never())
      ->method('getActionsForRoute');

    $build = $this->localActionsBlock->build();

    $this->assertIsArray($build);
    $this->assertEmpty($build);
    $this->assertSame([], $build);
  }

  /**
   * The build() will return the empty array if the route is an empty string.
   *
   * @legacy-covers ::build
   */
  public function testBuildWhenRouteNameIsEmptyString(): void {
    $this->routeMatch->expects($this->once())
      ->method('getRouteName')
      ->willReturn('');

    $this->localActionManager->expects($this->never())
      ->method('getActionsForRoute');

    $build = $this->localActionsBlock->build();

    $this->assertIsArray($build);
    $this->assertEmpty($build);
    $this->assertSame([], $build);
  }

}
