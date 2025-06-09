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
  public function testAutoActivateFalse(): void {
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
  public function testListFilterOverride(): void {
    $this->drupalGet('/list-filter/override_library');
    $page = $this->getSession()->getPage();
    $filterInput = $page->find('css', '.filter-input');
    $filterInput->setValue('patri');
    $this->waitSearchFiltered();
    $filterInput->blur();
    $this->assertSession()->waitForElementVisible('css', '.js-filter-count');
    $this->assertSession()->pageTextContains('Results found: ' . $this->countVisibleRows());
  }

  /**
   * Tests the minimum filter length functionality.
   */
  public function testMinFilterLength(): void {
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
    $this->assertLessThan($totalRows, $totalVisibleRows, 'Rows are visible after reaching the minimum filter length.');
  }

  /**
   * Tests the search start of words functionality.
   */
  public function testSearchStartOfWords(): void {
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
   * Tests multiple filter elements on the same page.
   */
  public function testMultipleFilterElementsInPage(): void {
    $this->drupalGet('/list-filter/multiple-filters-on-page');
    $page = $this->getSession()->getPage();

    // Filter Table One (drinks recipes) by "Mint".
    $filterOne = $page->find('css', '.filter-input-one');
    $filterOne->setValue('mint');
    $this->waitSearchFiltered();
    $visibleRows = $this->getVisibleRows('.filter-container-one tbody tr');
    $this->assertEquals(2, count($visibleRows), 'Two recipes are visible after filtering for "Mint".');
    $visibleExpected = [
      'Mojito',
      'Virgin Mojito',
    ];
    $visibleFound = array_map(fn($row) => $row->find('css', 'td')->getText(), $visibleRows);
    $this->assertEquals(
      $visibleExpected,
      array_values($visibleFound),
      'Filtered recipes match expected results.'
    );

    // Ensure Table Two (ingredients) is unaffected.
    $visibleRowsTwo = $this->countVisibleRows('.filter-container-two tbody tr');
    $totalRowsTow = count($this->getTableRows('.filter-container-two tbody tr'));
    $this->assertEquals($visibleRowsTwo, $totalRowsTow);

    // Now filter Table Two (ingredients) by "Mint".
    $filterTwo = $page->find('css', '.filter-input-two');
    $filterTwo->setValue('Mint');
    $this->waitSearchFiltered();
    $visibleRowsTwoCount = $this->countVisibleRows('.filter-container-two tbody tr');
    $this->assertEquals(1, $visibleRowsTwoCount, 'Only one ingredient row is visible after filtering for "Mint".');

    // Ensure Table One (recipes) is still filtered as before.
    $this->assertEquals(2, $this->countVisibleRows('.filter-container-one tbody tr'), 'Table One remains filtered with 2 visible rows.');
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
    return count($this->getVisibleRows($selector));
  }

  /**
   * Counts the number of visible rows in the filter container.
   *
   * @param string $selector
   *   The CSS selector for the rows to count.
   *
   * @return array
   *   The number of visible rows.
   */
  protected function getVisibleRows(string $selector = '.filter-container tbody tr'): array {
    return array_filter($this->getTableRows($selector), function ($element) {
      return $element->isVisible();
    });
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
