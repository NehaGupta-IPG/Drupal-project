<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Kernel;

use Drupal\Core\Render\Element\PasswordConfirm;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the deprecation of password_confirm.
 */
#[Group('legacy')]
class PasswordConfirmDeprecationTest extends KernelTestBase {

  /**
   * Tests the deprecation of password_confirm element.
   */
  public function testPasswordConfirmDeprecation(): void {
    $this->expectDeprecation('\Drupal\Core\Render\Element\PasswordConfirm is deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use Core/Render/Element/PasswordUnmask instead. See https://www.drupal.org/node/3394247');
    // @phpstan-ignore-next-line
    new PasswordConfirm([], 'password_confirm', [
      'id' => 'password_confirm',
      'class' => 'Drupal\Core\Render\Element\PasswordConfirm',
      'provider' => 'core',
    ]);
  }

}
