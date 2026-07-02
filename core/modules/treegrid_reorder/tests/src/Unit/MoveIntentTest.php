<?php

declare(strict_types=1);

namespace Drupal\Tests\treegrid_reorder\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\treegrid_reorder\MoveIntent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the MoveIntent value object.
 */
#[CoversClass(MoveIntent::class)]
#[Group('treegrid_reorder')]
final class MoveIntentTest extends UnitTestCase {

  /**
   * Construction exposes id, newParent and beforeId unchanged.
   */
  public function testConstructionExposesProperties(): void {
    $intent = new MoveIntent('a', 'b', 'c');

    $this->assertSame('a', $intent->id);
    $this->assertSame('b', $intent->newParent);
    $this->assertSame('c', $intent->beforeId);
  }

  /**
   * A NULL beforeId (append to the end) is allowed and preserved.
   */
  public function testBeforeIdNullIsAllowed(): void {
    $intent = new MoveIntent('a', 'b', NULL);

    $this->assertNull($intent->beforeId);
  }

  /**
   * An empty newParent denotes a move to the tree root and is preserved as-is.
   */
  public function testEmptyNewParentDenotesRoot(): void {
    $intent = new MoveIntent('a', '', NULL);

    $this->assertSame('', $intent->newParent);
  }

}
