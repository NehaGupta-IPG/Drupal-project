<?php

declare(strict_types=1);

namespace Drupal\newsroom\Render;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\newsroom\ArticleInterface;
use Drupal\newsroom\Repository\ArticleRepositoryInterface;
use Drupal\newsroom\ReviewPolicy;

/**
 * Builds article render arrays.
 */
class ArticleViewBuilder {

  use StringTranslationTrait;

  public function __construct(
    protected readonly ArticleRepositoryInterface $articleRepository,
    protected readonly ReviewPolicy $reviewPolicy,
    protected readonly TimeInterface $time,
  ) {}

  /**
   * Builds a notice telling editors that the article is due for review.
   *
   * @param \Drupal\newsroom\ArticleInterface $article
   *   The article.
   *
   * @return array
   *   A render array.
   */
  public function buildReviewNotice(ArticleInterface $article): array {
    if (!$this->reviewPolicy->needsReview($article)) {
      // Make sure the notice appears as soon as the article becomes due.
      return [
        '#cache' => [
          'max-age' => $this->reviewPolicy->getReviewDueTime($article) - $this->time->getCurrentTime(),
        ],
      ];
    }

    return [
      '#theme' => 'status_messages',
      '#message_list' => [
        'warning' => [$this->t('This article is due for review.')],
      ],
      '#status_headings' => [
        'warning' => $this->t('Warning message'),
      ],
      '#weight' => -100,
    ];
  }

  /**
   * Builds the list of articles due for review, most overdue first.
   *
   * @return array
   *   A render array.
   */
  public function buildReviewQueue(): array {
    $candidates = $this->articleRepository->getNotReviewedSince($this->time->getCurrentTime() - ReviewPolicy::REVIEW_INTERVAL);
    $queue = $this->reviewPolicy->getReviewQueue($candidates);

    return [
      '#theme' => 'item_list',
      '#items' => array_map(fn (ArticleInterface $article): string => $article->getHeadline(), $queue),
      '#empty' => $this->t('No articles are due for review.'),
      // The queue depends on the current time.
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

}
