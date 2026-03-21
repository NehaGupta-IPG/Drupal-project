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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;

/**
 * Tests deprecations in LocalTaskManager.
 */
#[CoversClass(LocalTaskManager::class)]
#[Group('Menu')]
#[RunTestsInSeparateProcesses]
#[IgnoreDeprecations]
class LegacyLocalTaskManagerTest extends KernelTestBase {

  /**
   * Tests the constructor deprecations.
   */
  public function testConstructorDeprecation(): void {
    $request_stack = new RequestStack();
    $request_stack->push(new Request());
    $language_manager = $this->createMock(LanguageManagerInterface::class);
    $language_manager
      ->method('getCurrentLanguage')
      ->willReturn(new Language(['id' => 'en']));
    $this->expectUserDeprecationMessage('Calling Drupal\Core\Menu\LocalTaskManager::__construct() without the $logger argument is deprecated in drupal:11.4.0 and it will be required in drupal:12.0.0. See https://www.drupal.org/node/3443775');
    $manager = new LocalTaskManager(
      $this->createStub(ArgumentResolverInterface::class),
      $request_stack,
      $this->createStub(RouteMatchInterface::class),
      $this->createStub(RouteProviderInterface::class),
      $this->createStub(ModuleHandlerInterface::class),
      $this->prophesize(CacheBackendInterface::class)->reveal(),
      $language_manager,
      $this->createStub(AccessManagerInterface::class),
      $this->createStub(AccountInterface::class));
    $this->assertNotNull($manager);
  }

}
