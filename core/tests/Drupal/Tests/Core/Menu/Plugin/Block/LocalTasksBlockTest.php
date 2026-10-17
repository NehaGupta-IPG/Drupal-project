<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Menu\Plugin\Block;

use Drupal\Core\Menu\LocalTaskManagerInterface;
use Drupal\Core\Menu\Plugin\Block\LocalTasksBlock;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Tests Drupal\Core\Menu\Plugin\Block\LocalTasksBlock.
 */
#[CoversClass(LocalTasksBlock::class)]
#[Group('Menu')]
class LocalTasksBlockTest extends UnitTestCase {

  /**
   * The local task manager.
   *
   * @var \Drupal\Core\Menu\LocalTaskManagerInterface
   */
  protected $localTaskManager;

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The local tasks block instance.
   *
   * @var \Drupal\Core\Menu\Plugin\Block\LocalTasksBlock
   */
  protected $localTasksBlock;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->localTaskManager = $this->createMock(LocalTaskManagerInterface::class);
    $this->routeMatch = $this->createMock(RouteMatchInterface::class);
  }

  /**
   * Creates a LocalTasksBlock instance with the given configuration.
   *
   * @param array $configuration
   *   The block configuration.
   *
   * @return \Drupal\Core\Menu\Plugin\Block\LocalTasksBlock
   *   The block instance.
   */
  protected function createLocalTasksBlock(array $configuration = []): LocalTasksBlock {
    $plugin_id = 'local_tasks_block';
    $plugin_definition = ['provider' => 'core'];

    return new LocalTasksBlock(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $this->localTaskManager,
      $this->routeMatch
    );
  }

  /**
   * Tests build() with null route name and primary and secondary tabs enabled.
   *
   * @legacy-covers ::build
   */
  public function testBuildWhenRouteNameIsNullAndPrimaryAndSecondaryTabs(): void {
    $this->localTasksBlock = $this->createLocalTasksBlock([
      'label_display' => FALSE,
      'primary' => TRUE,
      'secondary' => TRUE,
    ]);

    $this->routeMatch->expects($this->once())
      ->method('getRouteName')
      ->willReturn(NULL);

    $this->routeMatch->expects($this->once())
      ->method('getParameters')
      ->willReturn(new ParameterBag([]));

    // The LocalTaskManager::getLocalTasks() will never be called if
    // the route name is null.
    $this->localTaskManager->expects($this->never())
      ->method('getLocalTasks');

    $build = $this->localTasksBlock->build();

    $this->assertIsArray($build);
    $this->assertArrayNotHasKey('#theme', $build);
    $this->assertArrayNotHasKey('#primary', $build);
    $this->assertArrayNotHasKey('#secondary', $build);
  }

  /**
   * Tests build() with empty route name and primary and secondary tabs enabled.
   */
  public function testBuildWhenRouteNameIsEmptyAndPrimaryAndSecondaryTabs(): void {
    $this->localTasksBlock = $this->createLocalTasksBlock([
      'label_display' => FALSE,
      'primary' => TRUE,
      'secondary' => TRUE,
    ]);

    $this->routeMatch->expects($this->once())
      ->method('getRouteName')
      ->willReturn('');

    $this->routeMatch->expects($this->once())
      ->method('getParameters')
      ->willReturn(new ParameterBag([]));

    // The getLocalTasks will not be called with an empty route name.
    $this->localTaskManager->expects($this->never())
      ->method('getLocalTasks');

    $build = $this->localTasksBlock->build();

    $this->assertIsArray($build);
    $this->assertArrayNotHasKey('#theme', $build);
    $this->assertArrayNotHasKey('#primary', $build);
    $this->assertArrayNotHasKey('#secondary', $build);
  }

}
