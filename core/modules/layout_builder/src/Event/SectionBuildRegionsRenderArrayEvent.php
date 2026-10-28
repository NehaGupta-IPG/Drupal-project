<?php

declare(strict_types=1);

namespace Drupal\layout_builder\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Cache\CacheableResponseTrait;
use Drupal\layout_builder\Section;

/**
 * Event fired when a section's regions render array is being built.
 *
 * Subscribers to this event should read and alter the regions render array,
 * then set the updated render array using ::setRegions(). They can also respond
 * with cacheable metadata.
 */
class SectionBuildRegionsRenderArrayEvent extends Event {

  use CacheableResponseTrait;

  /**
   * The regions render array being built by the event subscribers.
   *
   * @var array
   */
  protected $regions = [];

  /**
   * Constructs a new event instance.
   *
   * @param \Drupal\layout_builder\Section $section
   *   The section whose regions render array is being built.
   * @param \Drupal\Core\Plugin\Context\ContextInterface[] $contexts
   *   The available contexts.
   * @param bool $inPreview
   *   Whether the section is in preview mode or not.
   */
  public function __construct(
    protected Section $section,
    protected readonly array $contexts,
    protected readonly bool $inPreview,
  ) {}

  /**
   * Gets the section whose regions render array is being built.
   *
   * @return \Drupal\layout_builder\Section
   *   The section whose regions array is being built.
   */
  public function getSection(): Section {
    return $this->section;
  }

  /**
   * Gets the available contexts.
   *
   * @return array|\Drupal\Core\Plugin\Context\ContextInterface[]
   *   The available contexts.
   */
  public function getContexts(): array {
    return $this->contexts;
  }

  /**
   * Determines if the section is in preview mode.
   *
   * @return bool
   *   Whether the section is in preview mode or not.
   */
  public function isInPreview(): bool {
    return $this->inPreview;
  }

  /**
   * Gets the regions render array in its current state.
   *
   * @return array
   *   The regions array built by the event subscribers.
   */
  public function getRegions(): array {
    return $this->regions;
  }

  /**
   * Sets the regions render array.
   *
   * @param array $regions
   *   A regions render array.
   *
   * @return $this
   */
  public function setRegions(array $regions): self {
    $this->regions = $regions;
    return $this;
  }

}
