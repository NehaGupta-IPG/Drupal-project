<?php

namespace Drupal\views\Plugin\views\pager;

/**
 * Provides an interface for Views pager plugins.
 */
interface PagerPluginInterface {

  /**
   * Gets the current page.
   *
   * @return mixed
   *   The current page number, or NULL if not yet set.
   */
  public function getCurrentPage();

  /**
   * Sets the current page.
   *
   * @param mixed $number
   *   The page number to set.
   */
  public function setCurrentPage($number = NULL);

  /**
   * Gets how many items per page this pager will display.
   *
   * @return int
   *   The number of items per page.
   */
  public function getItemsPerPage();

  /**
   * Sets how many items per page this pager will display.
   *
   * @param int $items
   *   The number of items per page.
   */
  public function setItemsPerPage($items);

  /**
   * Gets the page offset, or how many items to skip.
   *
   * @return int
   *   The offset.
   */
  public function getOffset();

  /**
   * Sets the page offset, or how many items to skip.
   *
   * @param int $offset
   *   The offset.
   */
  public function setOffset($offset);

  /**
   * Determines if this pager actually uses a pager.
   *
   * @return bool
   *   TRUE if this pager uses a pager, FALSE otherwise.
   */
  public function usePager();

  /**
   * Returns the renderable array of the pager.
   *
   * @param array $input
   *   Any extra GET parameters that should be retained, such as exposed input.
   */
  public function render($input);

  /**
   * Updates the pager information after a query is run.
   */
  public function updatePageInfo();

  /**
   * Performs any needed actions just before rendering.
   *
   * @param array $result
   *   The view result array, passed by reference.
   */
  public function preRender(&$result);

}
