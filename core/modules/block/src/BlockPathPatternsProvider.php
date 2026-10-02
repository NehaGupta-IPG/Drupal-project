<?php

declare(strict_types=1);

namespace Drupal\block;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\system\Path\PathPatternsProviderInterface;

/**
 * Provides the page patterns of block visibility conditions.
 */
class BlockPathPatternsProvider implements PathPatternsProviderInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getPatterns(): array {
    $patterns = [];
    /** @var \Drupal\block\BlockInterface $block */
    foreach ($this->entityTypeManager->getStorage('block')->loadMultiple() as $block) {
      $pages = $block->getVisibility()['request_path']['pages'] ?? '';
      if ($pages !== '') {
        $patterns[] = $pages;
      }
    }
    return $patterns;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return $this->entityTypeManager->getDefinition('block')->getListCacheTags();
  }

}
