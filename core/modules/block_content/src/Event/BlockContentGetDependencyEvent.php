<?php

namespace Drupal\block_content\Event;

use Drupal\block_content\BlockContentInterface;
use Drupal\Core\Access\AccessibleInterface;
use Drupal\Component\EventDispatcher\Event;

/**
 * Block content event to allow setting an access dependency.
 *
 * @internal
 */
class BlockContentGetDependencyEvent extends Event {

  /**
   * The dependency.
   *
   * @var \Drupal\Core\Access\AccessibleInterface
   */
  protected $accessDependency;

  /**
   * Constructs a new BlockContentGetDependencyEvent object.
   *
   * @param \Drupal\block_content\BlockContentInterface $blockContent
   *   The block content entity.
   * @param string $operation
   *   The access operation for which to load the block content dependency.
   *   Defaults to 'view'.
   */
  public function __construct(
    protected BlockContentInterface $blockContent,
    protected readonly string $operation = 'view',
  ) {}

  /**
   * Gets the block content entity.
   *
   * @return \Drupal\block_content\BlockContentInterface
   *   The block content entity.
   */
  public function getBlockContentEntity() {
    return $this->blockContent;
  }

  /**
   * Gets the access operation for this dependency event.
   *
   * @return string
   *   The access operation.
   */
  public function getOperation(): string {
    return $this->operation;
  }

  /**
   * Gets the access dependency.
   *
   * @return \Drupal\Core\Access\AccessibleInterface
   *   The access dependency.
   */
  public function getAccessDependency() {
    return $this->accessDependency;
  }

  /**
   * Sets the access dependency.
   *
   * @param \Drupal\Core\Access\AccessibleInterface $access_dependency
   *   The access dependency.
   */
  public function setAccessDependency(AccessibleInterface $access_dependency) {
    $this->accessDependency = $access_dependency;
  }

}
