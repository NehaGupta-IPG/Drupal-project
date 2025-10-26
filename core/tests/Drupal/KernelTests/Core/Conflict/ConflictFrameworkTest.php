<?php

namespace Drupal\KernelTests\Core\Conflict;

use Drupal\Core\Conflict\ConflictDiscovery\ConflictDiscoveryBase;
use Drupal\Core\Conflict\ConflictResolution\MergeStrategyBase;
use Drupal\Core\Conflict\Event\EntityConflictDiscoveryEvent;
use Drupal\Core\Conflict\Event\EntityConflictResolutionEvent;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Test cases for the event based conflict resolution system.
 *
 * Mocks actual conflict discovery and resolution strategies.
 *
 * @group Conflict
 */
class ConflictFrameworkTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'system',
    'entity_test',
    'user',
  ];

  /**
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $conflict_discovery_a;

  /**
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $conflict_discovery_b;

  /**
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $conflict_resolution_a;

  /**
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  protected $conflict_resolution_b;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('entity_test');
  }


  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);

    $this->conflict_discovery_a = $this->getMockForAbstractClass(ConflictDiscoveryBase::class);
    $discovery_a = new Definition();
    $discovery_a->setClass(ConflictDiscoveryBase::class);
    $discovery_a->setFactory([$this, 'discoveryFactory']);
    $discovery_a->setArguments(['a']);
    $discovery_a->addTag('event_subscriber');
    $container->setDefinition('conflict_test.discovery_a', $discovery_a);

    $this->conflict_discovery_b = $this->getMockForAbstractClass(ConflictDiscoveryBase::class);
    $discovery_b = new Definition();
    $discovery_b->setClass(ConflictDiscoveryBase::class);
    $discovery_b->setFactory([$this, 'discoveryFactory']);
    $discovery_b->setArguments(['b']);
    $discovery_b->addTag('event_subscriber');
    $container->setDefinition('conflict_test.discovery_b', $discovery_b);

    $this->conflict_resolution_a = $this->getMockForAbstractClass(MergeStrategyBase::class);
    $resolution_a = new Definition();
    $resolution_a->setClass(MergeStrategyBase::class);
    $resolution_a->setFactory([$this, 'resolutionFactory']);
    $resolution_a->setArguments(['a']);
    $resolution_a->addTag('event_subscriber');
    $container->setDefinition('conflict_test.resolution_a', $resolution_a);

    $this->conflict_resolution_b = $this->getMockForAbstractClass(MergeStrategyBase::class);
    $resolution_b = new Definition();
    $resolution_b->setClass(MergeStrategyBase::class);
    $resolution_b->setFactory([$this, 'resolutionFactory']);
    $resolution_b->setArguments(['b']);
    $resolution_b->addTag('event_subscriber');
    $container->setDefinition('conflict_test.resolution_b', $resolution_b);

    // Remove the default implementations for the sake of this test.
    $container->removeDefinition('conflict.discovery.default');
    $container->removeDefinition('conflict.resolution.merge_remote_only_changes');
  }

  /**
   * Container factory method to retrieve a mocked conflict discovery.
   *
   * @param string $id
   *   The mock id ('a' or 'b')
   *
   * @return \PHPUnit\Framework\MockObject\MockObject
   */
  public function discoveryFactory($id) {
    if ($id === 'a') {
      return $this->conflict_discovery_a;
    }

    if ($id === 'b') {
      return $this->conflict_discovery_b;
    }
  }

  /**
   * Container factory method to retrieve a mocked conflict resolution.
   *
   * @param string $id
   *   The mock id ('a' or 'b')
   *
   * @return \PHPUnit\Framework\MockObject\MockObject
   */
  public function resolutionFactory($id) {
    if ($id === 'a') {
      return $this->conflict_resolution_a;
    }

    if ($id === 'b') {
      return $this->conflict_resolution_b;
    }
  }

  /**
   * Test no conflicts detected.
   */
  public function testDiscoverNoConflicts() {
    $base = EntityTest::create(['name' => 'A']);
    $local = EntityTest::create(['name' => 'B']);
    $remote = EntityTest::create(['name' => 'C']);

    /** @var \Drupal\Core\Conflict\ConflictResolver\ConflictResolverManagerInterface $conflict_resolver */
    $conflict_resolver = $this->container->get('conflict.resolver.manager');

    $expectedEvent = new EntityConflictDiscoveryEvent($local, $remote, $base, NULL);

    $this->conflict_discovery_a
      ->expects($this->once())
      ->method('discoverConflictsContentEntity')
      ->with($expectedEvent)
      ->willReturnCallback(function (EntityConflictDiscoveryEvent $event) {
      // Don't add a conflict.
    });

    $conflicts = $conflict_resolver->getConflicts($local, $remote, $base);
    $this->assertEmpty($conflicts);
  }

  /**
   * Test a simple conflict detection.
   */
  public function testDiscoverOneConflict() {
    $base = EntityTest::create(['name' => 'A']);
    $local = EntityTest::create(['name' => 'B']);
    $remote = EntityTest::create(['name' => 'C']);

    /** @var \Drupal\Core\Conflict\ConflictResolver\ConflictResolverManagerInterface $conflict_resolver */
    $conflict_resolver = $this->container->get('conflict.resolver.manager');

    $this->conflict_discovery_a
      ->expects($this->once())
      ->method('discoverConflictsContentEntity')
      ->willReturnCallback(function (EntityConflictDiscoveryEvent $event) {
        $event->addConflict('name', 'custom');
      });

    $conflicts = $conflict_resolver->getConflicts($local, $remote, $base);
    $this->assertEquals(['name' => 'custom'], $conflicts);
  }

  /**
   * Test multiple conflicts by multiple discoveries.
   */
  public function testDiscoverMultipleConflicts() {
    $base = EntityTest::create(['name' => 'A']);
    $local = EntityTest::create(['name' => 'B']);
    $remote = EntityTest::create(['name' => 'C']);

    /** @var \Drupal\Core\Conflict\ConflictResolver\ConflictResolverManagerInterface $conflict_resolver */
    $conflict_resolver = $this->container->get('conflict.resolver.manager');

    $this->conflict_discovery_a
      ->expects($this->once())
      ->method('discoverConflictsContentEntity')
      ->willReturnCallback(function (EntityConflictDiscoveryEvent $event) {
        $event->addConflict('a', 'foo');
      });

    $this->conflict_discovery_b
      ->expects($this->once())
      ->method('discoverConflictsContentEntity')
      ->willReturnCallback(function (EntityConflictDiscoveryEvent $event) {
        $event->addConflict('b', 'bar');
      });

    $conflicts = $conflict_resolver->getConflicts($local, $remote, $base);
    $this->assertEquals(['a' => 'foo', 'b' => 'bar'], $conflicts);
  }

  /**
   * Test a conflict without a resolution strategy.
   */
  public function testNoResolution() {
    $base = EntityTest::create(['name' => 'A']);
    $local = EntityTest::create(['name' => 'B']);
    $remote = EntityTest::create(['name' => 'C']);

    /** @var \Drupal\Core\Conflict\ConflictResolver\ConflictResolverManagerInterface $conflict_resolver */
    $conflict_resolver = $this->container->get('conflict.resolver.manager');

    $this->conflict_discovery_a
      ->expects($this->once())
      ->method('discoverConflictsContentEntity')
      ->willReturnCallback(function (EntityConflictDiscoveryEvent $event) {
        $event->addConflict('name', 'foo');
      });

    $this->conflict_resolution_a
      ->expects($this->once())
      ->method('resolveConflictsContentEntity')
      ->willReturnCallback(function (EntityConflictResolutionEvent $event) {
        // No resolution in this case.
      });

    $conflicts = $conflict_resolver->resolveConflicts($local, $remote, $base);
    $this->assertEquals(['name' => 'foo'], $conflicts);
  }

  /**
   * Test a conflict with a matching resolution strategy.
   */
  public function testSimpleResolution() {
    $base = EntityTest::create(['name' => 'A']);
    $local = EntityTest::create(['name' => 'B']);
    $remote = EntityTest::create(['name' => 'C']);
    $result = EntityTest::create(['name' => 'X']);

    /** @var \Drupal\Core\Conflict\ConflictResolver\ConflictResolverManagerInterface $conflict_resolver */
    $conflict_resolver = $this->container->get('conflict.resolver.manager');

    $this->conflict_discovery_a
      ->expects($this->once())
      ->method('discoverConflictsContentEntity')
      ->willReturnCallback(function (EntityConflictDiscoveryEvent $event) {
        $event->addConflict('name', 'foo');
      });

    $this->conflict_resolution_a
      ->expects($this->once())
      ->method('resolveConflictsContentEntity')
      ->willReturnCallback(function (EntityConflictResolutionEvent $event) {
        $event->removeConflict('name');
        $event->getResultEntity()->set('name', 'bar');
      });

    $conflicts = $conflict_resolver->resolveConflicts($local, $remote, $base, $result);
    $this->assertEquals([], $conflicts);
    $this->assertEquals('bar', $result->get('name')->value);
  }

  /**
   * Test multiple conflicts with multiple resolution strategies.
   */
  public function testMultipleResolutions() {
    $base = EntityTest::create(['name' => 'A']);
    $local = EntityTest::create(['name' => 'B']);
    $remote = EntityTest::create(['name' => 'C']);
    $result = EntityTest::create(['name' => 'X']);

    /** @var \Drupal\Core\Conflict\ConflictResolver\ConflictResolverManagerInterface $conflict_resolver */
    $conflict_resolver = $this->container->get('conflict.resolver.manager');

    $this->conflict_discovery_a
      ->expects($this->once())
      ->method('discoverConflictsContentEntity')
      ->willReturnCallback(function (EntityConflictDiscoveryEvent $event) {
        $event->addConflict('a', 'foo');
        $event->addConflict('b', 'bar');
      });

    $this->conflict_resolution_a
      ->expects($this->once())
      ->method('resolveConflictsContentEntity')
      ->willReturnCallback(function (EntityConflictResolutionEvent $event) {
        $event->removeConflict('a');
      });

    $this->conflict_resolution_b
      ->expects($this->once())
      ->method('resolveConflictsContentEntity')
      ->willReturnCallback(function (EntityConflictResolutionEvent $event) {
        $event->removeConflict('b');
      });

    $conflicts = $conflict_resolver->resolveConflicts($local, $remote, $base, $result);
    $this->assertEquals([], $conflicts);
  }

  /**
   * Test disabled resolution strategies.
   *
   * Simultaneously tests context parameters.
   */
  public function testDisabledResolution() {
    $base = EntityTest::create(['name' => 'A']);
    $local = EntityTest::create(['name' => 'B']);
    $remote = EntityTest::create(['name' => 'C']);
    $result = EntityTest::create(['name' => 'X']);

    /** @var \Drupal\Core\Conflict\ConflictResolver\ConflictResolverManagerInterface $conflict_resolver */
    $conflict_resolver = $this->container->get('conflict.resolver.manager');

    $this->conflict_discovery_a
      ->expects($this->once())
      ->method('discoverConflictsContentEntity')
      ->willReturnCallback(function (EntityConflictDiscoveryEvent $event) {
        $event->addConflict('a', 'foo');
        $event->addConflict('b', 'bar');
      });

    $this->conflict_resolution_a
      ->expects($this->once())
      ->method('resolveConflictsContentEntity')
      ->willReturnCallback(function (EntityConflictResolutionEvent $event) {
        $event->removeConflict('a');
      });

    $this->conflict_resolution_a
      ->expects($this->once())
      ->method('getMergeStrategyId')
      ->willReturn('a');

    $this->conflict_resolution_b
      ->expects($this->once())
      ->method('resolveConflictsContentEntity')
      ->willReturnCallback(function (EntityConflictResolutionEvent $event) {
        // Do nothing in this test.
      });

    $this->conflict_resolution_b
      ->expects($this->once())
      ->method('getMergeStrategyId')
      ->willReturn('b');

    $parameters = new ParameterBag();
    $parameters->set('merge_strategy.disabled', ['b']);

    $conflicts = $conflict_resolver->resolveConflicts($local, $remote, $base, $result);
    $this->assertEquals(['b' => 'bar'], $conflicts);
  }

}
