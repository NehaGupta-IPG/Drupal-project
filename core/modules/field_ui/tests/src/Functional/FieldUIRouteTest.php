<?php

declare(strict_types=1);

namespace Drupal\Tests\field_ui\Functional;

use Drupal\Core\Entity\Entity\EntityFormMode;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the functionality of the Field UI route subscriber.
 */
#[Group('field_ui')]
#[RunTestsInSeparateProcesses]
class FieldUIRouteTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'entity_test', 'field_ui'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('local_tasks_block');
  }

  /**
   * Ensures that entity types with bundles do not break following entity types.
   */
  public function testFieldUIRoutes(): void {
    $route = \Drupal::service('router.route_provider')->getRouteByName('entity.entity_test.field_ui_fields');
    $is_admin = \Drupal::service('router.admin_context')->isAdminRoute($route);
    // Asserts that admin routes are correctly marked as such.
    $this->assertTrue($is_admin, 'Admin route correctly marked for "Manage fields" page.');

    $this->drupalLogin($this->drupalCreateUser([
      'administer account settings',
      'administer entity_test_no_id fields',
      'administer user fields',
      'administer user form display',
      'administer user display',
    ]));
    $this->drupalGet('entity_test_no_id/structure/entity_test/fields');
    $this->assertSession()->pageTextContains('No fields are present yet.');

    $this->drupalGet('admin/config/people/accounts/fields');
    $this->assertSession()->titleEquals('Manage fields | Drupal');
    $this->assertLocalTasks();

    // Test manage display tabs and titles.
    $this->drupalGet('admin/config/people/accounts/display/compact');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet('admin/config/people/accounts/display');
    $this->assertSession()->titleEquals('Manage display | Drupal');
    $this->assertLocalTasks();

    $xpath = "//tr[@id='display-mode-user-user-compact']//a[contains(., 'Enable')]";
    $this->assertSession()->elementExists('xpath', $xpath);
    $this->getSession()->getPage()->find('xpath', $xpath)->click();
    $this->drupalGet('admin/config/people/accounts/display/compact');
    $this->assertSession()->titleEquals('Manage display | Drupal');
    $this->assertLocalTasks();

    // Test manage form display tabs and titles.
    $this->drupalGet('admin/config/people/accounts/form-display/register');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet('admin/config/people/accounts/form-display');
    $this->assertSession()->titleEquals('Manage form display | Drupal');
    $this->assertLocalTasks();

    $edit = ['display_modes_custom[register]' => TRUE];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet('admin/config/people/accounts/form-display/register');
    $this->assertSession()->titleEquals('Manage form display | Drupal');
    $this->assertLocalTasks();
    // Test that default secondary tab is in first position.
    $this->assertSession()->elementsCount('xpath', "//ul/li[1]/a[contains(text(), 'Default')]", 1);

    // Create new view mode and verify it's available on the Manage Display
    // screen after enabling it.
    EntityViewMode::create([
      'id' => 'user.test',
      'label' => 'Test',
      'targetEntityType' => 'user',
    ])->save();
    $this->container->get('router.builder')->rebuildIfNeeded();

    $this->drupalGet('admin/config/people/accounts/display');
    $xpath = "//tr[@id='display-mode-user-user-test']//a[contains(., 'Enable')]";
    $this->assertSession()->elementExists('xpath', $xpath);
    $this->getSession()->getPage()->find('xpath', $xpath)->click();
    $this->assertSession()->linkExists('Test');

    // Create new form mode and verify it's available on the Manage Form
    // Display screen after enabling it.
    EntityFormMode::create([
      'id' => 'user.test',
      'label' => 'Test',
      'targetEntityType' => 'user',
    ])->save();
    $this->container->get('router.builder')->rebuildIfNeeded();

    $edit = ['display_modes_custom[test]' => TRUE];
    $this->drupalGet('admin/config/people/accounts/form-display');
    $this->submitForm($edit, 'Save');
    $this->assertSession()->linkExists('Test');
  }

  /**
   * Tests that view mode routes have the correct defaults.
   *
   * The view_mode_name must NOT be in the defaults (to keep it a required
   * path parameter), preventing the route from matching the overview URL
   * /display without a view mode segment. The active_tab default ensures
   * consistent tab UI across all view modes.
   */
  public function testDisplayViewModeRouteDefaults(): void {
    /** @var \Symfony\Component\Routing\Route $view_mode_route */
    $view_mode_route = \Drupal::service('router.route_provider')->getRouteByName('entity.entity_view_display.user.view_mode');
    $this->assertNotNull($view_mode_route, 'The view mode route exists.');
    $this->assertNull($view_mode_route->getDefault('view_mode_name'), 'view_mode_name must not be a default (required path param).');
    $this->assertSame('default', $view_mode_route->getDefault('active_tab'), 'active_tab default is set to "default".');

    /** @var \Symfony\Component\Routing\Route $default_route */
    $default_route = \Drupal::service('router.route_provider')->getRouteByName('entity.entity_view_display.user.default');
    $this->assertNotNull($default_route, 'The default display route exists.');
    $this->assertSame('default', $default_route->getDefault('view_mode_name'), 'Default route has view_mode_name set to "default".');

    $this->drupalLogin($this->drupalCreateUser([
      'administer user display',
      'access administration pages',
    ]));

    $this->drupalGet('admin/config/people/accounts/display/default');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->titleEquals('Manage display | Drupal');
  }

  /**
   * Tests that form display routes do not get the view mode defaults.
   */
  public function testFormDisplayModeRouteDefaults(): void {
    /** @var \Symfony\Component\Routing\Route $form_mode_route */
    $form_mode_route = \Drupal::service('router.route_provider')->getRouteByName('entity.entity_form_display.user.form_mode');
    $this->assertNotNull($form_mode_route, 'The form mode route exists.');
    $this->assertNull($form_mode_route->getDefault('form_mode_name'), 'form_mode_name default is not set on form_mode route.');
    $this->assertNull($form_mode_route->getDefault('active_tab'), 'active_tab default is not set on form_mode route.');
  }

  /**
   * Asserts that local tasks exists.
   *
   * @internal
   */
  public function assertLocalTasks(): void {
    $this->assertSession()->linkExists('Settings');
    $this->assertSession()->linkExists('Manage fields');
    $this->assertSession()->linkExists('Manage display');
    $this->assertSession()->linkExists('Manage form display');
  }

}
