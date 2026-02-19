<?php

namespace Drupal\block_content\Event;

use Drupal\block_content\BlockContentInterface;
use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Access\AccessibleInterface;

/**
 * Block content event to allow setting an access dependency.
 *
 * @internal
 */
class BlockContentGetDependencyEvent extends Event {

  /**
   * The block content entity.
   *
   * @var BlockContentInterface
   */
  protected $blockContent;

  /**
   * The dependency.
   *
   * @var AccessibleInterface
   */
  protected $accessDependency;

  /**
   * BlockContentGetDependencyEvent constructor.
   *
   * @param BlockContentInterface $blockContent
   *   The block content entity.
   */
  public function __construct(BlockContentInterface $blockContent) {
    $this->blockContent = $blockContent;
  }

  /**
   * Gets the block content entity.
   *
   * @return BlockContentInterface
   *   The block content entity.
   */
  public function getBlockContentEntity() {
    return $this->blockContent;
  }

  /**
   * Gets the access dependency.
   *
   * @return AccessibleInterface
   *   The access dependency.
   */
  public function getAccessDependency() {
    return $this->accessDependency;
  }

  /**
   * Sets the access dependency.
   *
   * @param AccessibleInterface $access_dependency
   *   The access dependency.
   */
  public function setAccessDependency(AccessibleInterface $access_dependency) {
    $this->accessDependency = $access_dependency;
  }

}
