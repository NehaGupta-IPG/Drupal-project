<?php

declare(strict_types=1);

namespace Drupal\FunctionalJavascriptTests\TableDrag;

use Behat\Mink\Element\NodeElement;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests draggable table with subgroups.
 *
 * @group javascript
 */
class TableDragSubgroupTest extends WebDriverTestBase {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['tabledrag_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->state = $this->container->get('state');
  }

  /**
   * Tests that dragging rows up and down in a sub-grouped table works.
   */
  public function testVerticalDragAndDrop() {
    $this->drupalGet('tabledrag_subgroup_test');

    $this->assertDraggableTable([
      ['id' => 1, 'group' => 'a', 'changed' => FALSE],
      ['id' => 2, 'group' => 'a', 'changed' => FALSE],
      ['id' => 3, 'group' => 'b', 'changed' => FALSE],
      ['id' => 4, 'group' => 'b', 'changed' => FALSE],
      ['id' => 5, 'group' => 'b', 'changed' => FALSE],
    ]);

    // Drag the first row into group B.
    $row1 = $this->findRowById(1);
    $row3 = $this->findRowById(3);
    $handle1 = $this->findDragHandle($row1);
    $handle3 = $this->findDragHandle($row3);

    $handle1->dragTo($handle3);
    $this->assertSession()->waitForText('You have unsaved changes');

    $this->assertDraggableTable([
      ['id' => 2, 'group' => 'a', 'changed' => FALSE],
      ['id' => 3, 'group' => 'b', 'changed' => FALSE],
      ['id' => 1, 'group' => 'b', 'changed' => TRUE],
      ['id' => 4, 'group' => 'b', 'changed' => FALSE],
      ['id' => 5, 'group' => 'b', 'changed' => FALSE],
    ]);

    // Drag row 4 from group B to the top of the table in group A.
    $row2 = $this->findRowById(2);
    $row4 = $this->findRowById(4);
    $handle2 = $this->findDragHandle($row2);
    $handle4 = $this->findDragHandle($row4);

    $handle4->dragTo($handle2);
    $this->waitForDragging();

    $this->assertDraggableTable([
      ['id' => 4, 'group' => 'a', 'changed' => TRUE],
      ['id' => 2, 'group' => 'a', 'changed' => FALSE],
      ['id' => 3, 'group' => 'b', 'changed' => FALSE],
      ['id' => 1, 'group' => 'b', 'changed' => TRUE],
      ['id' => 5, 'group' => 'b', 'changed' => FALSE],
    ]);
  }

  /**
   * Tests that dragging rows into a hierarchy in a sub-grouped table works.
   */
  public function testHierarchicalDragAndDrop() {
    $this->drupalGet('tabledrag_subgroup_test');

    $this->assertDraggableTable([
      ['id' => 1, 'group' => 'a', 'changed' => FALSE],
      ['id' => 2, 'group' => 'a', 'changed' => FALSE],
      ['id' => 3, 'group' => 'b', 'changed' => FALSE],
      ['id' => 4, 'group' => 'b', 'changed' => FALSE],
      ['id' => 5, 'group' => 'b', 'changed' => FALSE],
    ]);

    // Make row 1 a child of row 2.
    $row1 = $this->findRowById(1);
    $row2 = $this->findRowById(2);

    $this->dragToChild($row1, $row2, 'down');
    $this->assertSession()->waitForText('You have unsaved changes');

    $this->assertDraggableTable([
      ['id' => 2, 'group' => 'a', 'changed' => FALSE],
      ['id' => 1, 'group' => 'a', 'changed' => TRUE, 'parent' => 2, 'indent' => 1],
      ['id' => 3, 'group' => 'b', 'changed' => FALSE],
      ['id' => 4, 'group' => 'b', 'changed' => FALSE],
      ['id' => 5, 'group' => 'b', 'changed' => FALSE],
    ]);

    // Make a deeper hierarchy by moving 5 under 1.
    $row5 = $this->findRowById(5);
    $this->dragToChild($row5, $row1, 'up');
    $this->waitForDragging();

    $this->assertDraggableTable([
      ['id' => 2, 'group' => 'a', 'changed' => FALSE],
      ['id' => 1, 'group' => 'a', 'changed' => TRUE, 'parent' => 2, 'indent' => 1],
      ['id' => 5, 'group' => 'a', 'changed' => TRUE, 'parent' => 1, 'indent' => 2],
      ['id' => 3, 'group' => 'b', 'changed' => FALSE],
      ['id' => 4, 'group' => 'b', 'changed' => FALSE],
    ]);

    // Drag row 1 back to the top row.
    $handle2 = $this->findDragHandle($row2);
    $handle1 = $this->findDragHandle($row1);

    $handle1->dragTo($handle2);
    $this->waitForDragging();

    $this->assertDraggableTable([
      ['id' => 1, 'group' => 'a', 'changed' => TRUE],
      ['id' => 5, 'group' => 'a', 'changed' => TRUE, 'parent' => 1, 'indent' => 1],
      ['id' => 2, 'group' => 'a', 'changed' => FALSE],
      ['id' => 3, 'group' => 'b', 'changed' => FALSE],
      ['id' => 4, 'group' => 'b', 'changed' => FALSE],
    ]);
  }

