<?php

declare(strict_types=1);

namespace Drupal\Tests\views_ui\Functional;

use Drupal\Core\Database\Database;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the UI preview functionality.
 */
#[Group('views_ui')]
#[RunTestsInSeparateProcesses]
class PreviewTest extends UITestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = [
    'test_preview',
    'test_preview_error',
    'test_pager_full',
    'test_mini_pager',
    'test_click_sort',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests contextual links in the preview form.
   */
  public function testPreviewContextual(): void {
    \Drupal::service('module_installer')->install(['contextual']);
    $this->resetAll();

    $this->drupalGet('admin/structure/views/view/test_preview/edit');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm($edit = [], 'Update preview');

    // Verify that the contextual link to add a new field is shown.
    $selector = $this->assertSession()->buildXPathQuery('//div[@id="views-live-preview"]//ul[contains(@class, :ul-class)]/li/a[contains(@href, :href)]', [
      ':ul-class' => 'contextual-links',
      ':href' => '/admin/structure/views/nojs/add-handler/test_preview/default/filter',
    ]);
    $this->assertSession()->elementsCount('xpath', $selector, 1);

    $this->submitForm(['view_args' => '100'], 'Update preview');

    // Test that area text and exposed filters are present and rendered.
    $this->assertSession()->fieldExists('id');
    $this->assertSession()->pageTextContains('Test header text');
    $this->assertSession()->pageTextContains('Test footer text');
    $this->assertSession()->pageTextContains('Test empty text');

    $this->submitForm(['view_args' => '0'], 'Update preview');

    // Test that area text and exposed filters are present and rendered.
    $this->assertSession()->fieldExists('id');
    $this->assertSession()->pageTextContains('Test header text');
    $this->assertSession()->pageTextContains('Test footer text');
    $this->assertSession()->pageTextContains('Test empty text');
  }

  /**
   * Tests arguments in the preview form.
   */
  public function testPreviewUI(): void {
    $this->drupalGet('admin/structure/views/view/test_preview/edit');
    $this->assertSession()->statusCodeEquals(200);

    $this->submitForm($edit = [], 'Update preview');

    $selector = '//div[@class = "views-row"]';
    $this->assertSession()->elementsCount('xpath', $selector, 5);

    // Filter just the first result.
    $this->submitForm($edit = ['view_args' => '1'], 'Update preview');
    $this->assertSession()->elementsCount('xpath', $selector, 1);

    // Filter for no results.
    $this->submitForm($edit = ['view_args' => '100'], 'Update preview');
    $this->assertSession()->elementNotExists('xpath', $selector);

    // Test that area text and exposed filters are present and rendered.
    $this->assertSession()->fieldExists('id');
    $this->assertSession()->pageTextContains('Test header text');
    $this->assertSession()->pageTextContains('Test footer text');
    $this->assertSession()->pageTextContains('Test empty text');

    // Test feed preview.
    $view = [];
    $view['label'] = $this->randomMachineName(16);
    $view['id'] = $this->randomMachineName(16);
    $view['page[create]'] = 1;
    $view['page[title]'] = $this->randomMachineName(16);
    $view['page[path]'] = $this->randomMachineName(16);
    $view['page[feed]'] = 1;
    $view['page[feed_properties][path]'] = $this->randomMachineName(16);
    $this->drupalGet('admin/structure/views/add');
    $this->submitForm($view, 'Save and edit');
    $this->clickLink('Feed');
    $this->submitForm([], 'Update preview');
    $this->assertSession()->elementTextContains('xpath', '//div[@id="views-live-preview"]/pre', '<title>' . $view['page[title]'] . '</title>');

    // Test the non-default UI display options.
    // Statistics only, no query.
    $settings = \Drupal::configFactory()->getEditable('views.settings');
    $settings->set('ui.show.performance_statistics', TRUE)->save();
    $this->drupalGet('admin/structure/views/view/test_preview/edit');
    $this->submitForm($edit = ['view_args' => '100'], 'Update preview');
    $this->assertSession()->pageTextContains('Query build time');
    $this->assertSession()->pageTextContains('Query execute time');
    $this->assertSession()->pageTextContains('View render time');
    $this->assertSession()->responseNotContains('<strong>Query</strong>');

    // Statistics and query.
    $settings->set('ui.show.sql_query.enabled', TRUE)->save();
    $this->submitForm($edit = ['view_args' => '100'], 'Update preview');
    $this->assertSession()->pageTextContains('Query build time');
    $this->assertSession()->pageTextContains('Query execute time');
    $this->assertSession()->pageTextContains('View render time');
    $this->assertSession()->responseContains('<strong>Query</strong>');
    $query_string = <<<SQL
SELECT "views_test_data"."name" AS "views_test_data_name"
FROM
{views_test_data} "views_test_data"
WHERE (views_test_data.id = '100')
SQL;
    $this->assertSession()->assertEscaped($query_string);

    // Test that the statistics and query are rendered above the preview.
    $this->assertLessThan(strpos($this->getSession()->getPage()->getContent(), 'js-view-dom-id'), strpos($this->getSession()->getPage()->getContent(), 'views-query-info'));

    // Test that statistics and query rendered below the preview.
    $settings->set('ui.show.sql_query.where', 'below')->save();
    $this->submitForm($edit = ['view_args' => '100'], 'Update preview');
    $this->assertLessThan(strpos($this->getSession()->getPage()->getContent(), 'views-query-info'), strpos($this->getSession()->getPage()->getContent(), 'js-view-dom-id'), 'Statistics shown below the preview.');

    // Test that the preview title isn't double escaped.
    $this->drupalGet("admin/structure/views/nojs/display/test_preview/default/title");
    $this->submitForm($edit = ['title' => 'Double & escaped'], 'Apply');
    $this->submitForm([], 'Update preview');
    $this->assertSession()->elementsCount('xpath', '//div[@id="views-live-preview"]/div[contains(@class, views-query-info)]//td[text()="Double & escaped"]', 1);
  }

  /**
   * Tests the additional information query info area.
   */
  public function testPreviewAdditionalInfo(): void {
    \Drupal::service('module_installer')->install(['views_ui_test']);
    $this->resetAll();

    $this->drupalGet('admin/structure/views/view/test_preview/edit');
    $this->assertSession()->statusCodeEquals(200);

    $this->submitForm($edit = [], 'Update preview');

    // Check for implementation of hook_views_preview_info_alter().
    // @see views_ui_test.module
    // Verify that Views Query Preview Info area was altered.
    $this->assertSession()->elementsCount('xpath', '//div[@id="views-live-preview"]/div[contains(@class, views-query-info)]//td[text()="Test row count"]', 1);
    // Check that additional assets are attached.
    $this->assertStringContainsString('views_ui_test/views_ui_test.test', $this->getDrupalSettings()['ajaxPageState']['libraries'], 'Attached library found.');
    $this->assertSession()->responseContains('css/views_ui_test.test.css');
  }

  /**
   * Tests view validation error messages in the preview.
   */
  public function testPreviewError(): void {
    $this->drupalGet('admin/structure/views/view/test_preview_error/edit');
    $this->assertSession()->statusCodeEquals(200);

    $this->submitForm($edit = [], 'Update preview');

    $this->assertSession()->pageTextContains('Unable to preview due to validation errors.');
  }

  /**
   * Tests HTML is filtered from the view title when previewing.
   */
  public function testPreviewTitle(): void {
    // Update the view and change title with html tags.
    \Drupal::configFactory()->getEditable('views.view.test_preview')
      ->set('display.default.display_options.title', '<strong>Test preview title</strong>')
      ->save();

    $this->drupalGet('admin/structure/views/view/test_preview/edit');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm([], 'Update preview');
    $this->assertSession()->pageTextContains('Test preview title');
    // Ensure allowed HTML tags are still displayed.
    $this->assertCount(2, $this->getNodeElementsByXpath('//div[@id="views-live-preview"]//strong[text()=:text]', [':text' => 'Test preview title']));

    // Ensure other tags are filtered.
    \Drupal::configFactory()->getEditable('views.view.test_preview')
      ->set('display.default.display_options.title', '<b>Test preview title</b>')
      ->save();
    $this->submitForm([], 'Update preview');
    $this->assertCount(0, $this->getNodeElementsByXpath('//div[@id="views-live-preview"]//b[text()=:text]', [':text' => 'Test preview title']));
  }

  /**
   * Tests the live preview limit.
   */
  public function testPreviewLimit() {
    $live_preview_limit = \Drupal::ConfigFactory()->get('views.settings')->get('ui.show.live_preview_limit') ?: 50;

    // Add additional records to the test data table.
    $count = $live_preview_limit + 1;
    $data_set = $this->dataSet();
    $query = Database::getConnection()->insert('views_test_data')->fields(array_keys($data_set[0]));
    for ($i = 1; $i <= $count; $i++) {
      $record = [
        'name' => $this->randomString(12),
        'age' => 50,
        'job' => $this->randomString(32),
        'created' => gmmktime(0, 0, 0, 1, 1, 2000),
        'status' => 1,
      ];
      $query->values($record);
    }
    $query->execute();

    // Test display all items.
    $this->drupalGet('admin/structure/views/nojs/display/test_preview/default/pager');
    $this->submitForm($edit = ['pager[type]' => 'none'], 'Apply');
    $this->drupalGet('admin/structure/views/view/test_preview/edit');
    $this->submitForm($edit = [], t('Save'));
    $this->submitForm($edit = [], t('Update preview'));

    // Verify that not all items are shown.
    $elements = $this->xpath('//div[@class = "view-content"]/div[contains(@class, views-row)]');
    $this->assertEquals($live_preview_limit, count($elements));
    $message = "Limit of results is not set. The live preview has a limit of $live_preview_limit items per page.";
    $this->assertSession()->pageTextContains($message);

    // Test full pager when the views limit is less than the maximum preview
    // limit.
    $limit = 20;
    $this->drupalGet('admin/structure/views/nojs/display/test_preview/default/pager');
    $this->submitForm($edit = ['pager[type]' => 'full'], 'Apply');
    $this->drupalGet('admin/structure/views/nojs/display/test_preview/default/pager_options');
    $this->submitForm($edit = ['pager_options[items_per_page]' => $limit], 'Apply');
    $this->drupalGet('admin/structure/views/view/test_preview/edit');
    $this->submitForm($edit = [], t('Save'));
    $this->submitForm($edit = [], t('Update preview'));

    // Verify that not all items are shown.
    $elements = $this->xpath('//div[@class = "view-content"]/div[contains(@class, views-row)]');
    $this->assertEquals($limit, count($elements));
    $message = "The live preview has a limit of $live_preview_limit items per page.";
    $this->assertSession()->pageTextNotContains($message);

    // Test full pager when the views limit is greater than the maximum preview
    // limit.
    $limit = $live_preview_limit + 1;
    $this->drupalGet('admin/structure/views/nojs/display/test_preview/default/pager_options');
    $this->submitForm($edit = ['pager_options[items_per_page]' => $limit], 'Apply');
    $this->drupalGet('admin/structure/views/view/test_preview/edit');
    $this->submitForm($edit = [], t('Save'));
    $this->submitForm($edit = [], t('Update preview'));

    // Verify that not all items are shown.
    $elements = $this->xpath('//div[@class = "view-content"]/div[contains(@class, views-row)]');
    $this->assertEquals($live_preview_limit, count($elements));
    $message = "Limit of results is set to $limit. The live preview has a limit of $live_preview_limit items per page.";
    $this->assertSession()->pageTextContains($message);
  }

}
