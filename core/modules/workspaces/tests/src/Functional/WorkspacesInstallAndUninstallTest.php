<?php

declare(strict_types=1);

namespace Drupal\Tests\workspaces\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\workspaces\Entity\Handler\DefaultWorkspaceHandler;

/**
 * Tests installing and uninstalling the Workspaces module.
 *
 * @group workspaces
 */
class WorkspacesInstallAndUninstallTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalLogin($this->drupalCreateUser(['administer modules']));
  }

  /**
   * Tests installing and uninstalling the Workspaces module.
   */
  public function testInstallAndUninstall(): void {
    // Install the modules separately to simulate enabling Workspaces on an
    // existing site.
    $this->container->get('module_installer')->install(['workspaces', 'workspaces_ui']);
    $this->resetAll();

    // Check that the workspace handler and revision metadata key are being set
    // when the module is installed.
    $entity_type = \Drupal::entityDefinitionUpdateManager()->getEntityType('node');
    $revision_metadata_keys = $entity_type->get('revision_metadata_keys');
    $this->assertSame('workspace', $revision_metadata_keys['workspace']);
    $handlers = $entity_type->get('handlers');
    $this->assertSame(DefaultWorkspaceHandler::class, $handlers['workspace']);

    $this->assertTrue(\Drupal::database()->schema()->fieldExists('node_revision', 'workspace'));

    $this->drupalGet('/admin/modules/uninstall');
    $session = $this->assertSession();
    $session->linkExists('Remove workspaces');
    $this->clickLink('Remove workspaces');
    $session->pageTextContains('Are you sure you want to delete all workspaces?');
    $this->drupalGet('/admin/modules/uninstall/entity/workspace');
    $this->submitForm([], 'Delete all workspaces');
    $this->drupalGet('admin/modules/uninstall');
    $this->submitForm(['uninstall[workspaces_ui]' => TRUE], 'Uninstall');
    $this->submitForm([], 'Uninstall');
    $this->submitForm(['uninstall[workspaces]' => TRUE], 'Uninstall');
    $this->submitForm([], 'Uninstall');
    $session->pageTextContains('The selected modules have been uninstalled.');
    $session->pageTextNotContains('Workspaces');

    $this->assertFalse(\Drupal::database()->schema()->fieldExists('node_revision', 'workspace'));

    // Verify that the handler and revision metadata key have been removed.
    $this->rebuildContainer();
    $entity_type = \Drupal::entityDefinitionUpdateManager()->getEntityType('node');
    $revision_metadata_keys = $entity_type->get('revision_metadata_keys');
    $this->assertArrayNotHasKey('workspace', $revision_metadata_keys);
    $handlers = $entity_type->get('handlers');
    $this->assertArrayNotHasKey('workspace', $handlers);
  }

}
