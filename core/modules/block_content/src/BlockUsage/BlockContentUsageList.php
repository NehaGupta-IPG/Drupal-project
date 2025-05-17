<?php

declare(strict_types=1);

namespace Drupal\block_content\BlockUsage;

use Drupal\block_content\BlockContentInterface;

/**
 * List of Block configs using plugins derived from Block Content.
 *
 * Lazily loads Block configs or counts at call time.
 *
 * @implements \IteratorAggregate<int, \Drupal\block\BlockInterface>
 */
final class BlockContentUsageList implements \Countable, \IteratorAggregate {

  /**
   * Constructs a new BlockContentUsageList.
   *
   * @param string $pluginId
   *   The plugin ID of the block plugin derived from the block content.
   * @param (callable(): \Drupal\Core\Entity\EntityStorageInterface) $lazyBlockStorage
   *   A callable that returns Block config storage.
   */
  private function __construct(
    private string $pluginId,
    private $lazyBlockStorage,
  ) {
  }

  /**
   * Creates a new BlockContentUsageList for a Block Content entity.
   *
   * @param \Drupal\block_content\BlockContentInterface $blockContent
   *   A block content entity.
   * @param (callable(): \Drupal\Core\Entity\EntityStorageInterface) $lazyBlockStorage
   *   A callable that returns Block config storage.
   *
   * @internal
   *   This method is designed to be invoked exclusively by
   *   BlockContentBlockUsageInterface::getUsage().
   */
  public static function fromBlockContent(BlockContentInterface $blockContent, callable $lazyBlockStorage): static {
    return new static(
      sprintf('block_content:%s', $blockContent->uuid()),
      $lazyBlockStorage,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator(): \Traversable {
    $blockStorage = ($this->lazyBlockStorage)();
    yield from $blockStorage->loadByProperties(['plugin' => $this->pluginId]);
  }

  /**
   * {@inheritdoc}
   */
  public function count(): int {
    // Count how many block config instances will be affected by the derivative
    // plugin being undefined.
    $blockStorage = ($this->lazyBlockStorage)();
    return (int) $blockStorage
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('plugin', $this->pluginId)
      ->count()
      ->execute();
  }

}
