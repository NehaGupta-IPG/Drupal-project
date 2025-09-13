<?php

declare(strict_types=1);

namespace Drupal\Tests\ban\Unit;

use Drupal\ban\BanIpManagerInterface;
use Drupal\ban\Form\BanAdmin;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests BanAdmin validation against allowed IPs.
 *
 * @group ban
 * @covers \Drupal\ban\Form\BanAdmin::validateForm
 */
final class BanAdminAllowedValidationTest extends UnitTestCase {

  public function testCannotBanAllowedIp(): void {
    $ip = '203.0.113.123';

    // Ban manager says it's not already banned.
    $manager = $this->createMock(BanIpManagerInterface::class);
    $manager->method('isBanned')->with($ip)->willReturn(FALSE);

    // Entity query returns a non-empty result => IP is on the allowed list.
    $query = $this->createMock(QueryInterface::class);
    $query->method('condition')->with('allowedIp', $ip)->willReturnSelf();
    $query->method('execute')->willReturn(['id_' . $ip]);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('getQuery')->willReturn($query);

    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $etm->method('getStorage')->with('ban_allowed_ip')->willReturn($storage);

    $form = new BanAdmin($manager, $etm);
    $form->setStringTranslation($this->getStringTranslationStub());

    $state = $this->createMock(FormStateInterface::class);
    $state->method('getValue')->with('ip')->willReturn($ip);
    $state->expects($this->once())
      ->method('setErrorByName')
      ->with('ip', $this->stringContains('allowed'));

    $form->validateForm([], $state);
  }

}