  /**
   * Drag a row to the child of another row.
   *
   * @param \Behat\Mink\Element\NodeElement $child
   *   The row to drag into the child position;
   * @param \Behat\Mink\Element\NodeElement $parent
   *   The row that will be parent;
   * @param string $direction
   *   Pass 'up' if the child row is being dragged up; 'down' otherwise.
   */
  protected function dragToChild($child, $parent, $direction) {
    $child_handle = $this->findDragHandle($child);
    $parent_target = $parent->find('css', "[data-drop-target-child-$direction]");
    $child_handle->dragTo($parent_target);
  }

  /**
   * Waits for tabledrag dragging to finish.
   */
  protected function waitForDragging() {
    $this->assertSession()->assertNoElementAfterWait('css', '#tabledrag-test-table tr.drag');
  }

  /**
   * Finds a row in the test table by the row ID.
   *
   * @param string $id
   *   The ID of the row.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The row element.
   */
  protected function findRowById($id) {
    $xpath = "//table[@id='tabledrag-test-table']/tbody/tr[.//input[@name='table[$id][id]']]";
    $row = $this->getSession()->getPage()->find('xpath', $xpath);
    $this->assertNotEmpty($row);
    return $row;
  }

  /**
   * Finds a row drag handle from a containing element, eg. row.
   *
   * @param \Behat\Mink\Element\NodeElement $element
   *   The containing element, eg. row.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The drag handle element.
   */
  protected function findDragHandle($element) {
    $handle = $element->find('css', 'a.tabledrag-handle');
    $this->assertNotEmpty($handle);
    return $handle;
  }

  /**
   * Asserts the structure of the draggable test table.
   *
   * Although passing the exact weight in the structure is optional, the order
   * and number of rows is asserted regardless.
   *
   * @param array $structure
   *   The table structure. Each entry represents a row and consists of:
   *   - id: the expected value for the ID hidden field.
   *   - parent: the expected parent ID for the row.
   *   - indent: how many indents the row should have.
   *   - changed: whether or not the row should have been marked as changed.
   *   - weight: (optional) the expected row weight.
   */
  protected function assertDraggableTable(array $structure) {
    $rows = $this->getSession()->getPage()->findAll('xpath', '//table[@id="tabledrag-test-table"]/tbody/tr[contains(@class, "draggable")]');
    $this->assertCount(count($structure), $rows, "An unexpected number of draggable table rows was found.");

    foreach ($structure as $delta => $expected) {
      $parent = $expected['parent'] ?? '';
      $indent = $expected['indent'] ?? 0;
      $changed = $expected['changed'] ?? FALSE;
      $weight = $expected['weight'] ?? NULL;
      $this->assertTableRow($rows[$delta], $expected['id'], $expected['group'], $parent, $indent, $changed, $weight);
    }
  }

  /**
   * Asserts the values of a draggable row.
   *
   * @param \Behat\Mink\Element\NodeElement $row
   *   The row element to assert.
   * @param string $id
   *   The expected value for the ID hidden input of the row.
   * @param string $group
   *   The expected group ID.
   * @param string $parent
   *   The expected parent ID.
   * @param int $indent
   *   The expected indent of the row.
   * @param bool $changed
   *   Whether or not the row should have been marked as changed.
   * @param int|null $weight
   *   (optional) The expected weight of the row or NULL to not test the weight;
   *   defaults to NULL.
   */
  protected function assertTableRow(NodeElement $row, $id, $group, $parent = '', $indent = 0, $changed = FALSE, $weight = NULL) {
    // Assert that the row position is correct by checking that the id
    // corresponds.
    $this->assertSession()->hiddenFieldValueEquals("table[$id][id]", $id, $row);
    $this->assertSession()->hiddenFieldValueEquals("table[$id][parent]", $parent, $row);
    $this->assertSession()->hiddenFieldValueEquals("table[$id][group]", $group, $row);
    // $weight might be NULL
    if (!is_null($weight)) {
      $this->assertSession()->fieldValueEquals("table[$id][weight]", $weight, $row);
    }
    $this->assertSession()->elementsCount('css', '.js-indentation.indentation', $indent, $row);
    // A row is marked as changed when the related markup is present.
    $this->assertSession()->elementsCount('css', 'abbr.tabledrag-changed', (int) $changed, $row);
  }

}
