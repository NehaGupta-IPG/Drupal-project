<?php

namespace Drupal\FunctionalJavascriptTests;

/**
 * Tests the ListFilter functionality with a temporary page.
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
   * Tests the ListFilter functionality.
   *
   * @dataProvider dataProvider
   */
  public function testListFilter($filters, $expected, $not_expected): void {
    // Visit the temporary page.
    $this->drupalGet('/list-filter/' . $this->dataName());

    $page = $this->getSession()->getPage();
    $filterInput = $page->find('css', '.filter-input');
    foreach ($filters as $index => $filter) {
      $filterInput->setValue($filter);
      sleep(1);
      $expectedByType = $expected[$index] ?? [];
      $notExpectedByType = $not_expected[$index] ?? [];
      $max = max(count($expectedByType), count($notExpectedByType));
      for ($i=0; $i < $max; $i++) {
        if (isset($expectedByType[$i])) {
          $this->assertSession()->pageTextContains($expectedByType[$i]);
        }
        if (isset($not_expected[$i])) {
          $this->assertSession()->pageTextNotContains($notExpectedByType[$i]);
        }
      }
    }
  }

  /**
   * Provides fake data for the test.
   *
   * @return \Generator
   *   The fake data.
   */
  public static function dataProvider(): \Generator {
    yield 'ok' => [
      [
        'Drama', 'Crime',
      ],
      [
        ['Inception', 'Pulp Fiction'],
        ['The Godfather'],
      ],
      [
        ['The Godfather', 'The Matrix', 'Spirited Away'],
        ['Inception', 'Pulp Fiction', 'The Matrix', 'Spirited Away'],
      ],
    ];
    yield 'auto_activate_false' => [
      ['drama', 'no matter filter is not active'],
      [
        ['Inception', 'Pulp Fiction', 'The Godfather', 'The Matrix', 'Spirited Away'],
        ['Inception', 'Pulp Fiction', 'The Godfather', 'The Matrix', 'Spirited Away']
      ],
      [],
    ];
  }

  /**
   * Tests the ListFilter with an overridden library.
   */
  public function testListFilterOverride() {
    $this->drupalGet('/list-filter/override_library');
    $page = $this->getSession()->getPage();
    $filterInput = $page->find('css', '.filter-input');
    $filterInput->setValue('patriarch');
    sleep(1);
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
    sleep(1);
    $totalVisibleRows = count(array_filter($page->findAll('css', '.filter-container tbody tr'), function ($element) {
      return $element->isVisible();
    }));
    $this->assertEquals($totalRows, $totalVisibleRows, 'All rows are visible before reaching the minimum filter length.');
    $filterInput->setValue('patria');
    sleep(1);
    $totalVisibleRows = count(array_filter($page->findAll('css', '.filter-container tbody tr'), function ($element) {
      return $element->isVisible();
    }));
    $this->assertLessThan($totalRows, $totalVisibleRows, 'Rows are visible after reaching the minimum filter length.');;
  }

  /**
   * Tests the search start of words functionality.
   */
  public function testSearchStartOfWords() {
    $this->drupalGet('/list-filter/search_start_of_words');
    $page = $this->getSession()->getPage();
    $filterInput = $page->find('css', '.filter-input');
    $filterInput->setValue('sub');
    sleep(1);
    $trs = $page->findAll('css', '.filter-container tbody tr');

    $this->assertTrue($trs[2]->isVisible(), 'Pull Fiction move should be visible when searching for "sub".');
    $this->assertFalse($trs[4]->isVisible(), 'Spirited Away should not be visible when searching for "sub".');
  }

}