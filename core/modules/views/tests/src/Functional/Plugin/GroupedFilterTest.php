<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Functional\Plugin;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\views\Functional\ViewTestBase;
use Drupal\views\Tests\ViewTestData;

/**
 * Tests grouped exposed filter functionality.
 *
 * @group views
 */
class GroupedFilterTest extends ViewTestBase {

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['test_grouped_filter'];

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'options', 'views_ui'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE, $modules = []): void {
    parent::setUp(FALSE, $modules);

    $this->drupalCreateContentType(['type' => 'article']);
    // Add test field to content type to be used by the test view.
    $field_name = 'field_test_country';
    $options = [
      'france' => 'France',
      'spain' => 'Spain',
      'indonesia' => 'Indonesia',
      'china' => 'China',
    ];
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => 'list_string',
      'cardinality' => -1,
      'settings' => [
        'allowed_values' => $options,
      ],
    ])->save();
    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'bundle' => 'article',
    ])->save();

    ViewTestData::createTestViews(self::class, ['views_test_config']);
    $this->enableViewsTestModule();

    // Create an article for each allowed value.
    foreach ($options as $key => $value) {
      $this->drupalCreateNode([
        'type' => 'article',
        'title' => $value,
        $field_name => $key,
      ]);
    }
  }

  /**
   * Tests that exposed grouped filters behave as expected.
   */
  public function testGroupedFilter(): void {
    $this->drupalGet('test_grouped_filter');
    // Check all the test nodes appear when no filters are selected.
    $this->assertSession()->elementsCount('xpath', "//div[contains(@class, 'views-row')]", 4);
    $this->assertSession()->pageTextContains('France');
    $this->assertSession()->pageTextContains('Spain');
    $this->assertSession()->pageTextContains('Indonesia');
    $this->assertSession()->pageTextContains('China');

    $this->submitForm(['field_test_country_value[1]' => 1], 'Apply');
    $this->assertSession()->elementsCount('xpath', "//div[contains(@class, 'views-row')]", 2);
    // Check only France and Spain appear when group 1 (Europe) is selected.
    $this->assertSession()->pageTextContains('France');
    $this->assertSession()->pageTextContains('Spain');

    // Check only Indonesia and China appear when group 2 (Asia) is selected.
    $this->drupalGet('test_grouped_filter');
    $this->submitForm(['field_test_country_value[2]' => 2], 'Apply');
    $this->assertSession()->elementsCount('xpath', "//div[contains(@class, 'views-row')]", 2);
    $this->assertSession()->pageTextContains('Indonesia');
    $this->assertSession()->pageTextContains('China');

    // Check all the test nodes appear when both groups are selected.
    $this->drupalGet('test_grouped_filter');
    $this->submitForm(['field_test_country_value[1]' => 1, 'field_test_country_value[2]' => 2], 'Apply');
    $this->assertSession()->elementsCount('xpath', "//div[contains(@class, 'views-row')]", 4);
    $this->assertSession()->pageTextContains('France');
    $this->assertSession()->pageTextContains('Spain');
    $this->assertSession()->pageTextContains('Indonesia');
    $this->assertSession()->pageTextContains('China');
  }

}
