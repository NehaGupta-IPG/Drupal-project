<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Kernel;

use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that SystemController's menu links are discovered from #[MenuLink].
 */
#[Group('system')]
#[RunTestsInSeparateProcesses]
class SystemMenuLinkAttributeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * The menu link plugin manager.
   */
  protected MenuLinkManagerInterface $menuLinkManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->menuLinkManager = $this->container->get('plugin.manager.menu.link');
  }

  /**
   * Tests links derived from a single sibling route (auto ID).
   */
  public function testSingleRouteLinks(): void {
    $definitions = $this->menuLinkManager->getDefinitions();

    // system.admin_config is declared on SystemController::overview(), which
    // has a single route, so the plugin ID and route are both derived from it.
    $this->assertArrayHasKey('system.admin_config', $definitions);
    $config = $definitions['system.admin_config'];
    $this->assertSame('system.admin_config', $config['route_name']);
    $this->assertSame('system.admin', $config['parent']);
    $this->assertSame('Configuration', (string) $config['title']);
    $this->assertSame('Administer settings.', (string) $config['description']);
    // The provider is derived from the controller's namespace.
    $this->assertSame('system', $config['provider']);

    // system.themes_page is likewise a single-route link.
    $this->assertArrayHasKey('system.themes_page', $definitions);
    $this->assertSame('system.themes_page', $definitions['system.themes_page']['route_name']);
    $this->assertSame('system.admin', $definitions['system.themes_page']['parent']);
    $this->assertSame(-6, $definitions['system.themes_page']['weight']);
  }

  /**
   * Tests links on the multi-route admin block page (explicit route).
   */
  public function testMultiRouteLinks(): void {
    $definitions = $this->menuLinkManager->getDefinitions();

    // The root link sets the menu and carries no parent.
    $this->assertArrayHasKey('system.admin', $definitions);
    $this->assertSame('admin', $definitions['system.admin']['menu_name']);
    $this->assertSame(9, $definitions['system.admin']['weight']);
    $this->assertSame('', $definitions['system.admin']['parent']);

    // A link selected from a method declaring many routes resolves to the
    // route it names, and the route name is reused as the plugin ID.
    $this->assertArrayHasKey('system.admin_config_system', $definitions);
    $system = $definitions['system.admin_config_system'];
    $this->assertSame('system.admin_config_system', $system['route_name']);
    $this->assertSame('system.admin_config', $system['parent']);
    $this->assertSame(-20, $system['weight']);
    $this->assertSame('Configure basic site settings, actions, and cron.', (string) $system['description']);
  }

  /**
   * Tests that YAML links parented on attribute links still resolve.
   */
  public function testYamlLinksReferenceAttributeParents(): void {
    $definitions = $this->menuLinkManager->getDefinitions();

    // system.cron_settings remains in YAML but is parented on
    // system.admin_config_system, which is now declared via #[MenuLink].
    $this->assertArrayHasKey('system.cron_settings', $definitions);
    $this->assertSame('system.admin_config_system', $definitions['system.cron_settings']['parent']);
    $this->assertArrayHasKey('system.admin_config_system', $definitions);
  }

}
