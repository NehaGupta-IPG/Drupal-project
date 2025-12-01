<?php

namespace Drupal\Tests\system\Functional\Render;

/**
 * Provides test assertions for testing page head tags.
 *
 * Can be used by test classes that extend \Drupal\Tests\BrowserTestBase.
 */
trait AssertPageHeadTrait {

  /**
   * Helper function to make assertions about HTML head elements.
   *
   * @param string $headTag
   *   The tag string to use in the search.
   * @param array $attributes
   *   Get attribute of the DOM element.
   * @param int $count
   *   Expected count of the search.
   * @param string $boolOperator
   *   (optional) Determine the operator between attributes.
   *   Allowed values: 'and', 'or'
   *   Default value - and.
   *
   *   Example:
   *      Input data:
   *        - headTag = 'link'
   *        - attributes = ['rel' => 'icon', 'as' => 'image']
   *        - count = 1
   *        - boolOperator = 'and'
   *      xpath result: '//head/link[@rel = "icon" and @as = "image"]'.
   *
   * @internal
   */
  protected function assertPageHead(string $headTag, array $attributes, int $count, string $boolOperator = 'and'): void {
    $xpath = '//head/' . $headTag;
    $selectors = [];
    foreach ($attributes as $attribute => $value) {
      $selectors[] = '@' . $attribute . '="' . $value . '"';
    }
    if ($selectors) {
      $xpath .= '[' . implode(" $boolOperator ", $selectors) . ']';
    }

    $this->assertSession()->elementsCount('xpath', $xpath, $count);
  }

}
