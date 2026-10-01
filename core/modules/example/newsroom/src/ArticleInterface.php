<?php

declare(strict_types=1);

namespace Drupal\newsroom;

/**
 * A newsroom article.
 */
interface ArticleInterface {

  /**
   * Returns the article headline.
   *
   * @return string
   *   The headline.
   */
  public function getHeadline(): string;

  /**
   * Returns when the article was published.
   *
   * @return int
   *   A Unix timestamp.
   */
  public function getPublicationTime(): int;

  /**
   * Returns when the article content was last reviewed by an editor.
   *
   * @return int|null
   *   A Unix timestamp, or NULL if the article was never reviewed.
   */
  public function getLastReviewTime(): ?int;

  /**
   * Checks whether the article content does not get outdated.
   *
   * @return bool
   *   TRUE if the article is evergreen, FALSE otherwise.
   */
  public function isEvergreen(): bool;

}
