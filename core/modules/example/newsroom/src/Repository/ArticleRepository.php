<?php

declare(strict_types=1);

namespace Drupal\newsroom\Repository;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;

/**
 * Retrieves published articles from node storage.
 */
final class ArticleRepository implements ArticleRepositoryInterface {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getLatestIds(int $limit): array {
    return $this->getQuery()
      ->sort('field_publication_date', 'DESC')
      ->range(0, $limit)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getLatest(int $limit): array {
    return $this->loadMultiple($this->getLatestIds($limit));
  }

  /**
   * {@inheritdoc}
   */
  public function getEvergreenIds(): array {
    return $this->getQuery()
      ->condition('field_evergreen', TRUE)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getEvergreen(): array {
    return $this->loadMultiple($this->getEvergreenIds());
  }

  /**
   * {@inheritdoc}
   */
  public function getNotReviewedSinceIds(int $time): array {
    $query = $this->getQuery();
    $never_reviewed = $query->andConditionGroup()
      ->notExists('field_last_review_date')
      ->condition('field_publication_date', $time, '<');
    $not_reviewed_since = $query->orConditionGroup()
      ->condition('field_last_review_date', $time, '<')
      ->condition($never_reviewed);
    return $query
      ->condition($not_reviewed_since)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getNotReviewedSince(int $time): array {
    return $this->loadMultiple($this->getNotReviewedSinceIds($time));
  }

  /**
   * Returns a query matching published articles.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The entity query.
   */
  protected function getQuery(): QueryInterface {
    return $this->getStorage()->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'article')
      ->condition('status', 1);
  }

  /**
   * Loads the specified articles.
   *
   * @param array<int|string, int|string> $ids
   *   The article IDs.
   *
   * @return array<int|string, \Drupal\newsroom\ArticleInterface>
   *   The articles keyed by ID, in the order of the specified IDs.
   */
  protected function loadMultiple(array $ids): array {
    return $ids ? $this->getStorage()->loadMultiple($ids) : [];
  }

  /**
   * Returns the node storage.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   The node storage.
   */
  protected function getStorage(): EntityStorageInterface {
    return $this->entityTypeManager->getStorage('node');
  }

}
