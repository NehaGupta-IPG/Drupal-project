<?php

declare(strict_types=1);

namespace Drupal\Tests\navigation\Unit;

use Drupal\Core\Config\Action\ConfigActionManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Menu\LocalTaskManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Drupal\navigation\EntityRouteHelper;
use Drupal\navigation\Hook\NavigationHooks;
use Drupal\navigation\NavigationRenderer;
use Drupal\navigation\TopBarItemManagerInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests hook implementations for the Navigation module.
 */
#[CoversClass(NavigationHooks::class)]
#[Group('navigation')]
class NavigationHooksTest extends UnitTestCase {

  /**
   * Tests that modules without navigation defaults do not stop later modules.
   */
  public function testModulesInstalledContinuesWhenModuleHasNoNavigationDefaults(): void {
    $block = [
      'delta' => 1,
      'configuration' => [
        'id' => 'test_block',
      ],
    ];

    $module_handler = $this->createMock(ModuleHandlerInterface::class);
    $module_handler->expects($this->exactly(2))
      ->method('invoke')
      ->willReturnCallback(function (string $module, string $hook) use ($block): ?array {
        $this->assertSame('navigation_defaults', $hook);
        return match ($module) {
          'invalid_module' => NULL,
          'valid_module' => [$block],
        };
      });

    $config_action_manager = $this->createMock(ConfigActionManager::class);
    $config_action_manager->expects($this->once())
      ->method('applyAction')
      ->with('addNavigationBlock', 'navigation.block_layout', $block);

    $current_user = $this->createStub(AccountInterface::class);
    $navigation_renderer = new NavigationRenderer(
      $this->createStub(ConfigFactoryInterface::class),
      $module_handler,
      $this->createStub(RouteMatchInterface::class),
      $this->createStub(LocalTaskManagerInterface::class),
      $this->createStub(ImageFactory::class),
      $this->createStub(FileUrlGeneratorInterface::class),
      $this->createStub(SectionStorageManagerInterface::class),
      new RequestStack(),
      $this->createStub(ModuleExtensionList::class),
      $current_user,
      $this->createStub(EntityRouteHelper::class),
    );

    $hooks = new NavigationHooks(
      $module_handler,
      $current_user,
      $navigation_renderer,
      $config_action_manager,
      $this->createStub(TopBarItemManagerInterface::class),
    );

    $hooks->modulesInstalled(['invalid_module', 'valid_module'], FALSE);
  }

}
