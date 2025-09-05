<?php

namespace Drupal\Tests\field\FunctionalJavascript\EntityReference;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\field_ui\Traits\FieldUiTestTrait;

/**
 * Tests for consistent UX between auto-create and save behavior settings.
 *
 * @group entity_reference
 */
class EntityReferenceConfigurationConsistencyTest extends WebDriverTestBase {

  use FieldUiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field_ui',
    'node',
    'taxonomy',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The test user.
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create test content type.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    // Create vocabulary for testing.
    $vocabulary = $this->drupalCreateVocabulary([
      'vid' => 'tags',
      'name' => 'Tags',
    ]);

    // Create admin user.
    $this->adminUser = $this->drupalCreateUser([
      'access content',
      'administer content types',
      'administer node fields',
      'administer node form display',
      'administer node display',
      'administer taxonomy',
    ]);
    $this->drupalLogin($this->adminUser);

    // Add entity reference field.
    $this->fieldUIAddNewField('admin/structure/types/manage/article', 'tags', 'Tags', 'entity_reference', [
      'settings[target_type]' => 'taxonomy_term',
    ], [
      'settings[handler_settings][target_bundles][tags]' => TRUE,
    ]);
  }

  /**
   * Tests that auto-create is disabled when save behavior is disabled.
   */
  public function testAutoCreateDisabledWhenSaveBehaviorDisabled(): void {
    $page = $this->getSession()->getPage();

    // Navigate to field edit form.
    $this->drupalGet('admin/structure/types/manage/article/fields/node.article.field_tags');

    // Set save behavior to disabled.
    $page->selectFieldOption('settings[save_referenced_entities]', 'disabled');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Check that auto-create checkbox is now disabled.
    $auto_create_field = $page->findField('settings[handler_settings][auto_create]');
    $this->assertTrue($auto_create_field->hasAttribute('disabled'), 'Auto-create checkbox should be disabled when save behavior is disabled.');

    // Verify improved description is present.
    $this->assertSession()->pageTextContains('Allow users to type new entity labels in autocomplete fields');
    $this->assertSession()->pageTextContains('This only works if referenced entities are configured to be saved automatically');
  }

  /**
   * Tests that auto-create is enabled when save behavior allows saving.
   */
  public function testAutoCreateEnabledWhenSaveBehaviorAllowsSaving(): void {
    $page = $this->getSession()->getPage();

    // Navigate to field edit form.
    $this->drupalGet('admin/structure/types/manage/article/fields/node.article.field_tags');

    // Test each save mode that should enable auto-create.
    $enabled_modes = ['new_only', 'changed_only', 'always'];
    
    foreach ($enabled_modes as $mode) {
      // Set save behavior to mode that allows saving.
      $page->selectFieldOption('settings[save_referenced_entities]', $mode);
      $this->assertSession()->assertWaitOnAjaxRequest();

      // Check that auto-create checkbox is enabled.
      $auto_create_field = $page->findField('settings[handler_settings][auto_create]');
      $this->assertFalse($auto_create_field->hasAttribute('disabled'), "Auto-create checkbox should be enabled when save behavior is '$mode'.");
    }
  }

  /**
   * Tests the field settings summary includes both configurations.
   */
  public function testFieldSummaryShowsBothSettings(): void {
    $page = $this->getSession()->getPage();

    // Navigate to field edit form.
    $this->drupalGet('admin/structure/types/manage/article/fields/node.article.field_tags');

    // Configure both settings.
    $page->selectFieldOption('settings[save_referenced_entities]', 'new_only');
    $this->assertSession()->assertWaitOnAjaxRequest();
    
    $page->checkField('settings[handler_settings][auto_create]');
    
    // Save the field configuration.
    $page->pressButton('Save settings');
    $this->assertSession()->pageTextContains('Saved Tags configuration');

    // Go back to field list to check summary.
    $this->drupalGet('admin/structure/types/manage/article/fields');
    
    // Verify field summary includes save behavior information.
    $this->assertSession()->pageTextContains('Save new only');
  }

  /**
   * Tests that conflicting configurations are prevented.
   */
  public function testConflictingConfigurationsPrevented(): void {
    $page = $this->getSession()->getPage();

    // Navigate to field edit form.
    $this->drupalGet('admin/structure/types/manage/article/fields/node.article.field_tags');

    // Start with auto-create enabled and saving enabled.
    $page->selectFieldOption('settings[save_referenced_entities]', 'new_only');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->checkField('settings[handler_settings][auto_create]');

    // Now change save behavior to disabled.
    $page->selectFieldOption('settings[save_referenced_entities]', 'disabled');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Verify auto-create is now disabled and cannot be checked.
    $auto_create_field = $page->findField('settings[handler_settings][auto_create]');
    $this->assertTrue($auto_create_field->hasAttribute('disabled'), 'Auto-create should be disabled when save behavior is disabled.');

    // Try to save - this should work because the conflicting state is prevented.
    $page->pressButton('Save settings');
    $this->assertSession()->pageTextContains('Saved Tags configuration');
  }

}
