<?php

declare(strict_types=1);

namespace Drupal\comment\Hook;

use Drupal\node\NodeInterface;
use Drupal\comment\CommentLinkBuilderInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Menu hook implementations for comment.
 */
class CommentMenuHooks {

  public function __construct(
    private readonly CommentLinkBuilderInterface $commentLinkBuilder,
  ) {}

  /**
   * Implements hook_node_links_alter().
   */
  #[Hook('node_links_alter')]
  public function nodeLinksAlter(array &$links, NodeInterface $node, array &$context): void {
    // Comment links are only added to node entity type for backwards
    // compatibility. Should you require comment links for other entity types
    // you can do so by implementing a new field formatter.
    // @todo Make this configurable from the formatter. See
    //   https://www.drupal.org/node/1901110.
    $comment_links = $this->commentLinkBuilder->buildCommentedEntityLinks($node, $context);
    $links += $comment_links;
  }

}
