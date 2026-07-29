<?php

declare(strict_types=1);

namespace Drupal\Tests\field_ui\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\field_ui\Traits\FieldUiTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Manage Display page of a fieldable entity type.
 */
#[Group('field_ui')]
#[RunTestsInSeparateProcesses]
class ManageFieldsTest extends BrowserTestBase {

  use FieldUiTestTrait;
  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field_test',
    'field_ui',
    'field_ui_test',
    'node',
    'text',
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
    $this->config('system.logging')
      ->set('error_level', ERROR_REPORTING_DISPLAY_ALL)
      ->save();
  }

  /**
   * Tests multiple users adding a field with the same name.
   */
  public function testAddFieldWithMultipleUsers(): void {
    $page = $this->getSession()->getPage();
    // Create two users.
    $user1 = $this->drupalCreateUser(['administer node fields']);
    $user2 = $this->drupalCreateUser(['administer node fields']);

    $node_type = $this->drupalCreateContentType();
    $bundle_path = '/admin/structure/types/manage/' . $node_type->id();

    // Start adding a field as user 1, stop prior to saving, but keep the URL.
    $this->drupalLogin($user1);
    $this->drupalGet($bundle_path . '/fields/add-field');
    $this->clickLink('Test field');
    $this->submitForm([], 'Continue');
    $edit = [
      'label' => 'Test field',
      'field_name' => 'test_field',
    ];
    $this->submitForm($edit, 'Continue');
    // Make changes to the storage form.
    $edit = ['field_storage[subform][cardinality_number]' => 5];
    $storage_form_url = $this->getUrl();
    $this->submitForm($edit, 'Update settings');
    $this->drupalLogout();

    // Actually add a field as user 2.
    $this->drupalLogin($user2);
    $this->drupalGet($bundle_path . '/fields/add-field');
    $this->clickLink('Test field');
    $this->submitForm([], 'Continue');
    $edit = [
      'label' => 'Test field',
      'field_name' => 'test_field',
    ];
    $this->submitForm($edit, 'Continue');
    $allowed_no_of_values = $page->findField('field_storage[subform][cardinality_number]')->getValue();
    // Assert that the changes made by any user do not affect other users until
    // the field is saved.
    $this->assertEquals(1, $allowed_no_of_values);
    $this->submitForm(['field_storage[subform][cardinality_number]' => 2], 'Update settings');
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextContains("Saved Test field configuration.");
    $this->drupalLogout();

    // Continue adding a field as user 1, using the URL saved previously.
    $this->drupalLogin($user1);
    $this->drupalGet($storage_form_url);
    // Assert that the user can go on with configuring a field with a machine
    // that is already taken.
    $this->assertSession()->pageTextNotContains('error');
    $this->submitForm([], 'Save');
    // An error is thrown only after the final 'Save'.
    $this->assertSession()->statusMessageContains("An error occurred while saving the field: 'field_storage_config' entity with ID 'node.field_test_field' already exists.");
  }

}
