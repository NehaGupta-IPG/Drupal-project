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
 * Tests using AJAX on some elements of a view only.
 *
 * @see \Drupal\views\Plugin\views\display\DisplayPluginInterface::getAjaxOptions()
 */
#[Group('views')]
#[RunTestsInSeparateProcesses]
class AjaxOptionsTest extends WebDriverTestBase {

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

    // Create a Content type and two test nodes. The view is sorted by changed
    // timestamp by default, so "Page B" is listed first.
    $this->createContentType(['type' => 'page']);
    $this->createNode(['title' => 'Page A', 'changed' => \Drupal::time()->getRequestTime()]);
    $this->createNode(['title' => 'Page B', 'changed' => \Drupal::time()->getRequestTime() + 1000]);

    $user = $this->drupalCreateUser([
      'access content',
      'access content overview',
    ]);
    $this->drupalLogin($user);
  }

  /**
   * Tests a view using AJAX on the pager only.
   */
  public function testAjaxOnPagerOnly(): void {
    $this->drupalGet('test-content-ajax-pager-only');
    $this->assertSettings('page_3', [
      'pager' => TRUE,
      'sorting' => FALSE,
      'exposed_form' => FALSE,
    ]);
    $this->assertRows(['Page B']);

    // The pager uses AJAX.
    $this->markPage();
    $this->getSession()->getPage()->find('css', '.pager__item--next a')->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertRows(['Page A']);
    $this->assertFalse($this->isPageReloaded());

    // Table click sorting reloads the page.
    $this->drupalGet('test-content-ajax-pager-only');
    $this->markPage();
    $this->getSession()->getPage()->clickLink('Title');
    $this->assertPageReloaded();
    $this->assertRows(['Page A']);

    // The exposed form reloads the page.
    $this->markPage();
    $this->submitForm(['title' => 'Page B'], 'Filter');
    $this->assertPageReloaded();
    $this->assertRows(['Page B']);
  }

  /**
   * Tests a view using AJAX on table click sorting and the exposed form only.
   */
  public function testAjaxOnSortingAndExposedForm(): void {
    $this->drupalGet('test-content-ajax-no-pager');
    $this->assertSettings('page_4', [
      'pager' => FALSE,
      'sorting' => TRUE,
      'exposed_form' => TRUE,
    ]);
    $this->assertRows(['Page B']);

    // The pager reloads the page.
    $this->markPage();
    $this->getSession()->getPage()->find('css', '.pager__item--next a')->click();
    $this->assertPageReloaded();
    $this->assertRows(['Page A']);

    // Table click sorting uses AJAX.
    $this->drupalGet('test-content-ajax-no-pager');
    $this->markPage();
    $this->getSession()->getPage()->clickLink('Title');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertRows(['Page A']);
    $this->assertFalse($this->isPageReloaded());

    // The exposed form uses AJAX.
    $this->submitForm(['title' => 'Page B'], 'Filter');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertRows(['Page B']);
    $this->assertFalse($this->isPageReloaded());
  }

  /**
   * Asserts the AJAX options of the view in drupalSettings.
   *
   * @param string $display_id
   *   The ID of the display on the page.
   * @param bool[] $expected
   *   The expected AJAX options.
   */
  protected function assertSettings(string $display_id, array $expected): void {
    $settings = $this->getDrupalSettings()['views'];
    $this->assertCount(1, $settings['ajaxViews']);
    $key = array_key_first($settings['ajaxViews']);
    $this->assertSame($display_id, $settings['ajaxViews'][$key]['view_display_id']);
    $this->assertEquals($expected, $settings['ajaxOptions'][$key]);
  }

  /**
   * Asserts the titles of the rows displayed by the view.
   *
   * @param string[] $titles
   *   The expected titles.
   */
  protected function assertRows(array $titles): void {
    $rows = $this->getSession()->getPage()->findAll('css', 'tbody tr td.views-field-title');
    $this->assertSame($titles, array_map(fn ($row) => trim($row->getText()), $rows));
  }

  /**
   * Sets a marker on the current page, which is lost when the page reloads.
   */
  protected function markPage(): void {
    $this->getSession()->executeScript('window.viewsAjaxOptionsTestMarker = true;');
  }

  /**
   * Checks whether the page has been reloaded since it was marked.
   *
   * @return bool
   *   TRUE if the page has been reloaded, FALSE otherwise.
   */
  protected function isPageReloaded(): bool {
    return $this->getSession()->evaluateScript('typeof window.viewsAjaxOptionsTestMarker === "undefined"');
  }

  /**
   * Waits for the page to be reloaded since it was marked.
   */
  protected function assertPageReloaded(): void {
    $this->assertTrue($this->getSession()->wait(10000, 'typeof window.viewsAjaxOptionsTestMarker === "undefined" && document.readyState === "complete"'), 'The page has been reloaded.');
  }

}
