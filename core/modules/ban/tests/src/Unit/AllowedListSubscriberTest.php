<?php

declare(strict_types=1);

namespace Drupal\Tests\ban\Unit;

use Drupal\ban\Event\BanEvent;
use Drupal\ban\EventSubscriber\AllowedListSubscriber;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())
      ->method('getQuery')
      ->willReturn(new class($ip) {
        private string $ip;
        public function __construct(string $ip) { $this->ip = $ip; }
        public function condition($field, $value) { return $this; }
        public function execute() { return ['id_' . $this->ip]; }
      });

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

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())
      ->method('getQuery')
      ->willReturn(new class {
        public function condition($field, $value) { return $this; }
        public function execute() { return []; }
      });

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
