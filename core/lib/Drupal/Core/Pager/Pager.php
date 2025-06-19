<?php

namespace Drupal\Core\Pager;

/**
 * A value object that represents a pager.
 */
readonly class Pager {

  /**
   * The total number of pages.
   */
  public int $totalPages;

  /**
   * The current page of the pager.
   */
  public int $currentPage;

  /**
   * Pager constructor.
   *
   * @param int $totalItems
   *   The total number of items.
   * @param int $limit
   *   The maximum number of items per page.
   * @param int $currentPage
   *   The current page.
   */
  public function __construct(
    public int $totalItems,
    public int $limit,
    int $currentPage = 0,
  ) {
    $this->setTotalPages($totalItems, $limit);
    $this->setCurrentPage($currentPage);
  }

  /**
   * Sets the current page to a valid value within range.
   *
   * If a page that does not correspond to the actual range of the result set
   * was provided, this function will set the closest page actually within
   * the result set.
   *
   * @param int $currentPage
   *   (optional) The current page.
   */
  protected function setCurrentPage($currentPage = 0) {
    $this->currentPage = max(0, min($currentPage, $this->totalPages - 1));
  }

  /**
   * Sets the total number of pages.
   *
   * @param int $totalItems
   *   The total number of items.
   * @param int $limit
   *   The maximum number of items per page.
   */
  protected function setTotalPages($totalItems, $limit) {
    $this->totalPages = (int) ceil($totalItems / $limit);
  }

  /**
   * Gets the total number of items.
   *
   * @return int
   *   The total number of items.
   */
  public function getTotalItems() {
    @\trigger_error('@todo deprecation message', E_USER_DEPRECATED);
    return $this->totalItems;
  }

  /**
   * Gets the total number of pages.
   *
   * @return int
   *   The total number of pages.
   */
  public function getTotalPages() {
    @\trigger_error('@todo deprecation message', E_USER_DEPRECATED);
    return $this->totalPages;
  }

  /**
   * Gets the current page.
   *
   * @return int
   *   The current page.
   */
  public function getCurrentPage() {
    @\trigger_error('@todo deprecation message', E_USER_DEPRECATED);
    return $this->currentPage;
  }

  /**
   * Gets the maximum number of items per page.
   *
   * @return int
   *   The maximum number of items per page.
   */
  public function getLimit() {
    @\trigger_error('@todo deprecation message', E_USER_DEPRECATED);
    return $this->limit;
  }

}
