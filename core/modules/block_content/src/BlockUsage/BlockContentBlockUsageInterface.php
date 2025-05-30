<?php

declare(strict_types=1);

namespace Drupal\block_content\BlockUsage;

use Drupal\block_content\BlockContentInterface;

/**
 * Interface for determining which Blocks use plugins from Block Content.
 */
interface BlockContentBlockUsageInterface {

  /**
   * Get usage of the plugins derived from a Block Content.
   *
   * @param \Drupal\block_content\BlockContentInterface $blockContent
   *   A block content entity.
   *
   * @return \Drupal\block_content\BlockUsage\BlockContentUsageList
   *   List of Blocks using plugins of a Block Content.
   */
  public function getUsage(BlockContentInterface $blockContent): BlockContentUsageList;

}
