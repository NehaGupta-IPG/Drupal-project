<?php

declare(strict_types=1);

namespace Drupal\content_translation\Hook;

use Drupal\comment\CommentInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\content_translation\ContentTranslationManagerInterface;

/**
 * Comment hook implementations for content_translation.
 */
class ContentTranslationCommentHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly ContentTranslationManagerInterface $contentTranslationManager,
  ) {}

  /**
   * Implements hook_comment_links_alter().
   */
  #[Hook('comment_links_alter')]
  public function addTranslateLink(array &$links, CommentInterface $comment, array $context): void {
    if ($this->contentTranslationManager->access($comment)->isAllowed()) {
      $links['comment-translations'] = [
        'title' => $this->t('Translate'),
        'url' => $comment->toUrl('drupal:content-translation-overview'),
      ];
    }
  }

}
