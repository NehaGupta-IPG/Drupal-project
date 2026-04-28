<?php

declare(strict_types=1);

namespace Drupal\Core\DefaultContent;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched before default content is imported.
 *
 * Subscribers to this event should avoid modifying content, because it is
 * probably about to change again. This event is best used for tasks like
 * notifications, logging, or updating a value in state. It can also be used
 * to skip importing certain entities, identified by their UUID.
 */
final class PreImportEvent extends Event {

  /**
   * Entity UUIDs that should not be imported.
   *
   * @var string[]
   */
  private array $skip = [];

  /**
   * Constructs a PreImportEvent object.
   *
   * @param \Drupal\Core\DefaultContent\Finder|null $finder
   *   (optional) Deprecated. Passing a $finder is deprecated in
   *   drupal:10.4.0 and is removed from drupal:12.0.0. Do not pass this
   *   argument.
   * @param \Drupal\Core\DefaultContent\Existing $existing
   *   What the importer will do when importing an entity that already exists.
   *
   * @see https://www.drupal.org/node/3585238
   */
  public function __construct(
    public readonly ?Finder $finder = NULL,
    public readonly Existing $existing = Existing::Error,
  ) {
    if ($finder !== NULL) {
      @trigger_error('Passing a $finder argument to ' . __CLASS__ . '::__construct() is deprecated in drupal:10.4.0 and is removed from drupal:12.0.0. See https://www.drupal.org/node/3585238', E_USER_DEPRECATED);
    }
  }

  /**
   * Adds an entity UUID to the skip list.
   *
   * @param string $uuid
   *   The UUID of an entity that should not be imported.
   * @param string|\Stringable|null $reason
   *   (optional) A reason why the entity is being skipped. Defaults to NULL.
   */
  public function skip(string $uuid, string|\Stringable|null $reason = NULL): void {
    $this->skip[$uuid] = $reason;
  }

  /**
   * Returns the list of entity UUIDs that should not be imported.
   *
   * @return string|\Stringable|null[]
   *   An array whose keys are the UUIDs of the entities that should not be
   *   imported, and the values are either a short explanation of why that
   *   entity was skipped, or NULL if no explanation was given.
   */
  public function getSkipList(): array {
    return $this->skip;
  }

}
