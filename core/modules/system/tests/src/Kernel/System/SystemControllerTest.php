<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Kernel\System;

use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the SystemController class.
 */
#[Group('system')]
class SystemControllerTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->setUpCurrentUser([], [
      'access administration pages',
      'access site reports',
    ]);
  }

  /**
   * Test access to system.admin_reports route.
   */
  public function testAdminReportsAccess(): void {
    $url = Url::fromRoute('system.admin_reports');
    $this->assertTrue($url->access());
  }

  /**
   * Test access to system.admin_reports route with dblog enabled.
   */
  public function testAdminReportsAccessWithDblog(): void {
    \Drupal::service(ModuleInstallerInterface::class)->install(['dblog']);
    $url = Url::fromRoute('system.admin_reports');
    $this->assertTrue($url->access());
  }

}
