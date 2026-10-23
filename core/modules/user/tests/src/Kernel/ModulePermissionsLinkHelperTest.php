<?php

declare(strict_types=1);

namespace Drupal\Tests\user\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\TestTools\Attribute\ShareEnvironment;
use Drupal\user\ModulePermissionsLinkHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the ModulePermissionsLinkHelper.
 */
#[CoversClass(ModulePermissionsLinkHelper::class)]
#[Group('user')]
#[RunTestsInSeparateProcesses]
#[ShareEnvironment]
class ModulePermissionsLinkHelperTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'user_permissions_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->setUpCurrentUser([], [
      'administer permissions',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUpEnvironment(): void {
    parent::setUpEnvironment();
    $this->installEntitySchema('user');
  }

  /**
   * Tests get module permissions link.
   */
  public function testGetModulePermissionsLink(): void {

    /** @var \Drupal\user\ModulePermissionsLinkHelper $permsLinkHelper */
    $permsLinkHelper = $this->container->get('user.module_permissions_link_helper');

    $permsLink = $permsLinkHelper->getModulePermissionsLink('user_permissions_test', 'User permissions test');

    $this->assertNotEmpty($permsLink);
    $this->assertEquals("Configure User permissions test permissions", $permsLink['title']);
    /** @var \Drupal\Core\Url $url */
    $url = $permsLink['url'];
    $this->assertEquals('user.admin_permissions.module', $url->getRouteName());
    $this->assertEquals('user_permissions_test', $url->getRouteParameters()['modules']);
  }

}
