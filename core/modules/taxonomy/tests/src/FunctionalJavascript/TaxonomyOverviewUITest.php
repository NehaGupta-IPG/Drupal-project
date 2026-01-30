<?php

declare(strict_types=1);

namespace Drupal\Tests\taxonomy\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Tests the Taxonomy Overview form.
 *
 * @group taxonomy
 */
class TaxonomyOverviewUITest extends WebDriverTestBase {

  use TaxonomyTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['taxonomy'];

  /**
   * Vocabulary for testing.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected $vocabulary;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalLogin($this->drupalCreateUser([
      'administer taxonomy',
    ]));
    $this->vocabulary = $this->createVocabulary();

    // Create terms with special names. They are easily alphabetized. The number
    // matches the tid. It is intentional that the alphabetical order does not
    // match the numerical order so that things are less likely to work by
    // coincidence.
    $this->createTerm($this->vocabulary, ['name' => 'Bravo 1']);
    $this->createTerm($this->vocabulary, ['name' => 'Alpha 2']);
    $this->createTerm($this->vocabulary, ['name' => 'Delta 3']);
    $this->createTerm($this->vocabulary, ['name' => 'Charlie 4']);
  }

  /**
   * Tests re-ordering terms using the form.
   */
  public function testTermReorder(): void {
    $this->drupalGet('admin/structure/taxonomy/manage/' . $this->vocabulary->id() . '/overview');
    $session = $this->getSession();
    $page = $session->getPage();

    // Original order should be alphabetical, "Alpha 2, Bravo 1, Charlie 4,
    // Delta 3".
    $this->assertOrderOnForm('Alpha 2', 'Bravo 1', 'Charlie 4', 'Delta 3');
    // Drag first row, 'Alpha 2', to the bottom row. Drag to handle to avoid
    // nesting.
    $first_handle = $page->find('css', '#taxonomy tbody tr:nth-child(1) .tabledrag-handle');
    $last_handle = $page->find('css', '#taxonomy tbody tr:nth-child(4) .tabledrag-handle');
    $first_handle->dragTo($last_handle);
    $page->pressButton('Save');

    // Form should reload with this order, "Bravo 1, Charlie 4, Delta 3, Alpha
    // 2".
    $this->assertOrderOnForm('Bravo 1', 'Charlie 4', 'Delta 3', 'Alpha 2');
    $this->assertOrderInTree('Bravo 1', 'Charlie 4', 'Delta 3', 'Alpha 2');

    // Move 'Charlie 4' to the top and 'Delta 3' to the bottom.
    $charlie_handle = $page->find('css', '#taxonomy tbody tr:nth-child(2) .tabledrag-handle');
    $first_handle = $page->find('css', '#taxonomy tbody tr:nth-child(1) .tabledrag-handle');
    $charlie_handle->dragTo($first_handle);
    $delta_handle = $page->find('css', '#taxonomy tbody tr:nth-child(3) .tabledrag-handle');
    $last_handle = $page->find('css', '#taxonomy tbody tr:nth-child(4) .tabledrag-handle');
    $delta_handle->dragTo($last_handle);
    $page->pressButton('Save');

    // Form should reload with this order, "Charlie 4, Bravo 1, Alpha 2, Delta
    // 3".
    $this->assertOrderOnForm('Charlie 4', 'Bravo 1', 'Alpha 2', 'Delta 3');
    $this->assertOrderInTree('Charlie 4', 'Bravo 1', 'Alpha 2', 'Delta 3');

    // Reset to alphabetical order.
    $this->submitForm([], 'Reset to alphabetical');
    // Submit confirmation form.
    $this->submitForm([], 'Reset to alphabetical');
    // Ensure form redirected back to overview.
    $this->assertSession()->addressEquals('admin/structure/taxonomy/manage/' . $this->vocabulary->id() . '/overview');
    // Should be alphabetical, "Alpha 2, Bravo 1, Charlie 4, Delta 3".
    $this->assertOrderOnForm('Alpha 2', 'Bravo 1', 'Charlie 4', 'Delta 3');
    $this->assertWeightOnForm('0', '0', '0', '0');
    $this->assertOrderInTree('Alpha 2', 'Bravo 1', 'Charlie 4', 'Delta 3');

    // Save and confirm order does not change on form or storage. Nothing has
    // been dragged so it should still be alphabetical, "Alpha 2, Bravo 1,
    // Charlie 4, Delta 3". Weights should get updated to match form.
    $page->pressButton('Save');
    $this->assertOrderOnForm('Alpha 2', 'Bravo 1', 'Charlie 4', 'Delta 3');
    $this->assertWeightOnForm('0', '1', '2', '3');
    $this->assertOrderInTree('Alpha 2', 'Bravo 1', 'Charlie 4', 'Delta 3');

    // Confirm that dragging works after alphabetization. Drag first row, "Alpha
    // 2", to the bottom row.
    $first_handle = $page->find('css', '#taxonomy tbody tr:nth-child(1) .tabledrag-handle');
    $last_handle = $page->find('css', '#taxonomy tbody tr:nth-child(4) .tabledrag-handle');
    $first_handle->dragTo($last_handle);
    $page->pressButton('Save');

    // Form should reload with this order, "Bravo 1, Charlie 4, Delta 3, Alpha
    // 2".
    $this->assertOrderOnForm('Bravo 1', 'Charlie 4', 'Delta 3', 'Alpha 2');
    $this->assertOrderInTree('Bravo 1', 'Charlie 4', 'Delta 3', 'Alpha 2');
  }

