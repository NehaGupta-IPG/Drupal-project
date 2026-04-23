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
 * Tests that AJAX view refresh Drupal.Ajax instances do not leak.
 *
 * @see https://www.drupal.org/project/drupal/issues/2771361
 */
#[Group('views')]
#[RunTestsInSeparateProcesses]
class AjaxViewRefreshInstancesTest extends WebDriverTestBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'views', 'views_test_config'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The views to use for testing.
   *
   * @var string[]
   */
  public static $testViews = ['test_content_ajax'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    ViewTestData::createTestViews(self::class, ['views_test_config']);

    $this->createContentType(['type' => 'page']);
    for ($i = 1; $i <= 11; $i++) {
      $this->createNode([
        'title' => 'Node ' . $i,
        'changed' => $i * 1000,
      ]);
    }

    $user = $this->drupalCreateUser([
      'administer site configuration',
      'access content',
      'access content overview',
    ]);
    $this->drupalLogin($user);
  }

  /**
   * Tests that refresh ajax instances do not leak on pagination.
   */
  public function testRefreshAjaxDoesNotLeak(): void {
    $this->drupalGet('test-content-ajax');

    $session_assert = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Switch to 5 items per page so the pager is active.
    $page->selectFieldOption('edit-items-per-page', '5');
    $page->pressButton('Filter');
    $session_assert->assertWaitOnAjaxRequest();

    // Count script: returns the number of non-null Drupal.Ajax instances
    // whose event is 'RefreshView'. The refresh ajax is bound to the
    // .js-view-dom-id-<id> element and only a single active instance is
    // expected per AJAX view on the page, regardless of how many times the
    // view has been refreshed.
    $count_script = <<<'JS'
      (function () {
        return Drupal.ajax.instances.filter(function (instance) {
          return instance && instance.elementSettings && instance.elementSettings.event === 'RefreshView';
        }).length;
      })();
      JS;

    // After initial load there should be exactly one refresh ajax instance.
    $initial = (int) $this->getSession()->evaluateScript($count_script);
    $this->assertSame(1, $initial, 'Exactly one RefreshView Drupal.Ajax instance exists on initial render.');

    // Paginate a few times. With the bug each pagination leaks one refresh
    // Drupal.Ajax instance (the old one is orphaned but never nulled).
    $this->clickLink('Go to page 2');
    $session_assert->assertWaitOnAjaxRequest();
    $this->clickLink('Go to page 3');
    $session_assert->assertWaitOnAjaxRequest();
    $this->clickLink('Go to first page');
    $session_assert->assertWaitOnAjaxRequest();

    $after = (int) $this->getSession()->evaluateScript($count_script);
    $this->assertSame(1, $after, 'Refresh Drupal.Ajax instances do not accumulate after paginating an AJAX view.');
  }

}
