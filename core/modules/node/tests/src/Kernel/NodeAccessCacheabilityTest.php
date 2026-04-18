<?php

declare(strict_types=1);

namespace Drupal\Tests\node\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\EventSubscriber\EarlyRenderingControllerWrapperSubscriber;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Url;
use Drupal\Tests\system\Functional\Cache\AssertPageCacheContextsAndTagsTrait;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Tests the node access automatic cacheability bubbling logic.
 */
#[Group('node')]
#[Group('Cache')]
#[Group('cacheability_safeguards')]
#[RunTestsInSeparateProcesses]
class NodeAccessCacheabilityTest extends NodeAccessTestBase {

  use AssertPageCacheContextsAndTagsTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node_access_test',
    'node_access_test_auto_bubbling',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->setParameter('http.response.debug_cacheability_headers', TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('user', ['users_data']);
  }

  /**
   * Tests that the node grants cache context is auto-added, only when needed.
   *
   * Test with early rendering both enabled and disabled, because early
   * rendering provides a render context that will capture cache metadata from
   * a node_access query. Without the render context from early rendering, the
   * cache metadata is retrieved from the select object and merged to the
   * controller's build array cache metadata to bubble up.
   *
   * @see \Drupal\node\Hook\NodeDatabaseHooks::queryNodeAccessAlter()
   */
  #[TestWith([FALSE])]
  #[TestWith([TRUE])]
  public function testNodeAccessCacheabilitySafeguard(bool $disable_early_render): void {
    if ($disable_early_render) {
      $subscriber = \Drupal::service('early_rendering_controller_wrapper_subscriber');
      $this->assertInstanceOf(EarlyRenderingControllerWrapperSubscriber::class, $subscriber);
      \Drupal::service(EventDispatcherInterface::class)->removeSubscriber($subscriber);
    }

    for ($i = 0; $i < 4; $i++) {
      $this->createNode();
    }

    $anonymousUser = User::getAnonymousUser();
    $this->setCurrentUser($anonymousUser);
    $this->drupalGet(new Url('node_access_test_auto_bubbling')->toString());
    $this->assertCacheContext('user.node_grants:view');
    // The controller includes markup indicating whether it ran within a render
    // context. The render context is provided by the early rendering subscriber
    // when it is enabled, and there is no render context when early rendering
    // is disabled.
    if (!$disable_early_render) {
      $this->assertSession()->pageTextContains('Executed within a render context.');
    }
    else {
      $this->assertSession()->pageTextNotContains('Executed within a render context.');
    }

    // The user has the 'bypass node access' permission, which means the node
    // grants cache context is not added or bubbled to the response header.
    $bypassUser = $this->createUser(['bypass node access']);
    $this->setCurrentUser($bypassUser);
    $this->drupalGet(new Url('node_access_test_auto_bubbling')->toString());
    $this->assertNoCacheContext('user.node_grants:view');
    $this->setCurrentUser($anonymousUser);
    if (!$disable_early_render) {
      $this->assertSession()->pageTextContains('Executed within a render context.');
    }
    else {
      $this->assertSession()->pageTextNotContains('Executed within a render context.');
    }

    // Uninstall the module with the only hook_node_grants() implementation.
    \Drupal::service(ModuleInstallerInterface::class)->uninstall(['node_access_test']);

    // Because there are no node grants defined, the node grants cache context
    // is not added or bubbled to the response header.
    $this->setCurrentUser($anonymousUser);
    $this->drupalGet(new Url('node_access_test_auto_bubbling')->toString());
    $this->assertNoCacheContext('user.node_grants:view');
    if (!$disable_early_render) {
      $this->assertSession()->pageTextContains('Executed within a render context.');
    }
    else {
      $this->assertSession()->pageTextNotContains('Executed within a render context.');
    }
  }

}