  /**
   * Tests nesting and moving terms using the form.
   */
  public function testTermNesting(): void {
    $this->drupalGet('admin/structure/taxonomy/manage/' . $this->vocabulary->id() . '/overview');
    $session = $this->getSession();
    $page = $session->getPage();

    // Original order should be alphabetical with no nesting, "Alpha 2, Bravo 1,
    // Charlie 4, Delta 3".
    $this->assertSession()->pageTextNotContains('contains terms grouped under parent terms');
    $this->assertOrderOnForm('Alpha 2', 'Bravo 1', 'Charlie 4', 'Delta 3');
    // Nest "Bravo 1" under "Alpha 2".
    $bravo_handle = $page->find('css', '#taxonomy tbody tr:nth-child(2) .tabledrag-handle');
    $bravo_name = $page->find('css', '#taxonomy tbody tr:nth-child(2) a[id^="edit-terms"]');
    $bravo_handle->dragTo($bravo_name);
    $page->pressButton('Save');

    // Confirm form has correct order and indentation.
    $this->assertOrderOnForm('Alpha 2', 'Bravo 1', 'Charlie 4', 'Delta 3');
    $this->assertIndentationOnForm(0, 1, 0, 0);
    $this->assertOrderInTree('Alpha 2', 'Bravo 1', 'Charlie 4', 'Delta 3');
    $this->assertParentsInTree('0', '2', '0', '0');
    // We should have a nesting message.
    $this->assertSession()->pageTextContains('contains terms grouped under parent terms');

    // Drag "Alpha 2" to the bottom.
    $alpha_handle = $page->find('css', '#taxonomy tbody tr:nth-child(1) .tabledrag-handle');
    $last_handle = $page->find('css', '#taxonomy tbody tr:nth-child(4) .tabledrag-handle');
    $alpha_handle->dragTo($last_handle);
    $page->pressButton('Save');

    // "Bravo 1" should still be nested under "Alpha 2" at the bottom of list.
    $this->assertOrderOnForm('Charlie 4', 'Delta 3', 'Alpha 2', 'Bravo 1');
    $this->assertIndentationOnForm(0, 0, 0, 1);
    $this->assertOrderInTree('Charlie 4', 'Delta 3', 'Alpha 2', 'Bravo 1');
    $this->assertParentsInTree('0', '0', '0', '2');

    // Nest "Delta 3" under "Alpha 2" but before "Bravo 1".
    $delta_handle = $page->find('css', '#taxonomy tbody tr:nth-child(2) .tabledrag-handle');
    $alpha_handle = $page->find('css', '#taxonomy tbody tr:nth-child(3) .tabledrag-handle');
    $delta_handle->dragTo($alpha_handle);
    $page->pressButton('Save');

    // Confirm order and nesting.
    $this->assertOrderOnForm('Charlie 4', 'Alpha 2', 'Delta 3', 'Bravo 1');
    $this->assertIndentationOnForm(0, 0, 1, 1);
    $this->assertOrderInTree('Charlie 4', 'Alpha 2', 'Delta 3', 'Bravo 1');
    $this->assertParentsInTree('0', '0', '2', '2');

    // Reset to alphabetical order.
    $this->submitForm([], 'Reset to alphabetical');
    // Submit confirmation form.
    $this->submitForm([], 'Reset to alphabetical');
    // Ensure form redirected back to overview.
    $this->assertSession()->addressEquals('admin/structure/taxonomy/manage/' . $this->vocabulary->id() . '/overview');
    // Should be alphabetical within nesting structure, "Alpha 2, Bravo 1, Delta
    // 3, Charlie 4".
    $this->assertOrderOnForm('Alpha 2', 'Bravo 1', 'Delta 3', 'Charlie 4');
    $this->assertIndentationOnForm(0, 1, 1, 0);
    $this->assertWeightOnForm('0', '0', '0', '0');
    $this->assertOrderInTree('Alpha 2', 'Bravo 1', 'Delta 3', 'Charlie 4');
    $this->assertParentsInTree('0', '2', '2', '0');

    // Save and confirm order does not change on form or storage. Nothing has
    // been dragged so it should still be (nested) alphabetical. Weights should
    // get updated to match form, "Alpha 2, Bravo 1, Delta 3, Charlie 4".
    $page->pressButton('Save');
    $this->assertOrderOnForm('Alpha 2', 'Bravo 1', 'Delta 3', 'Charlie 4');
    $this->assertWeightOnForm('0', '0', '1', '3');
  }

