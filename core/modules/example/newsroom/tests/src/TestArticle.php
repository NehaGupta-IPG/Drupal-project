<?php

declare(strict_types=1);

namespace Drupal\Tests\newsroom;

use Drupal\newsroom\ArticleInterface;

/**
 * An in-memory article for tests.
 */
class TestArticle implements ArticleInterface {

  /**
   * Constructs a TestArticle object.
   *
   * @param int $published
   *   The publication time.
   * @param int|null $reviewed
   *   (optional) The last review time. Defaults to NULL.
   * @param bool $evergreen
   *   (optional) Whether the article is evergreen. Defaults to FALSE.
   * @param string $headline
   *   (optional) The headline. Defaults to 'Headline'.
   */
  public function __construct(
    protected readonly int $published,
    protected readonly ?int $reviewed = NULL,
    protected readonly bool $evergreen = FALSE,
    protected readonly string $headline = 'Headline',
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getHeadline(): string {
    return $this->headline;
  }

  /**
   * {@inheritdoc}
   */
  public function getPublicationTime(): int {
    return $this->published;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastReviewTime(): ?int {
    return $this->reviewed;
  }

  /**
   * {@inheritdoc}
   */
  public function isEvergreen(): bool {
    return $this->evergreen;
  }

}
