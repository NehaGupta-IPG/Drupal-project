<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Conflict;

use Drupal\Core\Conflict\ConflictDiscovery\ConflictDiscoveryBase;
use Drupal\Core\Conflict\ConflictDiscovery\ConflictDiscoveryInterface;
use Drupal\Core\Conflict\ConflictDiscovery\DefaultConflictDiscovery;
use Drupal\Core\Conflict\ConflictResolution\MergeRemoteOnlyChanges;
use Drupal\Core\Conflict\ConflictResolution\MergeStrategyBase;
use Drupal\Core\Conflict\ConflictResolution\MergeStrategyInterface;
use Drupal\Core\Conflict\ConflictResolver\ConflictResolverManagerInterface;
use Drupal\Core\Conflict\ConflictTypeInterface;
use Drupal\Core\Conflict\Event\EntityConflictDiscoveryEvent;
use Drupal\Core\Conflict\Event\EntityConflictResolutionEvent;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Test cases for the event based conflict resolution system.
 */
#[Group('Conflict')]
class ConflictFrameworkTest extends KernelTestBase implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'entity_test',
    'user',
  ];

  /**
   * Test conflict discovery A.
   *
   * @var \Drupal\Core\Conflict\ConflictDiscovery\ConflictDiscoveryInterface
   */
  protected ConflictDiscoveryInterface $conflictDiscoveryA;

  /**
   * Test conflict discovery B.
   *
   * @var \Drupal\Core\Conflict\ConflictDiscovery\ConflictDiscoveryInterface
   */
  protected ConflictDiscoveryInterface $conflictDiscoveryB;

  /**
   * Test conflict resolution A.
   *
   * @var \Drupal\Core\Conflict\ConflictResolution\MergeStrategyInterface
   */
  protected MergeStrategyInterface $conflictResolutionA;

  /**
   * Test conflict resolution A.
   *
   * @var \Drupal\Core\Conflict\ConflictResolution\MergeStrategyInterface
   */
  protected MergeStrategyInterface $conflictResolutionB;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test');
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);

    $container->get(EventDispatcherInterface::class)->addSubscriber($this);
    // Remove the default implementations for the sake of this test.
    $container->removeDefinition(DefaultConflictDiscovery::class);
    $container->removeDefinition(MergeRemoteOnlyChanges::class);
  }

  /**
   * Test no conflicts detected.
   */
  public function testDiscoverNoConflicts(): void {
    $base = EntityTest::create(['name' => 'A']);
    $local = EntityTest::create(['name' => 'B']);
    $remote = EntityTest::create(['name' => 'C']);

    /** @var \Drupal\Core\Conflict\ConflictResolver\ConflictResolverManagerInterface $conflict_resolver */
    $conflict_resolver = $this->container->get(ConflictResolverManagerInterface::class);
    $this->conflictDiscoveryA = new NoConflict();
    $this->conflictDiscoveryB = new NoConflict();

    $conflicts = $conflict_resolver->getConflicts($local, $remote, $base);
    self::assertCount(0, $conflicts);
  }

  /**
   * Test a simple conflict detection.
   */
  public function testDiscoverOneConflict(): void {
    $base = EntityTest::create(['name' => 'A']);
    $local = EntityTest::create(['name' => 'B']);
    $remote = EntityTest::create(['name' => 'C']);

    /** @var \Drupal\Core\Conflict\ConflictResolver\ConflictResolverManagerInterface $conflict_resolver */
    $conflict_resolver = $this->container->get(ConflictResolverManagerInterface::class);

    $this->conflictDiscoveryA = new CustomConflict();
    $this->conflictDiscoveryB = new NoConflict();

    $conflicts = $conflict_resolver->getConflicts($local, $remote, $base);
    self::assertEquals(['name' => CustomFooBarConflictType::Custom], $conflicts);
  }

  /**
   * Test multiple conflicts by multiple discoveries.
   */
  public function testDiscoverMultipleConflicts(): void {
    $base = EntityTest::create(['name' => 'A']);
    $local = EntityTest::create(['name' => 'B']);
    $remote = EntityTest::create(['name' => 'C']);

    /** @var \Drupal\Core\Conflict\ConflictResolver\ConflictResolverManagerInterface $conflict_resolver */
    $conflict_resolver = $this->container->get(ConflictResolverManagerInterface::class);

    $this->conflictDiscoveryA = new FooConflict();
    $this->conflictDiscoveryB = new BarConflict();

    $conflicts = $conflict_resolver->getConflicts($local, $remote, $base);
    self::assertEquals([
      'foo' => CustomFooBarConflictType::Foo,
      'bar' => CustomFooBarConflictType::Bar,
    ], $conflicts);
  }

  /**
   * Test a conflict without a resolution strategy.
   */
  public function testNoResolution(): void {
    $base = EntityTest::create(['name' => 'A']);
    $local = EntityTest::create(['name' => 'B']);
    $remote = EntityTest::create(['name' => 'C']);

    /** @var \Drupal\Core\Conflict\ConflictResolver\ConflictResolverManagerInterface $conflict_resolver */
    $conflict_resolver = $this->container->get(ConflictResolverManagerInterface::class);

    $this->conflictDiscoveryA = new FooConflict();
    $this->conflictDiscoveryB = new NoConflict();

    $this->conflictResolutionA = new NoResolution();
    $this->conflictResolutionB = new NoResolution();

    $conflicts = $conflict_resolver->resolveConflicts($local, $remote, $base);
    self::assertEquals([
      'foo' => CustomFooBarConflictType::Foo,
    ], $conflicts);
  }

  /**
   * Test a conflict with a matching resolution strategy.
   */
  public function testSimpleResolution(): void {
    $base = EntityTest::create(['name' => 'A']);
    $local = EntityTest::create(['name' => 'B']);
    $remote = EntityTest::create(['name' => 'C']);
    $result = EntityTest::create(['name' => 'X']);

    /** @var \Drupal\Core\Conflict\ConflictResolver\ConflictResolverManagerInterface $conflict_resolver */
    $conflict_resolver = $this->container->get(ConflictResolverManagerInterface::class);

    $this->conflictDiscoveryA = new FooConflict();
    $this->conflictDiscoveryB = new NoConflict();

    $this->conflictResolutionA = new ResolveNameAsBar();
    $this->conflictResolutionB = new NoResolution();

    $conflicts = $conflict_resolver->resolveConflicts($local, $remote, $base, $result);
    self::assertEquals([], $conflicts);
    self::assertEquals('bar', $result->get('name')->value);
  }

  /**
   * Test multiple conflicts with multiple resolution strategies.
   */
  public function testMultipleResolutions(): void {
    $base = EntityTest::create(['name' => 'A']);
    $local = EntityTest::create(['name' => 'B']);
    $remote = EntityTest::create(['name' => 'C']);
    $result = EntityTest::create(['name' => 'X']);

    /** @var \Drupal\Core\Conflict\ConflictResolver\ConflictResolverManagerInterface $conflict_resolver */
    $conflict_resolver = $this->container->get(ConflictResolverManagerInterface::class);

    $this->conflictDiscoveryA = new FooConflict();
    $this->conflictDiscoveryB = new BarConflict();

    $this->conflictResolutionA = new ResolveFoo();
    $this->conflictResolutionB = new ResolveBar();
    $conflicts = $conflict_resolver->getConflicts($local, $remote, $base);
    self::assertCount(2, $conflicts);

    $conflicts = $conflict_resolver->resolveConflicts($local, $remote, $base, $result);
    self::assertEquals([], $conflicts);
  }

  /**
   * Test disabled resolution strategies.
   *
   * Simultaneously tests context parameters.
   */
  public function testDisabledResolution(): void {
    $base = EntityTest::create(['name' => 'A']);
    $local = EntityTest::create(['name' => 'B']);
    $remote = EntityTest::create(['name' => 'C']);
    $result = EntityTest::create(['name' => 'X']);

    /** @var \Drupal\Core\Conflict\ConflictResolver\ConflictResolverManagerInterface $conflict_resolver */
    $conflict_resolver = $this->container->get(ConflictResolverManagerInterface::class);

    $this->conflictDiscoveryA = new FooConflict();
    $this->conflictDiscoveryB = new BarConflict();

    $this->conflictResolutionA = new ResolveFoo();
    $this->conflictResolutionB = new ResolveBar();

    $parameters = new ParameterBag();
    $parameters->set('merge_strategy.disabled', ['bar']);

    $conflicts = $conflict_resolver->resolveConflicts($local, $remote, $base, $result, $parameters);
    $this->assertEquals([
      'bar' => CustomFooBarConflictType::Bar,
    ], $conflicts);
  }

  /**
   * Event listener.
   */
  public function discoverConflicts(EntityConflictDiscoveryEvent $event): void {
    $this->conflictDiscoveryA->discoverConflicts($event);
    $this->conflictDiscoveryB->discoverConflicts($event);
  }

  /**
   * Event listener.
   */
  public function resolveConflicts(EntityConflictResolutionEvent $event): void {
    $this->conflictResolutionA->resolveConflicts($event);
    $this->conflictResolutionB->resolveConflicts($event);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      EntityConflictResolutionEvent::class => [['resolveConflicts']],
      EntityConflictDiscoveryEvent::class => [['discoverConflicts']],
    ];

  }

}
// @phpcs:disable
class NoConflict extends ConflictDiscoveryBase {

