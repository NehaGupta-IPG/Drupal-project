<?php

declare(strict_types=1);

namespace Drupal\Tests\workspaces_ui\Kernel;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Render\RenderContext;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\workspaces\Entity\Workspace;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the workspace list builder.
 */
#[Group('workspaces')]
#[RunTestsInSeparateProcesses]
class WorkspaceListBuilderTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'system',
    'workspaces',
    'workspaces_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('workspace');
    $this->installSchema('workspaces', ['workspace_association', 'workspace_association_revision']);

    // Create user 1 so the test users don't bypass access control.
    $this->createUser();
  }

  /**
   * Tests that the off-canvas action buttons are access checked.
   */
  public function testOffCanvasActionsAccess(): void {
    $stage = Workspace::create(['id' => 'stage', 'label' => 'Stage']);
    $stage->save();
    $dev = Workspace::create(['id' => 'dev', 'label' => 'Dev', 'parent' => 'stage']);
    $dev->save();

    // Only the off-canvas render path builds the action buttons.
    $this->container->get('request_stack')->getCurrentRequest()->query
      ->set(MainContentViewSubscriber::WRAPPER_FORMAT, 'drupal_dialog.off_canvas');

    // Create both accounts first; roles cannot be saved in a workspace.
    $viewer = $this->createUser(['view any workspace']);
    $admin = $this->createUser(['administer workspaces']);

    $this->setCurrentUser($viewer);

    $this->container->get('workspaces.manager')->setActiveWorkspace($dev);
    $build = $this->getListBuild();
    $this->assertAccessForbidden($build['active_workspace']['actions']['merge']['#access']);

    $this->container->get('workspaces.manager')->setActiveWorkspace($stage);
    $build = $this->getListBuild();
    $this->assertAccessForbidden($build['active_workspace']['actions']['publish']['#access']);

    $this->setCurrentUser($admin);

    $this->container->get('workspaces.manager')->setActiveWorkspace($dev);
    $build = $this->getListBuild();
    $this->assertTrue($build['active_workspace']['actions']['merge']['#access']->isAllowed());

    $this->container->get('workspaces.manager')->setActiveWorkspace($stage);
    $build = $this->getListBuild();
    $this->assertTrue($build['active_workspace']['actions']['publish']['#access']->isAllowed());
  }

  /**
   * Builds the workspace collection render array.
   *
   * @return array
   *   The render array.
   */
  protected function getListBuild(): array {
    // Child workspace rows render an indentation element inline.
    return $this->container->get('renderer')->executeInRenderContext(new RenderContext(), function () {
      return $this->container->get('entity_type.manager')->getListBuilder('workspace')->render();
    });
  }

  /**
   * Asserts that an access result is a non-allowed access result object.
   *
   * @param mixed $access
   *   The '#access' value to check.
   */
  protected function assertAccessForbidden(mixed $access): void {
    $this->assertInstanceOf(AccessResultInterface::class, $access);
    $this->assertFalse($access->isAllowed());
  }

}
