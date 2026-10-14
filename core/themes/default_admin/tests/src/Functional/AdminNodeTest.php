<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Default Admin theme for nodes.
 */
#[Group('default_admin')]
#[RunTestsInSeparateProcesses]
class AdminNodeTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'path',
    'default_admin_form_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'default_admin';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->container->get('config.factory')
      ->getEditable('system.theme')
      ->set('admin', 'default_admin')
      ->save();

    $this->drupalCreateContentType(['type' => 'page', 'name' => 'Basic page']);
    $this->drupalLogin($this->drupalCreateUser([
      'access administration pages',
      'view the administration theme',
      'create page content',
      'edit any page content',
      'administer nodes',
      'create url aliases',
    ]));
  }

  /**
   * Tests the published checkbox on an entity form that sets #tree.
   *
   * The theme copies the footer into its sidebar, and a group is identified by
   * its #parents, so without pinning them the copy registers under a different
   * name on a treed form. The checkbox would then render nowhere while still
   * being processed on submit, which unpublishes the entity on save.
   */
  public function testPublishedCheckboxOnTreedForm(): void {
    $node = $this->drupalCreateNode(['type' => 'page', 'status' => TRUE]);

    $this->drupalGet($node->toUrl('edit-form'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('status[value]');
    $this->assertSession()->checkboxChecked('status[value]');

    $this->submitForm([], 'Save');

    $node = $this->container->get('entity_type.manager')
      ->getStorage('node')
      ->loadUnchanged($node->id());
    $this->assertTrue($node->isPublished());
  }

  /**
   * Tests that error messages of sidebar fields point to the sidebar.
   *
   * Without inline form errors, the messages are only shown at the top of the
   * page. The message of a field in the sidebar gets a button that opens the
   * sidebar, the message of a field in the main region does not.
   */
  public function testSidebarErrorMessages(): void {
    $this->drupalGet('node/add/page');
    $this->submitForm([
      'title[0][value]' => '',
      'path[0][alias]' => 'no-slash',
    ], 'Save');

    $this->assertSession()->pageTextContains('Title field is required.');
    $this->assertSession()->pageTextContains('The alias path has to start with a slash.');
    $buttons = $this->getSession()->getPage()->findAll('css', '[data-drupal-messages] [data-admin-sidebar-open]');
    $this->assertCount(1, $buttons);
    $this->assertSame('Open the sidebar', $buttons[0]->getText());
    $this->assertStringContainsString('The alias path has to start with a slash.', $buttons[0]->getParent()->getText());
  }

  /**
   * Tests that inline form errors leave the error messages unchanged.
   */
  public function testSidebarErrorMessagesWithInlineFormErrors(): void {
    $this->container->get('module_installer')->install(['inline_form_errors']);

    $this->drupalGet('node/add/page');
    $this->submitForm([
      'title[0][value]' => 'Title',
      'path[0][alias]' => 'no-slash',
    ], 'Save');

    $this->assertSession()->pageTextContains('The alias path has to start with a slash.');
    $this->assertSession()->elementNotExists('css', '[data-admin-sidebar-open]');
  }

}