  protected function discoverContentEntityConflicts(EntityConflictDiscoveryEvent $event): void {
  }

}

enum CustomFooBarConflictType: string implements ConflictTypeInterface {
  case Foo = 'foo';
  case Bar = 'bar';
  case Custom = 'custom';
}

class CustomConflict extends ConflictDiscoveryBase {

  protected function discoverContentEntityConflicts(EntityConflictDiscoveryEvent $event): void {
    $event->addConflict('name', CustomFooBarConflictType::Custom);
  }

}


class FooConflict extends ConflictDiscoveryBase {

  protected function discoverContentEntityConflicts(EntityConflictDiscoveryEvent $event): void {
    $event->addConflict('foo', CustomFooBarConflictType::Foo);
  }

}

class BarConflict extends ConflictDiscoveryBase {

  protected function discoverContentEntityConflicts(EntityConflictDiscoveryEvent $event): void {
    $event->addConflict('bar', CustomFooBarConflictType::Bar);
  }

}

class FooBarConflict extends ConflictDiscoveryBase {

  protected function discoverContentEntityConflicts(EntityConflictDiscoveryEvent $event): void {
    $event->addConflict('foo', CustomFooBarConflictType::Foo);
    $event->addConflict('bar', CustomFooBarConflictType::Bar);
  }

}

class NoResolution extends MergeStrategyBase {

  protected function getMergeStrategyId(): string {
    return 'no-op';
  }

  protected function resolveContentEntityConflicts(EntityConflictResolutionEvent $event): void {
  }

}

class ResolveFoo extends MergeStrategyBase {

  protected function getMergeStrategyId(): string {
    return 'foo';
  }

  protected function resolveContentEntityConflicts(EntityConflictResolutionEvent $event): void {
    $event->removeConflict('foo');
  }

}

class ResolveBar extends MergeStrategyBase {

  protected function getMergeStrategyId(): string {
    return 'bar';
  }

  protected function resolveContentEntityConflicts(EntityConflictResolutionEvent $event): void {
    $event->removeConflict('bar');
  }

}

class ResolveNameAsBar extends MergeStrategyBase {

  protected function getMergeStrategyId(): string {
    return 'foo-as-bar';
  }

  protected function resolveContentEntityConflicts(EntityConflictResolutionEvent $event): void {
    $event->removeConflict('foo');
    $event->getResultEntity()->set('name', 'bar');
  }

}
