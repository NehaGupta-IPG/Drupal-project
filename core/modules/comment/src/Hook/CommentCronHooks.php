<?php

declare(strict_types=1);

namespace Drupal\comment\Hook;

use Drupal\comment\CommentStatisticsInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Cron hook implementations for comment.
 */
class CommentCronHooks {

  public function __construct(
    private readonly CommentStatisticsInterface $commentStatistics,
    private readonly StateInterface $state,
  ) {}

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
    // Store the maximum possible comments per thread (used for node search
    // ranking by reply count).
    $max = max(1, $this->commentStatistics->getMaximumCount('node'));
    $this->state->set(
      'comment.node_comment_statistics_scale',
      1.0 / $max
    );
  }

}
