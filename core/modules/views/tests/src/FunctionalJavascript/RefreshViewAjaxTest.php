<?php

declare(strict_types=1);

namespace Drupal\Tests\views\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\views\Tests\ViewTestData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the RefreshView trigger.
 */
#[Group('views')]
#[RunTestsInSeparateProcesses]
class RefreshViewAjaxTest extends WebDriverTestBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = ['node', 'views', 'views_test_config'];

  /**
   * Claro has enough CSS to have the ".view-name" selector.
   *
   * @var string
   */
  protected $defaultTheme = 'claro';

  /**
   * The views to use for testing.
   *
   * @var string[]
   */
  public static array $testViews = ['test_refresh_view'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    ViewTestData::createTestViews(self::class, ['views_test_config']);

    $this->createContentType(['type' => 'page']);
    $this->createNode(['title' => 'Test Page', 'type' => 'page']);

    $user = $this->drupalCreateUser([
      'access content',
    ]);
    $this->drupalLogin($user);
  }

  /**
   * Tests that exposed operators don't get double-encoded after RefreshView.
   */
  public function testExposedOperatorEncoding(): void {
    $this->drupalGet('test-refresh-view');

    $session = $this->getSession();
    $page = $session->getPage();
    $session_assert = $this->assertSession();

    // Ensure the content is present.
    $session_assert->pageTextContains('Test Page');
    $page->selectFieldOption('changed_op', '=');

    // Trigger Ajax by clicking on a sortable table header.
    $page->clickLink('Title');
    $session_assert->assertWaitOnAjaxRequest();

    // Verify content is still present after first Ajax request.
    $session_assert->pageTextContains('Test Page');

    $page->clickLink('Refresh View');
    $session_assert->assertWaitOnAjaxRequest();
    $this->captureAfterRefresh();

    // After RefreshView, verify that:
    // 1. The content is still present
    // 2. No form validation error appears about invalid operator value
    $session_assert->pageTextContains('Test Page');

    // The specific error we're testing for would be something like:
    // "The submitted value %3D in the Operator element is not allowed."
    // This would happen if the operator was double-encoded from = to %3D to %253D,
    // and then decoded to %3D instead of =.
    $session_assert->pageTextNotContains('is not allowed');
    $session_assert->pageTextNotContains('%3D');

    // Also verify no general form errors are present.
    $session_assert->elementNotExists('css', '.messages--error');
  }

  /**
   * Capture the page state.
   */
  private function captureAfterRefresh(): void {
    if ($this->htmlOutputEnabled && !$this->isTestUsingGuzzleClient()) {
      $html_output = 'Refreshed view: ' . $this->getSession()->getCurrentUrl()
        . '<hr />' . $this->getSession()->getPage()->getContent()
        . $this->getHtmlOutputHeaders();
      $this->htmlOutput($html_output);
    }
  }

}
