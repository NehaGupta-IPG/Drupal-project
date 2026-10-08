<?php

declare(strict_types=1);

namespace Drupal\Tests\content_moderation\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Test the workflow type plugin in the content_moderation module.
 */
#[Group('content_moderation')]
#[RunTestsInSeparateProcesses]
class ContentModerationWorkflowTypeTest extends BrowserTestBase {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_moderation',
    'node',
    'entity_test',
    'taxonomy',
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
    $admin = $this->drupalCreateUser([
      'administer workflows',
    ]);
    $this->drupalLogin($admin);
  }

  /**
   * Tests creating a new workflow using the content moderation plugin.
   */
  public function testNewWorkflow(): void {
    $types[] = $this->createContentType();
    $types[] = $this->createContentType();
    $types[] = $this->createContentType();

    $entity_bundle_info = \Drupal::service('entity_type.bundle.info');

    $this->drupalGet('admin/config/workflow/workflows/add');
    $this->submitForm([
      'label' => 'Test',
      'id' => 'test',
      'workflow_type' => 'content_moderation',
    ], 'Save');

    $session = $this->assertSession();
    // Make sure the test workflow includes the default states and transitions.
    $session->pageTextContains('Draft');
    $session->pageTextContains('Published');
    $session->pageTextContains('Create New Draft');
    $session->pageTextContains('Publish');

    $session->linkByHrefNotExists('/admin/config/workflow/workflows/manage/test/state/draft/delete');
    $session->linkByHrefNotExists('/admin/config/workflow/workflows/manage/test/state/published/delete');

    // Ensure after a workflow is created, the bundle information can be
    // refreshed.
    $entity_bundle_info->clearCachedBundles();
    $this->assertNotEmpty($entity_bundle_info->getAllBundleInfo());

    $this->clickLink('Add a new state');
    $this->submitForm([
      'label' => 'Test State',
      'id' => 'test_state',
      'type_settings[published]' => TRUE,
      'type_settings[default_revision]' => FALSE,
    ], 'Save');
    $session->pageTextContains('Created Test State state.');
    $session->linkByHrefExists('/admin/config/workflow/workflows/manage/test/state/test_state/delete');

    // Check there is a link to delete a default transition.
    $session->linkByHrefExists('/admin/config/workflow/workflows/manage/test/transition/publish/delete');
    // Delete the transition.
    $this->drupalGet('/admin/config/workflow/workflows/manage/test/transition/publish/delete');
    $this->submitForm([], 'Delete');
    // The link to delete the transition should now be gone.
    $session->linkByHrefNotExists('/admin/config/workflow/workflows/manage/test/transition/publish/delete');

    // Ensure that the published settings cannot be changed.
    $this->drupalGet('admin/config/workflow/workflows/manage/test/state/published');
    $session->fieldDisabled('type_settings[published]');
    $session->fieldDisabled('type_settings[default_revision]');

    // Ensure that the draft settings cannot be changed.
    $this->drupalGet('admin/config/workflow/workflows/manage/test/state/draft');
    $session->fieldDisabled('type_settings[published]');
    $session->fieldDisabled('type_settings[default_revision]');

    $this->drupalGet('admin/config/workflow/workflows/manage/test/type/node');
    $session->pageTextContains('Select the content types for the Test workflow');
    foreach ($types as $type) {
      $session->pageTextContains($type->label());
      $session->elementContains('css', sprintf('.form-item-bundles-%s label', $type->id()), sprintf('Update %s', $type->label()));
    }

    // Ensure warning message are displayed for unsupported features.
    $this->drupalGet('admin/config/workflow/workflows/manage/test/type/entity_test_rev');
    $this->assertSession()->pageTextContains('Test entity - revisions entities do not support publishing statuses. For example, even after transitioning from a published workflow state to an unpublished workflow state they will still be visible to site visitors.');
  }

  /**
   * Tests the message shown when there are no bundles to select.
   */
  public function testNoBundlesMessage(): void {
    $this->drupalGet('admin/config/workflow/workflows/add');
    $this->submitForm([
      'label' => 'Test',
      'id' => 'test',
      'workflow_type' => 'content_moderation',
    ], 'Save');

    $session = $this->assertSession();

    // A user who cannot create content types sees no link to the add form.
    $this->drupalGet('admin/config/workflow/workflows/manage/test/type/node');
    $session->pageTextContains('No content types available. Someone with permission to create content types will need to do so before you can use this option.');
    $session->linkNotExists('Add content type');

    // A user who can create content types is linked to the add form. The node
    // type has no 'add-form' link template, so this uses a dedicated route.
    $this->drupalLogin($this->drupalCreateUser([
      'administer workflows',
      'administer content types',
      'administer taxonomy',
    ]));
    $this->drupalGet('admin/config/workflow/workflows/manage/test/type/node');
    $session->pageTextContains('No content types available. Add content type.');
    $session->linkByHrefExists('/admin/structure/types/add');

    // Bundle entity types with an 'add-form' link template use it.
    $this->drupalGet('admin/config/workflow/workflows/manage/test/type/taxonomy_term');
    $session->pageTextContains('No vocabularies available. Add vocabulary.');
    $session->linkByHrefExists('/admin/structure/taxonomy/add');

    // Entity types without a bundle entity type fall back to the entity type
    // label and never link anywhere.
    \Drupal::state()->set('entity_test_rev.bundles', []);
    \Drupal::service('entity_type.bundle.info')->clearCachedBundles();
    $this->drupalGet('admin/config/workflow/workflows/manage/test/type/entity_test_rev');
    $session->pageTextContains('No test entity - revisions available.');
    $session->elementNotExists('css', '#edit-bundles');

    // When every bundle is moderated by another workflow, the message says so
    // instead of claiming there are none.
    $this->createContentType(['type' => 'article']);
    $workflow = $this->createEditorialWorkflow();
    $this->addEntityTypeAndBundleToWorkflow($workflow, 'node', 'article');
    $this->drupalGet('admin/config/workflow/workflows/manage/test/type/node');
    $session->pageTextContains('All content types are already moderated by other workflows. Add content type.');
    $session->pageTextNotContains('No content types available.');
  }

}