  /**
   * Asserts the order of terms on the Overview Form UI.
   *
   * @param string $first
   *   Name of term expected in first row.
   * @param string $second
   *   Name of term expected in second row.
   * @param string $third
   *   Name of term expected in third row.
   * @param string $fourth
   *   Name of term expected in fourth row.
   */
  protected function assertOrderOnForm(string $first, string $second, string $third, string $fourth): void {
    $page = $this->getSession()->getPage();
    $this->assertSame($first, $page->find('css', '#taxonomy tbody tr:nth-child(1) a[id^="edit-terms"]')->getText());
    $this->assertSame($second, $page->find('css', '#taxonomy tbody tr:nth-child(2) a[id^="edit-terms"]')->getText());
    $this->assertSame($third, $page->find('css', '#taxonomy tbody tr:nth-child(3) a[id^="edit-terms"]')->getText());
    $this->assertSame($fourth, $page->find('css', '#taxonomy tbody tr:nth-child(4) a[id^="edit-terms"]')->getText());
  }

  /**
   * Assert the term weights on the Overview Form UI.
   *
   * @param string $first
   *   Weight of term expected in first row.
   * @param string $second
   *   Weight of term expected in second row.
   * @param string $third
   *   Weight of term expected in third row.
   * @param string $fourth
   *   Weight of term expected in fourth row.
   */
  protected function assertWeightOnForm(string $first, string $second, string $third, string $fourth): void {
    $page = $this->getSession()->getPage();
    $this->assertSame($first, $page->find('css', '#taxonomy tbody tr:nth-child(1) .term-weight')->getValue());
    $this->assertSame($second, $page->find('css', '#taxonomy tbody tr:nth-child(2) .term-weight')->getValue());
    $this->assertSame($third, $page->find('css', '#taxonomy tbody tr:nth-child(3) .term-weight')->getValue());
    $this->assertSame($fourth, $page->find('css', '#taxonomy tbody tr:nth-child(4) .term-weight')->getValue());
  }

  /**
   * Asserts the indentation on the Overview Form UI.
   *
   * @param int $first
   *   Indentation expected in first row.
   * @param int $second
   *   Indentation expected in second row.
   * @param int $third
   *   Indentation expected in third row.
   * @param int $fourth
   *   Indentation expected in fourth row.
   */
  protected function assertIndentationOnForm(int $first, int $second, int $third, int $fourth): void {
    $page = $this->getSession()->getPage();
    $this->assertCount($first, $page->findAll('css', '#taxonomy tbody tr:nth-child(1) .indentation'));
    $this->assertCount($second, $page->findAll('css', '#taxonomy tbody tr:nth-child(2) .indentation'));
    $this->assertCount($third, $page->findAll('css', '#taxonomy tbody tr:nth-child(3) .indentation'));
    $this->assertCount($fourth, $page->findAll('css', '#taxonomy tbody tr:nth-child(4) .indentation'));
  }

  /**
   * Asserts the order of the loaded tree.
   *
   * @param string $first
   *   Name of term expected first.
   * @param string $second
   *   Name of term expected second.
   * @param string $third
   *   Name of term expected third.
   * @param string $fourth
   *   Name of term expected fourth.
   */
  protected function assertOrderInTree(string $first, string $second, string $third, string $fourth): void {
    $taxonomy_storage = $this->container->get('entity_type.manager')->getStorage('taxonomy_term');
    $taxonomy_storage->resetCache();
    $tree = $taxonomy_storage->loadTree($this->vocabulary->id(), 0, NULL, TRUE);
    $this->assertSame($first, $tree[0]->getName());
    $this->assertSame($second, $tree[1]->getName());
    $this->assertSame($third, $tree[2]->getName());
    $this->assertSame($fourth, $tree[3]->getName());
  }

  /**
   * Asserts the parents in a loaded tree.
   *
   * @param string $first
   *   TID of term expected as parent of first term.
   * @param string $second
   *   TID of term expected as parent of second term.
   * @param string $third
   *   TID of term expected as parent of third term.
   * @param string $fourth
   *   TID of term expected as parent of fourth term.
   */
  protected function assertParentsInTree(string $first, string $second, string $third, string $fourth): void {
    $taxonomy_storage = $this->container->get('entity_type.manager')->getStorage('taxonomy_term');
    $taxonomy_storage->resetCache();
    $tree = $taxonomy_storage->loadTree($this->vocabulary->id(), 0, NULL, TRUE);
    $this->assertSame($first, $tree[0]->parents[0]);
    $this->assertSame($second, $tree[1]->parents[0]);
    $this->assertSame($third, $tree[2]->parents[0]);
    $this->assertSame($fourth, $tree[3]->parents[0]);
  }

}
