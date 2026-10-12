<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Theme;

use Drupal\Core\Cache\NullBackend;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\HookData;
use Drupal\Core\KeyValueStore\KeyValueMemoryFactory;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Core\Theme\ThemeManager;
use Drupal\Core\Theme\ThemeNegotiatorInterface;
use Drupal\Core\Utility\CallableResolver;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Symfony\Contracts\Service\ServiceCollectionInterface;

/**
 * Tests the theme manager.
 */
#[CoversClass(ThemeManager::class)]
#[Group('Theme')]
class ThemeManagerTest extends UnitTestCase {

  /**
   * Tests the deprecated key value factory constructor argument.
   */
  #[IgnoreDeprecations]
  public function testConstructorKeyValueFactoryArgument(): void {
    $this->expectUserDeprecationMessage('Calling Drupal\Core\Theme\ThemeManager::__construct() with the $keyValueFactory argument is deprecated in drupal:12.1.0 and is replaced by $hookData in drupal:13.0.0. See https://www.drupal.org/node/3620769');
    $hook_data = new HookData(new KeyValueMemoryFactory(), new NullBackend('bootstrap'));
    $container = new ContainerBuilder();
    $container->set(HookData::class, $hook_data);
    \Drupal::setContainer($container);
    $theme_manager = $this->createThemeManager(new KeyValueMemoryFactory(), new NullBackend('bootstrap'));
    $this->assertSame($hook_data, (new \ReflectionProperty($theme_manager, 'hookData'))->getValue($theme_manager));
  }

  /**
   * Tests the deprecated cache constructor argument.
   */
  #[IgnoreDeprecations]
  public function testConstructorCacheArgument(): void {
    $this->expectUserDeprecationMessage('Calling Drupal\Core\Theme\ThemeManager::__construct() with the $cache argument is deprecated in drupal:12.1.0 and is removed from drupal:13.0.0. See https://www.drupal.org/node/3620769');
    $hook_data = new HookData(new KeyValueMemoryFactory(), new NullBackend('bootstrap'));
    $theme_manager = $this->createThemeManager($hook_data, new NullBackend('bootstrap'));
    $this->assertSame($hook_data, (new \ReflectionProperty($theme_manager, 'hookData'))->getValue($theme_manager));
  }

  /**
   * Creates a theme manager with stubbed dependencies.
   *
   * @param mixed ...$args
   *   The hook data argument and any further constructor arguments.
   *
   * @return \Drupal\Core\Theme\ThemeManager
   *   The theme manager.
   */
  protected function createThemeManager(mixed ...$args): ThemeManager {
    return new ThemeManager(
      $this->root,
      $this->createStub(ThemeNegotiatorInterface::class),
      $this->createStub(ThemeInitializationInterface::class),
      $this->createStub(ModuleHandlerInterface::class),
      $this->createStub(CallableResolver::class),
      $this->createStub(ServiceCollectionInterface::class),
      ...$args,
    );
  }

}
