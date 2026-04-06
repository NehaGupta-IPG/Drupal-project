<?php

declare(strict_types=1);

namespace Drupal\Tests\update\Traits;

use Behat\Mink\Element\NodeElement;

/**
 * Provides methods for checking update module output.
 */
trait UpdateTestAssertionsTrait {

  /**
   * Finds an update page element by label.
   *
   * @param string $label
   *   The label for the update, for example "Recommended version:" or
   *   "Latest version:".
   * @param int $index
   *   (optional) The index of the element.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The update element.
   */
  protected function findUpdateElementByLabel($label, int $index = 0): NodeElement {
    $update_elements = $this->getSession()->getPage()
      ->findAll('css', $this->updateTableLocator . " .project-update__version:contains(\"$label\")");
    $this->assertGreaterThanOrEqual($index, count($update_elements));
    return $update_elements[$index];
  }

  /**
   * Asserts that the update table text contains the specified text.
   *
   * @param string $text
   *   The expected text.
   *
   * @see \Behat\Mink\WebAssert::elementTextContains()
   */
  protected function assertUpdateTableTextContains(string $text) {
    $this->assertSession()
      ->elementTextContains('css', $this->updateTableLocator, $text);
  }

  /**
   * Asserts that the update table text does not contain the specified text.
   *
   * @param string $text
   *   The expected text.
   */
  protected function assertUpdateTableTextNotContains(string $text) {
    $this->assertSession()->elementTextNotContains('css', $this->updateTableLocator, $text);
  }

  /**
   * Asserts that the update table element HTML contains the specified text.
   *
   * @param string $text
   *   The expected text.
   *
   * @see \Behat\Mink\WebAssert::elementContains()
   */
  protected function assertUpdateTableElementContains(string $text) {
    $this->assertSession()
      ->elementContains('css', $this->updateTableLocator, $text);
  }

}
