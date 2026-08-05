<?php

declare(strict_types=1);

namespace Drupal\Tests\views_ui\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that editing a field's aggregation function does not trigger an AJAX error.
 *
 * Regression test for a fatal error thrown by
 * \Drupal\views_ui\Form\Ajax\ConfigHandlerGroup::buildForm() when it
 * retrieved the field handler directly from the live display handler
 * instead of instantiating it through the views plugin manager. The
 * mismatched handler instance caused
 * \Drupal\views\Plugin\views\field\EntityField::submitGroupByForm() to
 * receive a NULL 'group_columns' value, which was then passed to
 * array_filter(), resulting in an uncaught TypeError surfaced to the user
 * as an AJAX HTTP 500 error.
 *
 * @see https://www.drupal.org/project/drupal/issues/3344910
 */
#[Group('views_ui')]
#[RunTestsInSeparateProcesses]
class AggregationAjaxTest extends WebDriverTestBase {

  /**
   * The machine name of the view created for this test.
   */
  protected static string $testViewId = 'test_aggregation_ajax';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'views', 'views_ui'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Build a minimal view with a single field whose handler is
    // \Drupal\views\Plugin\views\field\EntityField (plugin_id 'field'),
    // which is the handler affected by the regression under test.
    View::create([
      'id' => static::$testViewId,
      'label' => 'Test aggregation AJAX',
      'base_table' => 'node_field_data',
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'id' => 'default',
          'display_options' => [
            'fields' => [
              'changed' => [
                'id' => 'changed',
                'table' => 'node_field_data',
                'field' => 'changed',
                'entity_type' => 'node',
                'entity_field' => 'changed',
                'plugin_id' => 'field',
                'relationship' => 'none',
                'group_type' => 'group',
              ],
            ],
          ],
        ],
        'page_1' => [
          'display_plugin' => 'page',
          'id' => 'page_1',
          'display_options' => [
            'path' => 'test-aggregation-ajax',
          ],
        ],
      ],
    ])->save();

    $admin_user = $this->drupalCreateUser(['administer views']);
    $this->drupalLogin($admin_user);
  }

  /**
   * The exact message Drupal core shows in the page when an AJAX request fails and is not otherwise handled.
   *
   * @see \Drupal\Core\Ajax\AjaxResponse
   * @see https://www.drupal.org/project/drupal/issues/2987444
   */
  protected const AJAX_ERROR_MESSAGE = "Oops, something went wrong. Check your browser's developer console for more details.";

  /**
   * Tests switching a field's aggregation function via the AJAX dialog.
   *
   * The regression only manifests when *re-editing* an aggregation handler
   * that was already configured and saved, not when configuring it for the
   * first time. The test therefore configures the field twice: once to
   * establish an initial aggregation function, and a second time to switch
   * it, which is the step that failed before the fix.
   */
  public function testSwitchAggregationFunctionViaAjax(): void {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->drupalGet('admin/structure/views/view/' . static::$testViewId);

    // Enable aggregation for the display via the "Use aggregation" link in
    // the advanced settings column. This opens
    // \Drupal\views_ui\Form\Ajax\Display.
    $page->clickLink('Use aggregation');
    $this->assertNotEmpty(
      $assert_session->waitForElementVisible('css', '.views-ui-dialog'),
      'The "Use aggregation" dialog did not open.'
    );
    $page->checkField('group_by');
    $this->pressDialogButton('Apply');
    $this->assertNotEmpty(
      $assert_session->waitForElementRemoved('css', '.views-ui-dialog'),
      'The "Use aggregation" dialog is still open after applying the change.'
    );

    // Configure the field's aggregation function for the first time. This
    // must succeed both before and after the fix.
    $this->setFieldAggregationFunction('count');

    // Re-open the same, already-configured handler and switch the
    // aggregation function from COUNT to COUNT DISTINCT. Before the fix,
    // this second edit triggered array_filter(NULL) in
    // EntityField::submitGroupByForm() and the AJAX request failed with an
    // HTTP 500 error, leaving the modal open.
    $this->setFieldAggregationFunction('count_distinct');

    // The field row must still expose the aggregation settings link,
    // confirming the display tab rebuilt correctly after the AJAX
    // submission.
    $assert_session->linkExists('Aggregation settings');
  }

  /**
   * Opens the aggregation settings dialog and applies an aggregation function to the 'changed' field.
   *
   * Encapsulates the full click-configure-apply cycle so it can be run
   * twice: once to create the aggregation handler, and once to edit it,
   * which is the specific transition affected by the regression covered in
   * this test.
   *
   * @param string $group_type
   *   The aggregation function to select, e.g. 'count' or 'count_distinct'.
   *   Must be a valid key from the query plugin's aggregation info (see
   *   \Drupal\views\Plugin\views\query\Sql::getAggregationInfo()).
   */
  protected function setFieldAggregationFunction(string $group_type): void {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    // This link is rendered by \Drupal\views_ui\Form\Ajax\ConfigHandlerGroup
    // and opens the exact form patched for this issue.
    $page->clickLink('Aggregation settings');
    $this->assertNotEmpty(
      $assert_session->waitForElementVisible('css', '.views-ui-dialog'),
      sprintf('The aggregation settings dialog did not open while setting "%s".', $group_type)
    );
    $assert_session->fieldExists('options[group_type]');
    $page->selectFieldOption('options[group_type]', $group_type);
    $this->pressDialogButton('Apply');

    // This is the primary assertion for the regression: before the fix,
    // the AJAX request threw an uncaught TypeError and the dialog was left
    // open, so waitForElementRemoved() times out and returns FALSE.
    $this->assertNotEmpty(
      $assert_session->waitForElementRemoved('css', '.views-ui-dialog'),
      sprintf('The aggregation settings dialog is still open after setting the aggregation function to "%s". This indicates the AJAX request failed.', $group_type)
    );

    // Belt-and-braces check for the generic AJAX failure message that
    // Drupal core prints on the main page when an AJAX request errors out.
    $assert_session->pageTextNotContains(static::AJAX_ERROR_MESSAGE);

    // Note: WebDriverTestBase::$failOnJavascriptConsoleErrors is TRUE by
    // default, so an uncaught Drupal.AjaxError logged to the browser
    // console during this request would also fail the test on its own,
    // regardless of how verbose the site's error-display setting is.
  }

  /**
   * Presses a button inside the currently open Views UI modal dialog.
   *
   * @param string $text
   *   The visible text of the button to press.
   */
  protected function pressDialogButton(string $text): void {
    $this->assertSession()
      ->elementExists('css', sprintf('.ui-dialog-buttonset button:contains("%s")', $text))
      ->press();
  }

}
