<?php

declare(strict_types=1);

namespace Drupal\Tests\ban\Unit;

use Drupal\ban\Form\BanAdmin;
use Drupal\ban\BanIpManagerInterface;
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

    $manager = $this->createMock(BanIpManagerInterface::class);
    $manager->method('isBanned')->with($ip)->willReturn(FALSE);

    // If your BanAdmin checks 'isAllowed' on the manager, mock it here.
    if (method_exists($manager, 'isAllowed')) {
      $manager->method('isAllowed')->with($ip)->willReturn(TRUE);
    }

    $form = new BanAdmin($manager);
    $form->setStringTranslation($this->getStringTranslationStub());

    $state = $this->createMock(FormStateInterface::class);
    $state->method('getValue')->with('ip')->willReturn($ip);
    $state->expects($this->once())
      ->method('setErrorByName')
      ->with('ip', $this->anything());

    $form->validateForm([], $state);
  }

}
