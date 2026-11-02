<?php

declare(strict_types=1);

namespace Drupal\newsroom\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\newsroom\ArticleInterface;
use Drupal\newsroom\Render\ArticleViewBuilder;
use Drupal\node\NodeInterface;

/**
 * Hook implementations for newsroom.
 */
class NewsroomHooks {

  public function __construct(
    protected readonly ArticleViewBuilder $articleViewBuilder,
  ) {}

  /**
   * Implements hook_ENTITY_TYPE_view() for node entities.
   */
  #[Hook('node_view')]
  public function nodeView(array &$build, NodeInterface $node, EntityViewDisplayInterface $display, string $view_mode): void {
    if (!$node instanceof ArticleInterface || $view_mode !== 'full') {
      return;
    }

    // Only editors, that is users allowed to update the article, should see
    // the notice.
    $access = $node->access('update', NULL, TRUE);
    if ($access->isAllowed()) {
      $build['newsroom_review_notice'] = $this->articleViewBuilder->buildReviewNotice($node);
    }

    CacheableMetadata::createFromRenderArray($build)
      ->addCacheableDependency($access)
      ->applyTo($build);
  }

}
