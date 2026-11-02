<?php

declare(strict_types=1);

namespace Drupal\Tests\views\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\views\Tests\ViewTestData;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the basic AJAX functionality of Views exposed forms.
 */
#[Group('views')]
#[RunTestsInSeparateProcesses]
class ExposedFilterAJAXTest extends WebDriverTestBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'views',
    'views_test_modal',
    'views_test_config',
    'user_test_views',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['list_view', 'test_user_name'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Enable AJAX on the /admin/content View.
    \Drupal::configFactory()->getEditable('views.view.content')
      ->set('display.default.display_options.use_ajax', TRUE)
      ->save();

    // Import user_test_views and set it to use ajax.
    ViewTestData::createTestViews(get_class($this), ['user_test_views']);
    ViewTestData::createTestViews(self::class, ['views_test_config']);
    \Drupal::configFactory()->getEditable('views.view.test_user_name')
      ->set('display.default.display_options.use_ajax', TRUE)
      ->save();

    // Create a Content type and two test nodes.
    $this->createContentType(['type' => 'page']);
    $this->createNode(['title' => 'Page One']);
    $this->createNode(['title' => 'Page Two']);

    // Create a user privileged enough to use exposed filters and view content.
    $user = $this->drupalCreateUser([
      'administer site configuration',
      'access content',
      'access content overview',
      'edit any page content',
      'view the administration theme',
    ]);
    $this->drupalLogin($user);
  }

  /**
   * Tests if exposed filtering via AJAX works for the "Content" View.
   */
  public function testExposedFiltering(): void {
    // Create an account that can update the sticky flag.
    $user = $this->drupalCreateUser([
      'access content overview',
      'administer nodes',
      'edit any page content',
    ]);
    $this->drupalLogin($user);

    // Visit the View page.
    $this->drupalGet('admin/content');

    $session = $this->getSession();

    // Ensure that the Content we're testing for is present.
    $html = $session->getPage()->getHtml();
    $this->assertStringContainsString('Page One', $html);
    $this->assertStringContainsString('Page Two', $html);
    $this->assertSession()->addressEquals('admin/content');

    // Search for "Page One".
    $this->submitForm(['title' => 'Page One'], 'Filter');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Verify that only the "Page One" Node is present.
    $html = $session->getPage()->getHtml();
    $this->assertStringContainsString('Page One', $html);
    $this->assertStringNotContainsString('Page Two', $html);
    $this->assertSession()->addressEquals('admin/content?title=Page%20One&type=All&status=All');

    // Search for "Page Two".
    $this->submitForm(['title' => 'Page Two'], 'Filter');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Verify that only the "Page Two" Node is present.
    $html = $session->getPage()->getHtml();
    $this->assertStringContainsString('Page Two', $html);
    $this->assertStringNotContainsString('Page One', $html);
    $this->assertSession()->addressEquals('admin/content?type=All&status=All&title=Page%20Two');

    // Submit bulk actions form to ensure that the previous AJAX submit does not
    // break it.
    $this->submitForm([
      'action' => 'node_make_sticky_action',
      'node_bulk_form[0]' => TRUE,
    ], 'Apply to selected items');

    // Verify that the action was performed.
    $this->assertSession()->pageTextContains('Make content sticky was applied to 1 item.');
    $this->assertSession()->addressEquals('admin/content?type=All&status=All&title=Page%20Two');

    // Reset the form.
    $this->submitForm([], 'Reset');

    $this->assertSession()->pageTextContains('Page One');
    $this->assertSession()->pageTextContains('Page Two');
    $this->assertFalse($session->getPage()->hasButton('Reset'));
    $this->assertSession()->addressEquals('admin/content');
  }

  /**
   * Tests if exposed filtering via AJAX theme negotiation works.
   */
  public function testExposedFilteringThemeNegotiation(): void {
    // Install 'default_admin' and configure it as administrative theme.
    $this->container->get('theme_installer')->install(['default_admin']);
    $this->config('system.theme')->set('admin', 'default_admin')->save();

    // Visit the View page.
    $this->drupalGet('admin/content');

    // Search for "Page One".
    $this->submitForm(['title' => 'Page One'], 'Filter');
    $this->assertSession()->assertExpectedAjaxRequest(1);

    // Verify that the theme is the 'default_admin' admin theme and not the default
    // theme ('stark').
    $settings = $this->getDrupalSettings();
    $this->assertNotNull($settings['ajaxPageState']['theme_token']);
    $this->assertEquals('default_admin', $settings['ajaxPageState']['theme']);
  }

  /**
   * Tests if exposed filtering via AJAX works in a modal.
   */
  public function testExposedFiltersInModal(): void {
    $this->drupalGet('views-test-modal/modal');

    $assert = $this->assertSession();

    $assert->elementExists('named', ['link', 'Administer content'])->click();
    $dialog = $assert->waitForElementVisible('css', '.views-test-modal');

    $session = $this->getSession();
    // Ensure that the Content we're testing for is present.
    $html = $session->getPage()->getHtml();
    $this->assertStringContainsString('Page One', $html);
    $this->assertStringContainsString('Page Two', $html);

    // Search for "Page One".
    $session->getPage()->fillField('title', 'Page One');
    $assert->elementExists('css', '.ui-dialog-buttonpane')->pressButton('Filter');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Verify that only the "Page One" Node is present.
    $html = $session->getPage()->getHtml();
    $this->assertStringContainsString('Page One', $html);
    $this->assertStringNotContainsString('Page Two', $html);

    // Close and re-open the modal.
    $assert->buttonExists('Close', $dialog)->press();
    $assert->elementExists('named', ['link', 'Administer content'])->click();
    $assert->waitForElementVisible('css', '.views-test-modal');

    // Ensure that the Content we're testing for is present.
    $html = $session->getPage()->getHtml();
    $this->assertStringContainsString('Page One', $html);
    $this->assertStringContainsString('Page Two', $html);

    // Search for "Page One".
    $session->getPage()->fillField('title', 'Page One');
    $assert->elementExists('css', '.ui-dialog-buttonpane')->pressButton('Filter');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Verify that only the "Page One" Node is present.
    $html = $session->getPage()->getHtml();
    $this->assertStringContainsString('Page One', $html);
    $this->assertStringNotContainsString('Page Two', $html);
  }

  /**
   * Tests which progress type the exposed form and the view refresh use.
   *
   * @param array $types
   *   The data-progress-type attributes to put on the page, keyed by where they
   *   go: 'button' (the submit button), 'form' (the form element) or
   *   'before_form' (an element before the form, inside the view).
   * @param string $filter_type
   *   The progress type expected when the exposed form is submitted.
   * @param string $refresh_type
   *   The progress type expected when the view is refreshed.
   */
  #[DataProvider('providerExposedFiltersProgressType')]
  public function testExposedFiltersProgressType(array $types, string $filter_type, string $refresh_type): void {
    \Drupal::state()->set('views_test_config.progress_type', $types);
    $this->drupalGet('list_view');
    $this->recordProgressTypes();
    $this->submitForm(['title' => 'Page One'], 'Apply');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Page One');
    $this->assertSession()->pageTextNotContains('Page Two');
    $this->assertSame([$filter_type], $this->getProgressTypes());

    $this->recordProgressTypes();
    $this->getSession()->executeScript("jQuery('.js-view-dom-id-' + Object.values(drupalSettings.views.ajaxViews)[0].view_dom_id).trigger('RefreshView');");
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSame([$refresh_type], $this->getProgressTypes());
  }

  /**
   * Provides attributes and expected progress types for the exposed form.
   */
  public static function providerExposedFiltersProgressType(): array {
    return [
      'nothing set' => [[], 'fullscreen', 'fullscreen'],
      'on the submit button' => [['button' => 'throbber', 'before_form' => 'none'], 'throbber', 'none'],
      'on the form' => [['form' => 'throbber', 'before_form' => 'none'], 'throbber', 'none'],
      'elsewhere in the view' => [['before_form' => 'throbber'], 'throbber', 'throbber'],
    ];
  }

  /**
   * Tests that the pager and the exposed form can use different progress types.
   */
  public function testPagerProgressType(): void {
    for ($i = 1; $i <= 10; $i++) {
      $this->createNode(['title' => 'Page ' . ($i + 2)]);
    }
    \Drupal::state()->set('views_test_config.progress_type', [
      'button' => 'throbber',
      'pager' => 'none',
    ]);
    $this->drupalGet('list_view');
    $this->recordProgressTypes();
    $this->assertSession()->elementExists('css', '.js-pager__items a[rel="next"]')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->elementExists('css', '.js-pager__items a[rel="prev"]');
    $this->assertSame(['none'], $this->getProgressTypes());

    $this->recordProgressTypes();
    $this->submitForm(['title' => 'Page One'], 'Apply');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Page One');
    $this->assertSame(['throbber'], $this->getProgressTypes());
  }

  /**
   * Records the progress type of each AJAX request sent from now on.
   */
  protected function recordProgressTypes(): void {
    $this->getSession()->executeScript(<<<JS
      window.viewsTestProgressTypes = [];
      if (!Drupal.Ajax.prototype.viewsTestBeforeSend) {
        Drupal.Ajax.prototype.viewsTestBeforeSend = Drupal.Ajax.prototype.beforeSend;
        Drupal.Ajax.prototype.beforeSend = function (xmlhttprequest, options) {
          window.viewsTestProgressTypes.push(this.progress.type);
          return this.viewsTestBeforeSend(xmlhttprequest, options);
        };
      }
      JS);
  }

  /**
   * Gets the progress types recorded by recordProgressTypes().
   *
   * @return string[]
   *   The progress types, in the order the requests were sent.
   */
  protected function getProgressTypes(): array {
    return $this->getSession()->evaluateScript('window.viewsTestProgressTypes');
  }

  /**
   * Tests exposed filtering via AJAX with a button element.
   */
  public function testExposedFilteringWithButtonElement(): void {
    // Install theme to test with template system.
    \Drupal::service('theme_installer')->install(['views_test_theme']);

    // Make base theme default then test for hook invocations.
    $this->config('system.theme')
      ->set('default', 'views_test_theme')
      ->save();

    $this->drupalGet('admin/content');

    $session = $this->getSession();
    // Ensure that the Content we're testing for is present.
    $html = $session->getPage()->getHtml();
    $this->assertStringContainsString('Page One', $html);
    $this->assertStringContainsString('Page Two', $html);
    $button_tag = $session->getPage()->findButton('edit-submit-content')->getTagName();

    // Make sure the submit button has been transformed to a button element.
    $this->assertEquals('button', $button_tag);

    $drupal_settings = $this->getDrupalSettings();
    $ajax_views_before = $drupal_settings['views']['ajaxViews'];

    // Search for "Page One".
    $this->submitForm(['title' => 'Page One'], 'Filter');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Verify that only the "Page One" Node is present.
    $html = $session->getPage()->getHtml();
    $this->assertStringContainsString('Page One', $html);
    $this->assertStringNotContainsString('Page Two', $html);
    $drupal_settings = $this->getDrupalSettings();
    $ajax_views_after = $drupal_settings['views']['ajaxViews'];

    // Make sure that the views_dom_id didn't change, which would indicate that
    // the page reloaded instead of doing an AJAX update.
    $this->assertSame($ajax_views_before, $ajax_views_after);
  }

  /**
   * Tests that errors messages are displayed for exposed filters via ajax.
   */
  public function testExposedFilterErrorMessages(): void {
    $this->drupalGet('test_user_name');
    // Submit an invalid name, triggering validation errors.
    $name = $this->randomMachineName();
    $this->submitForm(['uid' => $name], 'Apply');
    $this->assertSession()->waitForElement('css', 'div[aria-label="Error message"]');
    $this->assertSession()->pageTextContainsOnce(sprintf('There are no users matching "%s"', $name));

    \Drupal::service('module_installer')->install(['inline_form_errors']);

    $this->drupalGet('test_user_name');
    // Submit an invalid name, triggering validation errors.
    $name = $this->randomMachineName();
    $this->submitForm(['uid' => $name], 'Apply');
    $this->assertSession()->waitForElement('css', 'div[aria-label="Error message"]');
    $this->assertSession()->pageTextContainsOnce(sprintf('There are no users matching "%s"', $name));
  }

}
