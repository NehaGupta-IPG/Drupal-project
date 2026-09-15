<?php

declare(strict_types=1);

namespace Drupal\Tests\navigation\Kernel;

use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\navigation\Plugin\Block\NavigationUserBlock;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the navigation user block current-user destinations.
 */
#[Group('navigation')]
#[RunTestsInSeparateProcesses]
class NavigationUserBlockTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'file',
    'layout_builder',
    'layout_discovery',
    'navigation',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['navigation']);
    $this->container->get('plugin.manager.menu.link')->rebuild();
  }

  /**
   * Tests rendered user links use the current account destinations.
   */
  public function testCurrentUserLinkDestinations(): void {
    $menu_link_manager = $this->container->get('plugin.manager.menu.link');
    $this->assertSame('user.page', $menu_link_manager->getDefinition('navigation.user_links.user.page')['route_name']);
    $this->assertSame('user.edit', $menu_link_manager->getDefinition('navigation.user_links.user.edit')['route_name']);

    $account = $this->createUser();
    $this->setCurrentUser($account);
    $this->assertCurrentUserDestinations($account);

    $other_account = $this->createUser();
    $this->setCurrentUser($other_account);
    $this->assertCurrentUserDestinations($other_account);
  }

  /**
   * Asserts the built user block URLs for the given account.
   *
   * @param \Drupal\user\UserInterface $account
   *   The current user account.
   */
  protected function assertCurrentUserDestinations(UserInterface $account): void {
    $build = NavigationUserBlock::buildLinks('User');
    $this->assertContains('user', $build['#cache']['contexts']);
    $this->assertContains('user:' . $account->id(), $build['#cache']['tags']);

    $wrapper = $build['#items']['navigation.user_links.user.wrapper'];
    $this->assertUrlDestination(
      $wrapper['url'],
      'entity.user.canonical',
      $account,
    );

    $below = $wrapper['below'];
    $this->assertUrlDestination(
      $below['navigation.user_links.user.page']['url'],
      'entity.user.canonical',
      $account,
    );
    $this->assertUrlDestination(
      $below['navigation.user_links.user.edit']['url'],
      'entity.user.edit_form',
      $account,
    );
    $this->assertSame(
      'user.logout',
      $below['navigation.user_links.user.logout']['url']->getRouteName(),
    );
  }

  /**
   * Asserts a menu URL uses the expected current-user destination.
   *
   * @param \Drupal\Core\Url $url
   *   The rendered menu URL.
   * @param string $route_name
   *   The expected route name.
   * @param \Drupal\user\UserInterface $account
   *   The current user account.
   */
  protected function assertUrlDestination(Url $url, string $route_name, UserInterface $account): void {
    $this->assertSame($route_name, $url->getRouteName());
    $this->assertSame(['user' => $account->id()], $url->getRouteParameters());
    $this->assertTrue($url->getOption('set_active_class'));
  }

}
