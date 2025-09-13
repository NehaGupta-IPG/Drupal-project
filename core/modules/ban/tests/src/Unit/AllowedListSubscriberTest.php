<?php

declare(strict_types=1);

namespace Drupal\Tests\ban\Unit;

use Drupal\ban\Event\BanEvent;
use Drupal\ban\EventSubscriber\AllowedListSubscriber;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for the AllowedListSubscriber.
 *
 * @group ban
 * @covers \Drupal\ban\EventSubscriber\AllowedListSubscriber
 */
final class AllowedListSubscriberTest extends UnitTestCase {

  public function testIpOnAllowedListSetsIsBannedFalse(): void {
    $ip = '203.0.113.5';

    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())
      ->method('condition')
      ->with('allowedIp', $ip)
      ->willReturnSelf();
    $query->expects($this->once())
      ->method('execute')
      ->willReturn(['id_' . $ip]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())
      ->method('getQuery')
      ->willReturn($query);

    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $etm->expects($this->once())
      ->method('getStorage')
      ->with('ban_allowed_ip')
      ->willReturn($storage);

    $subscriber = new AllowedListSubscriber($etm);

    $event = new BanEvent($ip, TRUE);
    $subscriber->checkAllowedList($event);

    $this->assertFalse($event->isBanned, 'Allowed IP should be vetoed.');
  }

  public function testIpNotOnAllowedListKeepsIsBannedTrue(): void {
    $ip = '203.0.113.6';

    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())
      ->method('condition')
      ->with('allowedIp', $ip)
      ->willReturnSelf();
    $query->expects($this->once())
      ->method('execute')
      ->willReturn([]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())
      ->method('getQuery')
      ->willReturn($query);

    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $etm->expects($this->once())
      ->method('getStorage')
      ->with('ban_allowed_ip')
      ->willReturn($storage);

    $subscriber = new AllowedListSubscriber($etm);

    $event = new BanEvent($ip, TRUE);
    $subscriber->checkAllowedList($event);

    $this->assertTrue($event->isBanned, 'Non-allowed IP remains bannable.');
  }

}
