<?php

declare(strict_types=1);

namespace Drupal\Tests\search\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the search page machine name field behavior.
 *
 * @group search
 */
class SearchPageMachineNameTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'search'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that machine name field is not automatically updated with label.
   *
   * This test verifies a bug where the machine name field is not automatically
   * updated when the label field changes. The machine name should be automatically
   * generated from the label, but currently it is not.
   *
   * @see https://www.drupal.org/project/drupal/issues/3520941#comment-16084573
   */
  public function testMachineNameNotUpdated(): void {
    // Log in as admin.
    $this->drupalLogin($this->drupalCreateUser([
      'search content',
      'administer search',
      'use advanced search',
      'access user profiles',
    ]));

    // Visit the search pages configuration page.
    $this->drupalGet('admin/config/search/pages');

    // Select "Content" from the search page type dropdown.
    $this->getSession()->getPage()->selectFieldOption('search_type', 'node_search');

    // Click "Add Search Page" button.
    $this->getSession()->getPage()->pressButton('edit-add-search-submit');

    // Fill in the label field.
    $this->getSession()->getPage()->fillField('Label', 'Test page');

    // Wait for any potential JavaScript updates to complete.
    $this->getSession()->wait(1000);

    // Get the machine name field value.
    $machine_name = $this->getSession()->getPage()->findField('Machine-readable name')->getValue();

    // Assert that the machine name is not automatically updated.
    // This is currently a bug - the machine name should be 'test_page' but it's not.
    $this->assertNotEquals('test_page', $machine_name, 'Machine name should be automatically updated to "test_page" when label is "Test page", but it is not. This is a bug.');
  }

}
