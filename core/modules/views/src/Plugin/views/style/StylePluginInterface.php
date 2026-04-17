<?php

namespace Drupal\views\Plugin\views\style;

use Drupal\views\Plugin\views\ViewsPluginInterface;

/**
 * Provides an interface for Views style plugins.
 */
interface StylePluginInterface extends ViewsPluginInterface {

  /**
   * Returns TRUE if this style also uses fields.
   *
   * @return bool
   *   TRUE if fields are used, FALSE otherwise.
   */
  public function usesFields();

  /**
   * Sorts the view's fields based on the style's criteria.
   *
   * @return bool
   *   TRUE if the default sort should still be applied, FALSE otherwise.
   */
  public function buildSort();

  /**
   * Allows the style to add a second pass of sorting after the main sort.
   */
  public function buildSortPost();

  /**
   * Allows the style to do processing before each row is rendered.
   *
   * @param array $result
   *   The full array of results from the query.
   */
  public function preRender($result);

  /**
   * Takes a value and applies token replacement logic to it.
   *
   * @param string $value
   *   The string to tokenize.
   * @param int $row_index
   *   The index of the current row being rendered.
   *
   * @return string
   *   The tokenized string.
   */
  public function tokenizeValue($value, $row_index);

}
