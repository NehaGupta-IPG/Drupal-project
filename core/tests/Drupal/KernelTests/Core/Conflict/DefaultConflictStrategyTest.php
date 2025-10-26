<?php

namespace Drupal\KernelTests\Core\Conflict;

use Drupal\Core\Conflict\ConflictTypes;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\KernelTestBase;

/**
 * Test cases for conflict resolution default implementations.
 *
 * @group Conflict
 */
class DefaultConflictStrategyTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'system',
    'entity_test',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('entity_test');
  }

  /**
   * No changes.
   */
  public function testNoChange() {
    $base = EntityTest::create(['name' => 'A']);
    $local = EntityTest::create(['name' => 'A']);
    $remote = EntityTest::create(['name' => 'A']);

    /** @var \Drupal\Core\Conflict\ConflictResolver\ConflictResolverManagerInterface $conflict_resolver */
    $conflict_resolver = $this->container->get('conflict.resolver.manager');

    $conflicts = $conflict_resolver->getConflicts($local, $remote, $base);
    $this->assertEquals([], $conflicts);
  }

  /**
   * Only the local entity changed.
   */
  public function testLocalChange() {
    $base = EntityTest::create(['name' => 'A']);
    $local = EntityTest::create(['name' => 'B']);
    $remote = EntityTest::create(['name' => 'A']);

    /** @var \Drupal\Core\Conflict\ConflictResolver\ConflictResolverManagerInterface $conflict_resolver */
    $conflict_resolver = $this->container->get('conflict.resolver.manager');

    $conflicts = $conflict_resolver->getConflicts($local, $remote, $base);
    $this->assertEquals([], $conflicts);
  }

  /**
   * Remote entity changed.
   */
  public function testRemoteChange() {
    $base = EntityTest::create(['name' => 'A']);
    $local = EntityTest::create(['name' => 'A']);
    $remote = EntityTest::create(['name' => 'B']);

    /** @var \Drupal\Core\Conflict\ConflictResolver\ConflictResolverManagerInterface $conflict_resolver */
    $conflict_resolver = $this->container->get('conflict.resolver.manager');

    $conflicts = $conflict_resolver->getConflicts($local, $remote, $base);
    $this->assertEquals([
      'name' => ConflictTypes::CONFLICT_TYPE_REMOTE
    ], $conflicts);
  }

  /**
   * Both entities changed.
   */
  public function testBothChanged() {
    $base = EntityTest::create(['name' => 'B']);
    $local = EntityTest::create(['name' => 'A']);
    $remote = EntityTest::create(['name' => 'C']);

    /** @var \Drupal\Core\Conflict\ConflictResolver\ConflictResolverManagerInterface $conflict_resolver */
    $conflict_resolver = $this->container->get('conflict.resolver.manager');

    $conflicts = $conflict_resolver->getConflicts($local, $remote, $base);
    $this->assertEquals([
      'name' => ConflictTypes::CONFLICT_TYPE_LOCAL_REMOTE
    ], $conflicts);
  }

  /**
   * If only the remote changed, auto-resolve the conflict.
   */
  public function testResolveRemoteOnlyChange() {
    $base = EntityTest::create(['name' => 'A']);
    $local = EntityTest::create(['name' => 'A']);
    $remote = EntityTest::create(['name' => 'C']);
    $result = EntityTest::create();

    /** @var \Drupal\Core\Conflict\ConflictResolver\ConflictResolverManagerInterface $conflict_resolver */
    $conflict_resolver = $this->container->get('conflict.resolver.manager');

    $conflicts = $conflict_resolver->resolveConflicts($local, $remote, $base, $result);
    $this->assertEquals([], $conflicts);
    $this->assertEquals('C', $result->get('name')->value);
  }

  /**
   * If both entities changed, don't auto-resolve the conflict.
   */
  public function testResolveLocalRemoteChange() {
    $base = EntityTest::create(['name' => 'A']);
    $local = EntityTest::create(['name' => 'B']);
    $remote = EntityTest::create(['name' => 'C']);
    $result = EntityTest::create();

    /** @var \Drupal\Core\Conflict\ConflictResolver\ConflictResolverManagerInterface $conflict_resolver */
    $conflict_resolver = $this->container->get('conflict.resolver.manager');

    $conflicts = $conflict_resolver->resolveConflicts($local, $remote, $base, $result);
    $this->assertEquals([
      'name' => ConflictTypes::CONFLICT_TYPE_LOCAL_REMOTE
    ], $conflicts);
  }

}
