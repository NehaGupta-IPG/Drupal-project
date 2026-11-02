<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Conflict;

use Drupal\Core\Conflict\ConflictResolver\ConflictResolverManagerInterface;
use Drupal\Core\Conflict\ConflictType;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test cases for conflict resolution default implementations.
 */
#[Group('Conflict')]
#[RunTestsInSeparateProcesses()]
class DefaultConflictStrategyTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'entity_test',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('entity_test');
  }

  /**
   * No changes.
   */
  public function testNoChange(): void {
    $base = EntityTest::create(['name' => 'A']);
    $ours = EntityTest::create(['name' => 'A']);
    $theirs = EntityTest::create(['name' => 'A']);

    /** @var \Drupal\Core\Conflict\ConflictResolver\ConflictResolverManagerInterface $conflict_resolver */
    $conflict_resolver = $this->container->get(ConflictResolverManagerInterface::class);

    $conflicts = $conflict_resolver->getConflicts($ours, $theirs, $base);
    $this->assertEquals([], $conflicts);
  }

  /**
   * Only the ours entity changed.
   */
  public function testOursChange(): void {
    $base = EntityTest::create(['name' => 'A']);
    $ours = EntityTest::create(['name' => 'B']);
    $theirs = EntityTest::create(['name' => 'A']);

    /** @var \Drupal\Core\Conflict\ConflictResolver\ConflictResolverManagerInterface $conflict_resolver */
    $conflict_resolver = $this->container->get(ConflictResolverManagerInterface::class);

    $conflicts = $conflict_resolver->getConflicts($ours, $theirs, $base);
    $this->assertEquals([], $conflicts);
  }

  /**
   * Both entities changed.
   */
  public function testBothChanged(): void {
    $base = EntityTest::create(['name' => 'B']);
    $ours = EntityTest::create(['name' => 'A']);
    $theirs = EntityTest::create(['name' => 'C']);

    /** @var \Drupal\Core\Conflict\ConflictResolver\ConflictResolverManagerInterface $conflict_resolver */
    $conflict_resolver = $this->container->get(ConflictResolverManagerInterface::class);

    $conflicts = $conflict_resolver->getConflicts($ours, $theirs, $base);
    $this->assertEquals([
      'name' => ConflictType::Both,
    ], $conflicts);
  }

  /**
   * Tests that a theirs-only change is detected and then auto-resolved.
   */
  public function testResolveTheirsOnlyChange(): void {
    $base = EntityTest::create(['name' => 'A']);
    $ours = EntityTest::create(['name' => 'A']);
    $theirs = EntityTest::create(['name' => 'C']);
    $result = EntityTest::create();

    /** @var \Drupal\Core\Conflict\ConflictResolver\ConflictResolverManagerInterface $conflict_resolver */
    $conflict_resolver = $this->container->get(ConflictResolverManagerInterface::class);

    // Verify the conflict is detected as TheirsOnly.
    $conflicts = $conflict_resolver->getConflicts($ours, $theirs, $base);
    $this->assertEquals([
      'name' => ConflictType::TheirsOnly,
    ], $conflicts);

    // Verify the merge strategy auto-resolves it.
    $conflicts = $conflict_resolver->resolveConflicts($ours, $theirs, $base, $result);
    $this->assertEquals([], $conflicts);
    $this->assertEquals('C', $result->get('name')->value);
  }

  /**
   * Tests that Both conflicts are not auto-resolved.
   */
  public function testResolveBothChange(): void {
    $base = EntityTest::create(['name' => 'A']);
    $ours = EntityTest::create(['name' => 'B']);
    $theirs = EntityTest::create(['name' => 'C']);
    $result = EntityTest::create();

    /** @var \Drupal\Core\Conflict\ConflictResolver\ConflictResolverManagerInterface $conflict_resolver */
    $conflict_resolver = $this->container->get(ConflictResolverManagerInterface::class);

    $conflicts = $conflict_resolver->resolveConflicts($ours, $theirs, $base, $result);
    $this->assertEquals([
      'name' => ConflictType::Both,
    ], $conflicts);
  }

}
