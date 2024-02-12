<?php

namespace Drupal\layout_builder\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Cache\CacheableResponseTrait;
use Drupal\layout_builder\Section;

/**
 * Event fired when a section's render array is being built.
 */
class SectionBuildRenderArrayEvent extends Event {

  use CacheableResponseTrait;

  const SECTION_BUILD_RENDER_ARRAY = 'section.build.render_array';

  /**
   * The section whose regions render array is being built.
   *
   * @var \Drupal\layout_builder\Section
   */
  protected $section;

  /**
   * The available contexts.
   *
   * @var \Drupal\Core\Plugin\Context\ContextInterface[]
   */
  protected $contexts;

  /**
   * Whether the section is in preview mode or not.
   *
   * @var bool
   */
  protected $inPreview;

  /**
   * The section render array being built by the event subscribers.
   *
   * @var array
   */
  protected $build = [];

  /**
   * Constructs a new event instance.
   *
   * @param \Drupal\layout_builder\Section $section
   *   The section whose regions render array is being built.
   * @param array $build
   *   The section render array being built by the event subscribers.
   * @param \Drupal\Core\Plugin\Context\ContextInterface[] $contexts
   *   The available contexts.
   * @param bool $in_preview
   *   Whether the section is in preview mode or not.
   */
  public function __construct(Section $section, array $build, array $contexts, bool $in_preview) {
    $this->section = $section;
    $this->build = $build;
    $this->contexts = $contexts;
    $this->inPreview = $in_preview;
  }

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
   * Gets the section's build render array.
   *
   * @return array
   *   The section's build render array.
   */
  public function getBuild(): array {
    return $this->build;
  }

  /**
   * Set the section's build render array.
   *
   * @param array $build
   *   The section's build render array.
   *
   * @return $this
   */
  public function setBuild(array $build): self {
    $this->build = $build;
    return $this;
  }

}
