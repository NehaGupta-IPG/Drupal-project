<?php

declare(strict_types=1);

namespace Drupal\Tests\views\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\views\Tests\ViewTestData;
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
    'taxonomy',
    'views_test_modal',
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
  public static $testViews = ['test_user_name'];

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
    // Install 'claro' and configure it as administrative theme.
    $this->container->get('theme_installer')->install(['claro']);
    $this->config('system.theme')->set('admin', 'claro')->save();

    // Visit the View page.
    $this->drupalGet('admin/content');

    // Search for "Page One".
    $this->submitForm(['title' => 'Page One'], 'Filter');
    $this->assertSession()->assertExpectedAjaxRequest(1);

    // Verify that the theme is the 'claro' admin theme and not the default
    // theme ('stark').
    $settings = $this->getDrupalSettings();
    $this->assertNotNull($settings['ajaxPageState']['theme_token']);
    $this->assertEquals('claro', $settings['ajaxPageState']['theme']);
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

  /**
   * Tests that multi-select exposed filters properly replace URL parameters.
   *
   * This test validates that when changing selections in a multi-select
   * exposed filter (like taxonomy entity reference), old URL array parameters
   * are properly removed and replaced with new ones, rather than accumulating.
   */
  public function testMultiSelectExposedFilterUrlParameters(): void {
    // Create a taxonomy vocabulary.
    $vocabulary = Vocabulary::create([
      'name' => 'Tags',
      'vid' => 'tags',
    ]);
    $vocabulary->save();

    // Create taxonomy terms.
    $term1 = Term::create([
      'name' => 'Term 1',
      'vid' => 'tags',
    ]);
    $term1->save();

    $term2 = Term::create([
      'name' => 'Term 2',
      'vid' => 'tags',
    ]);
    $term2->save();

    // Add taxonomy reference field to the page content type.
    $field_storage = \Drupal::entityTypeManager()
      ->getStorage('field_storage_config')
      ->create([
        'field_name' => 'field_tags',
        'entity_type' => 'node',
        'type' => 'entity_reference',
        'settings' => [
          'target_type' => 'taxonomy_term',
        ],
      ]);
    $field_storage->save();

    $field = \Drupal::entityTypeManager()
      ->getStorage('field_config')
      ->create([
        'field_storage' => $field_storage,
        'bundle' => 'page',
        'label' => 'Tags',
        'settings' => [
          'handler' => 'default:taxonomy_term',
          'handler_settings' => [
            'target_bundles' => ['tags' => 'tags'],
          ],
        ],
      ]);
    $field->save();

    // Create nodes with different tags.
    $this->createNode([
      'title' => 'Node with Term 1',
      'type' => 'page',
      'field_tags' => [$term1->id()],
    ]);

    $this->createNode([
      'title' => 'Node with Term 2',
      'type' => 'page',
      'field_tags' => [$term2->id()],
    ]);

    // Add exposed taxonomy filter to the admin/content view.
    $view = \Drupal::service('entity_type.manager')->getStorage('view')->load('content');
    $display = &$view->getDisplay('default');
    $display['display_options']['filters']['field_tags_target_id'] = [
      'id' => 'field_tags_target_id',
      'table' => 'node__field_tags',
      'field' => 'field_tags_target_id',
      'relationship' => 'none',
      'exposed' => TRUE,
      'expose' => [
        'identifier' => 'field_tags_target_id',
        'label' => 'Tags',
        'multiple' => TRUE,
      ],
      'type' => 'select',
      'vid' => 'tags',
      'plugin_id' => 'taxonomy_index_tid',
    ];
    $view->save();

    // Need admin permission to see taxonomy filter.
    $user = $this->drupalCreateUser([
      'access content overview',
      'administer nodes',
      'administer taxonomy',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('admin/content');
    $session = $this->getSession();

    // Ensure both nodes are initially visible.
    $this->assertSession()->pageTextContains('Node with Term 1');
    $this->assertSession()->pageTextContains('Node with Term 2');
    $this->assertSession()->addressEquals('admin/content');

    // Select Term 1 and apply filter.
    $page = $session->getPage();
    $page->selectFieldOption('field_tags_target_id[]', $term1->id());
    $page->pressButton('Filter');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Verify only Node with Term 1 is visible and URL has correct parameter.
    $this->assertSession()->pageTextContains('Node with Term 1');
    $this->assertSession()->pageTextNotContains('Node with Term 2');
    $current_url = $this->getSession()->getCurrentUrl();
    $this->assertStringContainsString('field_tags_target_id', $current_url);
    $this->assertStringContainsString((string) $term1->id(), $current_url);

    // Now change selection: deselect Term 1 and select Term 2.
    $page->selectFieldOption('field_tags_target_id[]', $term2->id());
    $page->pressButton('Filter');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Verify only Node with Term 2 is visible.
    $this->assertSession()->pageTextContains('Node with Term 2');
    $this->assertSession()->pageTextNotContains('Node with Term 1');

    // Critical assertion: URL should only contain Term 2 parameter, not Term 1.
    // This is the bug fix validation - without the fix, both term IDs would
    // appear in the URL.
    $current_url = $this->getSession()->getCurrentUrl();
    $this->assertStringContainsString('field_tags_target_id', $current_url);
    $this->assertStringContainsString((string) $term2->id(), $current_url);
    // This assertion would fail without the fix.
    $this->assertStringNotContainsString((string) $term1->id(), $current_url);
  }

}
