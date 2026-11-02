<?php

declare(strict_types=1);

namespace Drupal\newsroom;

use Drupal\Component\Datetime\TimeInterface;

/**
 * Decides when articles need to be reviewed by an editor.
 */
class ReviewPolicy {

  /**
   * The time after which a regular article needs a review, in seconds.
   */
  public const int REVIEW_INTERVAL = 90 * 86400;

  /**
   * The time after which an evergreen article needs a review, in seconds.
   */
  public const int EVERGREEN_REVIEW_INTERVAL = 365 * 86400;

  public function __construct(
    protected readonly TimeInterface $time,
  ) {}

  /**
   * Returns when the specified article is due for review.
   *
   * @param \Drupal\newsroom\ArticleInterface $article
   *   The article.
   *
   * @return int
   *   A Unix timestamp.
   */
  public function getReviewDueTime(ArticleInterface $article): int {
    $interval = $article->isEvergreen() ? static::EVERGREEN_REVIEW_INTERVAL : static::REVIEW_INTERVAL;
    $reference = $article->getLastReviewTime() ?? $article->getPublicationTime();
    return $reference + $interval;
  }

  /**
   * Checks whether the specified article is due for review.
   *
   * @param \Drupal\newsroom\ArticleInterface $article
   *   The article.
   *
   * @return bool
   *   TRUE if the article needs a review, FALSE otherwise.
   */
  public function needsReview(ArticleInterface $article): bool {
    return $this->getReviewDueTime($article) <= $this->time->getCurrentTime();
  }

  /**
   * Returns the specified articles that are due for review.
   *
   * @param iterable<\Drupal\newsroom\ArticleInterface> $articles
   *   The articles to check.
   *
   * @return list<\Drupal\newsroom\ArticleInterface>
   *   The articles needing a review, most overdue first.
   */
  public function getReviewQueue(iterable $articles): array {
    $queue = [];
    foreach ($articles as $article) {
      if ($this->needsReview($article)) {
        $queue[] = $article;
      }
    }
    usort($queue, fn (ArticleInterface $a, ArticleInterface $b): int => $this->getReviewDueTime($a) <=> $this->getReviewDueTime($b));
    return $queue;
  }

}
