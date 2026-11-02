<?php

declare(strict_types=1);

namespace Drupal\newsroom\Entity;

use Drupal\Core\Entity\Attribute\Bundle;
use Drupal\newsroom\ArticleInterface;
use Drupal\node\Entity\Node;

/**
 * Bundle class for the article node type.
 */
#[Bundle(
  entityType: 'node',
  bundle: 'article',
)]
class Article extends Node implements ArticleInterface {

  /**
   * {@inheritdoc}
   */
  public function getHeadline(): string {
    return $this->get('title')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getPublicationTime(): int {
    return (int) $this->get('field_publication_date')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastReviewTime(): ?int {
    $value = $this->get('field_last_review_date')->value;
    return $value !== NULL ? (int) $value : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isEvergreen(): bool {
    return (bool) $this->get('field_evergreen')->value;
  }

}
