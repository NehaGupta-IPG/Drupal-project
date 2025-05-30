<?php

namespace Drupal\FunctionalJavascriptTests;

/**
 * Tests the ListFilter functionality.
 *
 * @group javascript
 * @coversClass \Drupal\Core\Render\Element\ListFilter
 */
class ListFilterTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['list_filter_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  public function setUp(): void {
    parent::setUp();
    $admin = $this->createUser([], $this->randomString(), TRUE);
    $this->drupalLogin($admin);
  }

  /**
   * Tests the ListFilter with auto_activate set to FALSE.
   */
  public function testAutoActivateFalse() {
    $this->drupalGet('/list-filter/auto_activate_false');
    $this->getSession()
      ->getPage()
      ->find('css', '.filter-input')
      ->setValue('patriarch');
    $this->waitSearchFiltered();
    $this->assertEquals(
      $this->countVisibleRows(),
      $this->countTotalRows(),
      'All rows should be visible when auto_activate is set to FALSE.'
    );
  }

  /**
   * Tests the ListFilter with an overridden library.
   */
  public function testListFilterOverride() {
    $this->drupalGet('/list-filter/override_library');
    $page = $this->getSession()->getPage();
    $filterInput = $page->find('css', '.filter-input');
    $filterInput->setValue('patriarch');
    $this->waitSearchFiltered();
    $filterInput->blur();
    $this->assertSession()->waitForElementVisible('css', '.js-filter-count');
    $elementVisible = array_filter($page->findAll('css', '.filter-container tbody tr'), function ($element) {
      return $element->isVisible();
    });
    $this->assertSession()->pageTextContains('Results found: ' . count($elementVisible));
  }

  /**
   * Tests the minimum filter length functionality.
   */
  public function testMinFilterLength() {
    $this->drupalGet('/list-filter/minimum_filter_length');
    $page = $this->getSession()->getPage();
    $totalRows = count($page->findAll('css', '.filter-container tbody tr'));
    $filterInput = $page->find('css', '.filter-input');
    $filterInput->setValue('patr');
    $this->waitSearchFiltered();
    $totalVisibleRows = $this->countVisibleRows();
    $this->assertEquals($totalRows, $totalVisibleRows, 'All rows are visible before reaching the minimum filter length.');
    $filterInput->setValue('patria');
    $this->waitSearchFiltered();
    $totalVisibleRows = $this->countVisibleRows();
    $this->assertLessThan($totalRows, $totalVisibleRows, 'Rows are visible after reaching the minimum filter length.');;
  }

  /**
   * Tests the search start of words functionality.
   */
  public function testSearchStartOfWords() {
    $this->drupalGet('/list-filter/search_start_of_words');
    $page = $this->getSession()->getPage();
    $filterInput = $page->find('css', '.filter-input');
    $filterInput->setValue('rea');
    $this->waitSearchFiltered();
    $trs = $page->findAll('css', '.filter-container tbody tr');
    $this->assertTrue($trs[3]->isVisible(), 'The Matrix move should be visible when searching for "rea".');
    $this->assertFalse($trs[0]->isVisible(), 'Inception should not be visible when searching for "rea".');
  }

  /**
   * Helper function to wait for the search to be filtered.
   */
  protected function waitSearchFiltered($timeout = 300000): void {
    usleep($timeout);
  }

  /**
   * Counts the number of visible rows in the filter container.
   *
   * @param string $selector
   *   The CSS selector for the rows to count.
   *
   * @return int
   *   The number of visible rows.
   */
  protected function countVisibleRows(string $selector = '.filter-container tbody tr'): int {
    return count(array_filter($this->getTableRows(), function ($element) {
      return $element->isVisible();
    }));
  }

  /**
   * Counts the total number of rows in the filter container.
   *
   * @return int
   *   The total number of rows.
   */
  protected function countTotalRows(): int {
    return count($this->getTableRows());
  }

  /**
   * Gets all the rows in the filter container.
   *
   * @param string $selector
   *   The CSS selector for the rows to retrieve.
   *
   * @return array
   *   An array of row elements.
   */
  protected function getTableRows($selector = '.filter-container tbody tr'): array {
    return $this->getSession()->getPage()->findAll('css', $selector);
  }

}