<?php

declare(strict_types=1);

namespace Drupal\newsroom\Repository;

/**
 * Retrieves published articles.
 *
 * Access checking is the responsibility of the caller.
 */
interface ArticleRepositoryInterface {

  /**
   * Returns the IDs of the most recent articles.
   *
   * @param int $limit
   *   The maximum number of IDs to return.
   *
   * @return array<int|string, int|string>
   *   The article IDs, most recent first.
   */
  public function getLatestIds(int $limit): array;

  /**
   * Returns the most recent articles.
   *
   * @param int $limit
   *   The maximum number of articles to return.
   *
   * @return array<int|string, \Drupal\newsroom\ArticleInterface>
   *   The articles keyed by ID, most recent first.
   */
  public function getLatest(int $limit): array;

  /**
   * Returns the IDs of evergreen articles.
   *
   * @return array<int|string, int|string>
   *   The article IDs.
   */
  public function getEvergreenIds(): array;

  /**
   * Returns evergreen articles.
   *
   * @return array<int|string, \Drupal\newsroom\ArticleInterface>
   *   The articles keyed by ID.
   */
  public function getEvergreen(): array;

  /**
   * Returns the IDs of articles not reviewed since the specified time.
   *
   * Articles that were never reviewed are matched on their publication time.
   *
   * @param int $time
   *   A Unix timestamp.
   *
   * @return array<int|string, int|string>
   *   The article IDs.
   */
  public function getNotReviewedSinceIds(int $time): array;

  /**
   * Returns articles not reviewed since the specified time.
   *
   * Articles that were never reviewed are matched on their publication time.
   *
   * @param int $time
   *   A Unix timestamp.
   *
   * @return array<int|string, \Drupal\newsroom\ArticleInterface>
   *   The articles keyed by ID.
   */
  public function getNotReviewedSince(int $time): array;

}
