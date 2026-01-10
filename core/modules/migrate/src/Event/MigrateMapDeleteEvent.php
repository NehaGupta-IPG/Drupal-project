<?php

namespace Drupal\migrate\Event;

use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\Component\EventDispatcher\Event;

/**
 * Wraps a migrate map delete event for event listeners.
 */
class MigrateMapDeleteEvent extends Event {

  /**
   * Map plugin.
   *
   * @var \Drupal\migrate\Plugin\MigrateIdMapInterface
   */
  protected $map;

  /**
   * Array of source ID fields.
   *
   * @var array
   */
  protected $sourceId;

  public function __construct(MigrateIdMapInterface $map, array $source_id) {
    $this->map = $map;
    $this->sourceId = $source_id;
  }

  /**
   * Gets the map plugin.
   *
   * @return \Drupal\migrate\Plugin\MigrateIdMapInterface
   *   The map plugin that caused the event to fire.
   */
  public function getMap() {
    return $this->map;
  }

  /**
   * Gets the source ID of the item being removed from the map.
   *
   * @return array
   *   Array of source ID fields.
   */
  public function getSourceId() {
    return $this->sourceId;
  }

}
