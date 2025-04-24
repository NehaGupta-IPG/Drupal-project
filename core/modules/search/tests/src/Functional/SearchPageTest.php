<?php

declare(strict_types=1);

namespace Drupal\Tests\search\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the search page creation and verification.
 *
 * @group search
 */
class SearchPageTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'search'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests creating a new search page and verifying it works.
   */
  public function testSearchPageCreation(): void {
    // Log in as admin.
    $this->drupalLogin($this->drupalCreateUser([
      'search content',
      'administer search',
      'use advanced search',
      'access user profiles',
    ]));

    // Visit the search pages configuration page.
    $this->drupalGet('admin/config/search/pages');
    $this->assertSession()->statusCodeEquals(200);

    // Select "Content" from the search page type dropdown.
    $page = $this->getSession()->getPage();
    $page->fillField('search_type', 'node_search');

    // Click "Add Search Page" button.
    $this->getSession()->getPage()->pressButton('edit-add-search-submit');
    $this->assertSession()->statusCodeEquals(200);

    // Fill in the form fields.
    $this->getSession()->getPage()->fillField('Label', 'Test page');
    $this->getSession()->getPage()->fillField('Machine-readable name', 'test_page');

    $this->getSession()->getPage()->fillField('Path', 'test-page');

    // Save the form.
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->statusCodeEquals(200);

    // Verify we are redirected back to the search pages list.
    $this->assertSession()->addressEquals('admin/config/search/pages');

    // Visit the new search page to verify it exists.
    $this->drupalGet('search/test-page');
    $this->assertSession()->statusCodeEquals(200);
  }

}
