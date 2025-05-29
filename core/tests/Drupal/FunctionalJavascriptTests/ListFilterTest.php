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
   *
   * This test checks if the filter works correctly when the library is overridden
   * and the auto-activate feature is disabled.
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

}