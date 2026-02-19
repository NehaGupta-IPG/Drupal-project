<?php

namespace Drupal\migrate\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\migrate\Plugin\MigrateIdMapInterface;

/**
 * Wraps a migrate map delete event for event listeners.
 */
class MigrateMapDeleteEvent extends Event {

  /**
   * Map plugin.
   *
   * @var MigrateIdMapInterface
   */
  protected $map;

  /**
   * Array of source ID fields.
   *
   * @var array
   */
  protected $sourceId;

  /**
   * Constructs a migration map delete event object.
   *
   * @param MigrateIdMapInterface $map
   *   Map plugin.
   * @param array $source_id
   *   Array of source ID fields representing the object being deleted from the
   *   map.
   */
  public function __construct(MigrateIdMapInterface $map, array $source_id) {
    $this->map = $map;
    $this->sourceId = $source_id;
  }

  /**
   * Gets the map plugin.
   *
   * @return MigrateIdMapInterface
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
