<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\default_admin\Traits\StickyActionButtonsTrait;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the content form layout and the sticky form action buttons.
 *
 * @see \Drupal\default_admin\Hook\FormHooks::stickyActionButtonsAndSidebar()
 * @see \Drupal\default_admin\Hook\FormHooks::formAfterBuild()
 * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessHtml()
 * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessPage()
 * @see \Drupal\default_admin\Hook\PreprocessHooks::preprocessTopBar()
 * @see \Drupal\default_admin\Hook\ThemeSuggestionHooks::themeSuggestionsPageAlter()
 */
#[Group('default_admin')]
#[RunTestsInSeparateProcesses]
class NodeFormTest extends BrowserTestBase {

  use StickyActionButtonsTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'node',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->assertTrue(\Drupal::service('theme_installer')->install(['default_admin']));
    $this->container->get('config.factory')
      ->getEditable('system.theme')
      ->set('default', 'default_admin')
      ->set('admin', 'default_admin')
      ->save();

    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Basic page',
    ]);

    $account = $this->drupalCreateUser([
      'create page content',
      'edit own page content',
      'administer nodes',
      'access administration pages',
      'access content overview',
      'administer site configuration',
    ]);
    $this->drupalLogin($account);
  }

  /**
   * Tests the layout and sidebar markup added to content forms.
   */
  public function testNodeEditFormLayout(): void {
    $this->drupalGet('node/add/page');
    $this->assertSession()->statusCodeEquals(200);
    $assert_session = $this->assertSession();

    // The body gets the edit-form class because node.add is a content form
    // route. The most specific page template that exists for this route is
    // page--node.html.twig, which wraps the page in its own container.
    $assert_session->elementExists('css', 'body.edit-form');
    $assert_session->elementExists('css', '.page-wrapper__node-edit-form .layout-node-form');

    // The form is rendered through node-edit-form.html.twig, which splits it
    // into a main and a secondary region, and it carries the class that
    // edit_form.js uses to identify the form.
    $assert_session->elementExists('css', 'form.node-edit-form .layout-node-form');
    $assert_session->elementExists('css', '.layout-node-form .layout-region--main');
    $assert_session->elementExists('css', '.layout-node-form #default_admin_sidebar.layout-region--secondary');

    // The advanced container is turned into the entity meta sidebar and is
    // printed inside the secondary region.
    $assert_session->elementExists('css', '#default_admin_sidebar .entity-meta');

    // The sidebar group and its close button, plus the overlay that stays in
    // the form itself.
    $assert_session->elementExists('css', '.admin-sidebar');
    $assert_session->elementExists('css', '.admin-sidebar .meta-sidebar__close');
    $assert_session->elementExists('css', '.meta-sidebar__overlay');

    // Even though sticky action buttons are disabled by default, content forms
    // still hand the sidebar trigger to the page header through
    // Helper::formActions().
    $assert_session->elementExists('css', 'header.region-sticky.sticky-form-actions--preprocessed .sticky-form-actions .meta-sidebar__trigger');
    $assert_session->elementAttributeContains('css', '.meta-sidebar__trigger', 'aria-controls', 'default_admin_sidebar');

    // The default value of the sticky action buttons setting is "never", so the
    // form must not be marked as having sticky actions.
    $assert_session->elementNotExists('css', 'form.has-sticky-form-actions');
    $assert_session->elementNotExists('css', '#sticky-edit-submit');

    // The edit form library is attached to content forms.
    $assert_session->responseContains('css/components/edit-form.css');
  }

  /**
   * Tests that a route which is not a content form route uses a plain page.
   */
  public function testContentFormOnNonNodeRouteUsesPlainPage(): void {
    $this->drupalGet('admin/content');
    $this->assertSession()->statusCodeEquals(200);
    $assert_session = $this->assertSession();

    // The content overview renders the regular page template.
    $assert_session->elementExists('css', 'main.page-content');

    // system.admin_content is not a content form route, so none of the content
    // form additions are made.
    $assert_session->elementNotExists('css', 'body.edit-form');
    $assert_session->elementNotExists('css', '.page-wrapper__node-edit-form');
    $assert_session->elementNotExists('css', '.admin-sidebar');
    $assert_session->elementNotExists('css', '.meta-sidebar__trigger');
  }

  /**
   * Tests sticky action buttons on every form.
   */
  public function testStickyActionsAlways(): void {
    $this->setStickyActionButtons('always');

    $this->drupalGet('node/add/page');
    $this->assertSession()->statusCodeEquals(200);
    $assert_session = $this->assertSession();

    // The form is marked as sticky and gets the skip link that moves focus to
    // the sticky bar.
    $assert_session->elementExists('css', 'form.node-edit-form.has-sticky-form-actions');
    $assert_session->elementExists('css', '[data-move-focus-to-sticky-bar]');

    // The duplicated actions are moved out of the form and printed in the
    // sticky page header region.
    $assert_session->elementExists('css', 'header.region-sticky.sticky-form-actions--preprocessed .sticky-form-actions #sticky-edit-submit');
    $assert_session->elementNotExists('css', 'form.node-edit-form #sticky-edit-submit');

    // Because the duplicate lives outside the form it has to reference the form
    // it submits.
    $form_element = $assert_session->elementExists('css', 'form.node-edit-form');
    $form_html_id = $form_element->getAttribute('id');
    $this->assertNotEmpty($form_html_id);
    $sticky_submit = $assert_session->elementExists('css', '#sticky-edit-submit');
    $this->assertSame($form_html_id, $sticky_submit->getAttribute('form'));

    // A form that is not a content form is sticky as well with this flag, but
    // it does not get the content form sidebar.
    $this->drupalGet('admin/config/system/site-information');
    $this->assertSession()->statusCodeEquals(200);
    $assert_session->elementExists('css', 'form.has-sticky-form-actions');
    $assert_session->elementExists('css', 'header.region-sticky .sticky-form-actions #sticky-edit-submit');
    $assert_session->elementNotExists('css', '.admin-sidebar');
    $assert_session->elementNotExists('css', '.meta-sidebar__trigger');
  }

  /**
   * Tests sticky action buttons restricted to content forms.
   */
  public function testStickyActionsContentFormsOnly(): void {
    $this->setStickyActionButtons('content_forms');

    $this->drupalGet('node/add/page');
    $this->assertSession()->statusCodeEquals(200);
    $assert_session = $this->assertSession();

    $assert_session->elementExists('css', 'form.node-edit-form.has-sticky-form-actions');
    $assert_session->elementExists('css', 'header.region-sticky .sticky-form-actions #sticky-edit-submit');
    $assert_session->elementExists('css', '[data-move-focus-to-sticky-bar]');

    // Forms which are not content forms are left untouched, so neither the
    // form nor the page header advertises sticky actions.
    $this->drupalGet('admin/config/system/site-information');
    $this->assertSession()->statusCodeEquals(200);
    $assert_session->elementExists('css', 'form#system-site-information-settings');
    $assert_session->elementNotExists('css', 'form.has-sticky-form-actions');
    $assert_session->elementNotExists('css', '#sticky-edit-submit');
    $assert_session->elementNotExists('css', '[data-move-focus-to-sticky-bar]');
    $assert_session->elementNotExists('css', 'header.sticky-form-actions--preprocessed');
  }

  /**
   * Tests that sticky action buttons move into the navigation top bar.
   */
  public function testStickyActionsWithNavigation(): void {
    \Drupal::service('module_installer')->install(['navigation']);
    $this->resetAll();
    $authenticated = Role::load(RoleInterface::AUTHENTICATED_ID);
    $this->assertInstanceOf(RoleInterface::class, $authenticated);
    $this->grantPermissions($authenticated, ['access navigation']);
    $this->setStickyActionButtons('always');

    $this->drupalGet('node/add/page');
    $this->assertSession()->statusCodeEquals(200);
    $assert_session = $this->assertSession();

    // With the Navigation module installed the body switches to the navigation
    // variant and the top bar is themed by the theme's own template.
    $assert_session->elementExists('css', 'body.admin--navigation');
    $assert_session->elementExists('css', '.top-bar.admin--navigation-top-bar .top-bar__actions');

    // The sticky actions are injected into the top bar actions area.
    $assert_session->elementExists('css', '.top-bar__actions .sticky-form-actions #sticky-edit-submit');
    $assert_session->elementExists('css', '.top-bar__actions .sticky-form-actions .meta-sidebar__trigger');

    // The page header keeps the marker class but must not render the actions a
    // second time.
    $assert_session->elementExists('css', 'header.admin--navigation-top-bar--offset.sticky-form-actions--preprocessed');
    $assert_session->elementNotExists('css', 'header.admin--navigation-top-bar--offset .sticky-form-actions');
    $assert_session->elementNotExists('css', 'header.region-sticky');
  }

}
