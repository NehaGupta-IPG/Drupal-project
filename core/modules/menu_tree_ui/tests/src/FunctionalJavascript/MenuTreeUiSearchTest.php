<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\FunctionalJavascript;

use Drupal\menu_tree_ui_test\Hook\MenuTreeUiTestHooks;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the htmx-driven search popover on the menu tree page.
 *
 * Typing into the search field issues an htmx GET to the search route and
 * swaps the returned results into the results popover. Picking a result
 * expands its ancestors and focuses the matching row in the tree.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeUiSearchTest extends MenuTreeUiJavascriptTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'menu_link_content',
    'menu_tree_ui',
    'menu_tree_ui_test',
  ];

  /**
   * Seeds Apple, Banana and Cherry at top level, with Apricot under Banana.
   *
   * Three top-level rows keep the tree above the small-tree auto-expand
   * threshold, so Banana starts collapsed.
   */
  protected function seedMenu(): void {
    $this->createTestMenu();
    $this->linkIds['Apple'] = $this->createLink('Apple', '', 0);
    $this->linkIds['Banana'] = $this->createLink('Banana', '', 1);
    $this->linkIds['Cherry'] = $this->createLink('Cherry', '', 2);
    $this->linkIds['Apricot'] = $this->createLink('Apricot', $this->linkIds['Banana'], 0);
  }

  /**
   * Typing swaps results in, an empty query swaps them out, a result jumps.
   */
  public function testSearchSwapsResultsAndJumps(): void {
    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();

    $input = $page->find('css', '#menu-tree-ui-search-input');
    $this->assertNotNull($input);
    $assert->elementNotExists('css', '#menu-tree-ui-search-results > *');
    $this->assertNull($this->row('Apricot'), 'Apricot is not loaded while Banana is collapsed.');

    // A query with matches swaps the result list into the popover.
    $input->setValue('Ap');
    $assert->waitForElementVisible('css', '#menu-tree-ui-search-results .menu-tree-ui-search-results__list');
    $titles = array_map(
      static fn($element) => $element->getText(),
      $page->findAll('css', '.menu-tree-ui-search-results__title'),
    );
    sort($titles);
    $this->assertSame(['Apple', 'Apricot'], $titles);
    $assert->elementTextContains('css', '.menu-tree-ui-search-results__summary', '2 results for "Ap"');
    $assert->elementTextContains('css', '.menu-tree-ui-search-results__breadcrumb', 'Banana');

    // A query without matches swaps in the empty message.
    $input->setValue('zzz');
    $assert->waitForElementVisible('css', '.menu-tree-ui-search-results__empty');
    $assert->elementTextContains('css', '.menu-tree-ui-search-results__empty', 'No matches for "zzz"');

    // Clearing the field swaps in nothing, which closes the popover.
    $input->setValue('');
    $assert->waitForElementRemoved('css', '#menu-tree-ui-search-results > *');

    // Picking a result expands the collapsed parent and focuses the row.
    $input->setValue('Apricot');
    $assert->waitForElementVisible('css', '.menu-tree-ui-search-results__title');
    $page->find('css', '.menu-tree-ui-search-results__title')->click();
    $apricot_id = $this->linkIds['Apricot'];
    $assert->waitForElementVisible('css', sprintf('tr[role="row"][data-id="%s"]', $apricot_id));
    $this->assertSame('true', $this->row('Banana')->getAttribute('aria-expanded'));
    $focused_id = $this->getSession()->evaluateScript(
      'document.activeElement.closest("tr[role=\"row\"]")?.dataset.id ?? null',
    );
    $this->assertSame($apricot_id, $focused_id, 'The jumped-to row receives focus.');
  }

  /**
   * Picking a result while a row is held keeps the move and aims at the result.
   */
  public function testJumpWhileHoldingRowAimsAtResult(): void {
    $cherry_id = $this->linkIds['Cherry'];
    $this->getSession()->executeScript(<<<JS
      const row = document.querySelector('tr[role="row"][data-id="$cherry_id"]');
      row.tabIndex = 0;
      row.focus();
      row.dispatchEvent(new KeyboardEvent('keydown', { key: ' ', bubbles: true, cancelable: true }));
    JS);
    $this->assertSame('true', $this->row('Cherry')->getAttribute('aria-selected'));

    $page = $this->getSession()->getPage();
    $page->find('css', '#menu-tree-ui-search-input')->setValue('Apricot');
    $this->assertSession()->waitForElementVisible('css', '.menu-tree-ui-search-results__title');
    $page->find('css', '.menu-tree-ui-search-results__title')->click();
    $this->assertSession()->waitForElementVisible('css', sprintf('tr[role="row"][data-id="%s"]', $this->linkIds['Apricot']));

    $this->assertSame('true', $this->row('Cherry')->getAttribute('aria-selected'), 'The move stays active.');
    $this->assertJsCondition(sprintf(
      "document.querySelector('drupal-treegrid').getAttribute('data-treegrid-reorder-drop') === 'child:%s'",
      $this->linkIds['Banana'],
    ));
  }

  /**
   * Escape empties the results and keeps the typed query.
   */
  public function testEscapeEmptiesResultsAndKeepsQuery(): void {
    $this->searchFor('Ap');

    $this->getSession()->executeScript(<<<'JS'
      document.getElementById('menu-tree-ui-search-input').dispatchEvent(
        new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }),
      );
    JS);

    $this->assertResultsDismissedWithQuery('Ap');
  }

  /**
   * A click outside the search box empties the results.
   */
  public function testClickOutsideEmptiesResults(): void {
    $this->searchFor('Ap');

    $this->getSession()->executeScript(
      "document.body.dispatchEvent(new MouseEvent('click', { bubbles: true }));",
    );

    $this->assertResultsDismissedWithQuery('Ap');
  }

  /**
   * Activating a result empties the results.
   */
  public function testActivatingResultEmptiesResults(): void {
    $this->searchFor('Apricot');

    $this->getSession()->getPage()->find('css', '.menu-tree-ui-search-results__title')->click();

    $this->assertResultsDismissedWithQuery('Apricot');
  }

  /**
   * Clearing the input after results showed leaves the container empty.
   */
  public function testClearingTheInputEmptiesResults(): void {
    $this->searchFor('Ap');

    $this->getSession()->getPage()->find('css', '#menu-tree-ui-search-input')->setValue('');

    $this->assertResultsDismissedWithQuery('');
  }

  /**
   * A refused response empties the results and loads none of its assets.
   */
  public function testRefusedResponseEmptiesResultsAndLoadsNoAssets(): void {
    $this->searchFor('Ap');
    \Drupal::state()->set(MenuTreeUiTestHooks::FORBID_UPDATE_KEY, TRUE);

    $this->searchAgainAndWait('Ban');

    $this->assertNoForeignLibraryAndEmptyResults();
  }

  /**
   * The gate does not depend on the tree being on the page.
   */
  public function testGateWorksWithoutTheTree(): void {
    $this->searchFor('Ap');
    $this->getSession()->executeScript("document.querySelector('drupal-treegrid').remove();");
    \Drupal::state()->set(MenuTreeUiTestHooks::FORBID_UPDATE_KEY, TRUE);

    $this->searchAgainAndWait('Ban');

    $this->assertNoForeignLibraryAndEmptyResults();
  }

  /**
   * The gate still works after a search whose swap unloaded the container.
   */
  public function testGateSurvivesASearch(): void {
    $this->searchFor('Ap');
    $this->searchAgainAndWait('Ban');
    \Drupal::state()->set(MenuTreeUiTestHooks::FORBID_UPDATE_KEY, TRUE);

    $this->searchAgainAndWait('Che');

    $this->assertNoForeignLibraryAndEmptyResults();
  }

  /**
   * Types another query and waits for its request to finish.
   *
   * @param string $query
   *   The query to type.
   */
  private function searchAgainAndWait(string $query): void {
    $this->getSession()->executeScript(<<<'JS'
      window.searchRequestDone = false;
      document.addEventListener('htmx:finally:request', () => {
        window.searchRequestDone = true;
      }, { once: true });
    JS);
    $this->getSession()->getPage()->find('css', '#menu-tree-ui-search-input')->setValue($query);
    $this->assertJsCondition('window.searchRequestDone === true');
    // Assets of a swapped response load after its swap; give them the time.
    usleep(700000);
  }

  /**
   * Asserts the results container is empty and the foreign library never ran.
   */
  private function assertNoForeignLibraryAndEmptyResults(): void {
    $this->assertSame(0, $this->getSession()->evaluateScript(
      "return document.getElementById('menu-tree-ui-search-results').children.length;",
    ));
    $this->assertNull($this->getSession()->evaluateScript('return window.menuTreeUiTestForeignLibrary ?? null;'));
  }

  /**
   * Types a query and waits for its results to show.
   *
   * @param string $query
   *   The query to type.
   */
  private function searchFor(string $query): void {
    $this->getSession()->getPage()->find('css', '#menu-tree-ui-search-input')->setValue($query);
    $this->assertNotNull($this->assertSession()->waitForElementVisible('css', '.menu-tree-ui-search-results__title'));
  }

  /**
   * Asserts the results container has no children and the query is kept.
   *
   * @param string $query
   *   The query the input still holds.
   */
  private function assertResultsDismissedWithQuery(string $query): void {
    $this->assertSession()->waitForElementRemoved('css', '#menu-tree-ui-search-results > *');
    $this->assertSame(0, $this->getSession()->evaluateScript(
      "return document.getElementById('menu-tree-ui-search-results').children.length;",
    ));
    $this->assertSame($query, $this->getSession()->evaluateScript(
      "return document.getElementById('menu-tree-ui-search-input').value;",
    ));
  }

}
