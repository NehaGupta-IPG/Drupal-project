<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Condition;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Condition\ConditionInterface;
use Drupal\Core\Condition\ConditionManager;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\Core\Condition\ConditionManager
 *
 * @group Condition
 */
class ConditionManagerTest extends UnitTestCase {

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  public $cache;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $moduleHandler;

  /**
   * The class under test.
   *
   * @var \Drupal\Core\Condition\ConditionManager
   */
  public $conditionManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->moduleHandler = $this->createMock(ModuleHandlerInterface::class);

    $this->cache = $this->createMock(CacheBackendInterface::class);

    $namespaces = new \ArrayObject();

    $this->conditionManager = new ConditionManager($namespaces, $this->cache, $this->moduleHandler);
  }

  /**
   * @covers ::__construct
   * @covers ::execute
   */
  public function testExecute(): void {
    $execution_result = $this->randomMachineName();

    $condition = $this->createMock(ConditionInterface::class);
    $condition->expects($this->atLeastOnce())
      ->method('evaluate')
      ->willReturn($execution_result);

    $this->assertSame($execution_result, $this->conditionManager->execute($condition));
  }

}
