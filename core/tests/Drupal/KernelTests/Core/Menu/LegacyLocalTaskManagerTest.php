<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Menu;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Menu\LocalTaskManager;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;

/**
 * Tests deprecations in LocalTaskManager.
 *
 * @group Menu
 * @group legacy
 * @coversDefaultClass \Drupal\Core\Menu\LocalTaskManager
 */
class LegacyLocalTaskManagerTest extends KernelTestBase {

  /**
   * Tests the constructor deprecations.
   *
   * @covers ::__construct
   *
   * @group legacy
   */
  public function testConstructorDeprecation(): void {
    $request_stack = new RequestStack();
    $request_stack->push(new Request());
    $language_manager = $this->createMock(LanguageManagerInterface::class);
    $language_manager
      ->method('getCurrentLanguage')
      ->willReturn(new Language(['id' => 'en']));
    $this->expectDeprecation('Calling Drupal\Core\Menu\LocalTaskManager::__construct() without the $logger argument is deprecated in drupal:10.3.0 and it will be required in drupal:11.0.0. See https://www.drupal.org/node/3443775');
    $manager = new LocalTaskManager(
      $this->createMock(ArgumentResolverInterface::class),
      $request_stack,
      $this->createMock(RouteMatchInterface::class),
      $this->createMock(RouteProviderInterface::class),
      $this->createMock(ModuleHandlerInterface::class),
      $this->prophesize(CacheBackendInterface::class)->reveal(),
      $language_manager,
      $this->createMock(AccessManagerInterface::class),
      $this->createMock(AccountInterface::class));
    $this->assertNotNull($manager);
  }

}
