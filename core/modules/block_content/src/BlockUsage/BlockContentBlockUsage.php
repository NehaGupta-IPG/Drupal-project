<?php

declare(strict_types=1);

namespace Drupal\block_content\BlockUsage;

use Drupal\block_content\BlockContentInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Determine which Block configs use plugins derived from Block Content.
 *
 * This service is only available when Block is installed.
 */
class BlockContentBlockUsage implements BlockContentBlockUsageInterface {

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getUsage(BlockContentInterface $blockContent): BlockContentUsageList {
    return BlockContentUsageList::fromBlockContent(
      $blockContent,
      fn(): EntityStorageInterface => $this->entityTypeManager->getStorage('block'),
    );
  }

}
