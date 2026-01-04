<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate\Unit\Event;

use Drupal\migrate\Row;
use Drupal\migrate\MigrateMessageInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\migrate\Event\MigratePostRowSaveEvent.
 */
#[CoversClass(MigratePostRowSaveEvent::class)]
#[Group('migrate')]
class MigratePostRowSaveEventTest extends EventBaseTest {

  /**
   * Tests getDestinationIdValues method.
   *
   * @legacy-covers ::__construct
   * @legacy-covers ::getDestinationIdValues
   */
  public function testGetDestinationIdValues(): void {
    $migration = $this->prophesize(MigrationInterface::class)->reveal();
    $message_service = $this->prophesize(MigrateMessageInterface::class)->reveal();
    $row = $this->prophesize(Row::class)->reveal();
    $event = new MigratePostRowSaveEvent($migration, $message_service, $row, [1, 2, 3]);
    $this->assertSame([1, 2, 3], $event->getDestinationIdValues());
  }

  /**
   * Tests getRow method.
   *
   * @legacy-covers ::__construct
   * @legacy-covers ::getRow
   */
  public function testGetRow(): void {
    $migration = $this->prophesize(MigrationInterface::class)->reveal();
    $message_service = $this->prophesize(MigrateMessageInterface::class);
    $row = $this->prophesize(Row::class)->reveal();
    $event = new MigratePostRowSaveEvent($migration, $message_service->reveal(), $row, [1, 2, 3]);
    $this->assertSame($row, $event->getRow());
  }

}
