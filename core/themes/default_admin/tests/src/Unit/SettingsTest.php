<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\Unit;

use Drupal\Core\DependencyInjection\ClassResolver;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\Core\Session\UserSession;
use Drupal\default_admin\Settings;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserDataInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the lifetime of admin settings dependencies.
 */
#[CoversClass(Settings::class)]
#[Group('default_admin')]
class SettingsTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    \Drupal::unsetContainer();
    parent::tearDown();
  }

  /**
   * Tests that settings operations use the current services and account.
   */
  #[DataProvider('containerReplacementProvider')]
  public function testCurrentDependencies(bool $replace_container): void {
    $container = new ContainerBuilder();
    foreach ([1, 2] as $uid) {
      if ($replace_container) {
        $container = new ContainerBuilder();
      }
      $container->set('class_resolver', new ClassResolver($container));
      $container->set('current_user', new UserSession(['uid' => $uid]));
      $container->set('config.factory', $this->getConfigFactoryStub([
        'system.theme' => ['admin' => 'default_admin'],
      ]));

      $theme_settings = $this->createMock(ThemeSettingsProvider::class);
      $theme_settings->expects($this->once())
        ->method('getSetting')
        ->with('enable_dark_mode', 'default_admin')
        ->willReturn($uid === 1);
      $container->set(ThemeSettingsProvider::class, $theme_settings);

      $user_data = $this->createMock(UserDataInterface::class);
      $user_data->expects($this->once())
        ->method('delete')
        ->with('default_admin', $uid);
      $container->set('user.data', $user_data);
      \Drupal::setContainer($container);

      $settings = Settings::getInstance();
      self::assertSame($uid === 1, $settings->getDefault('enable_dark_mode'));
      // With no explicit account, settings must be cleared for the current
      // user, even after the current_user service has been replaced.
      $settings->clear();
    }
  }

  /**
   * Data provider for testCurrentDependencies().
   */
  public static function containerReplacementProvider(): array {
    return [
      'same container' => [FALSE],
      'new container' => [TRUE],
    ];
  }

  /**
   * Tests that the accessor does not retain resolved settings.
   */
  public function testReleasesSettings(): void {
    $container = new ContainerBuilder();
    $container->set('class_resolver', new ClassResolver($container));
    $container->set('current_user', new UserSession());
    $container->set('config.factory', $this->getConfigFactoryStub());
    $container->set(ThemeSettingsProvider::class, $this->createStub(ThemeSettingsProvider::class));
    \Drupal::setContainer($container);

    $settings = Settings::getInstance();
    $reference = \WeakReference::create($settings);
    unset($settings);

    self::assertNull($reference->get());
  }

}
