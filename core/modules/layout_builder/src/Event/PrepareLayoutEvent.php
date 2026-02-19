<?php

namespace Drupal\layout_builder\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Event fired in #pre_render of \Drupal\layout_builder\Element\LayoutBuilder.
 *
 * Subscribers to this event can prepare section storage before rendering.
 *
 * @see \Drupal\layout_builder\LayoutBuilderEvents::PREPARE_LAYOUT
 * @see \Drupal\layout_builder\Element\LayoutBuilder::prepareLayout()
 */
class PrepareLayoutEvent extends Event {

  /**
   * The section storage plugin.
   *
   * @var SectionStorageInterface
   */
  protected $sectionStorage;

  /**
   * Constructs a new PrepareLayoutEvent.
   *
   * @param SectionStorageInterface $section_storage
   *   The section storage preparing the Layout.
   */
  public function __construct(SectionStorageInterface $section_storage) {
    $this->sectionStorage = $section_storage;
  }

  /**
   * Gets the section storage.
   *
   * @return SectionStorageInterface
   *   The section storage.
   */
  public function getSectionStorage(): SectionStorageInterface {
    return $this->sectionStorage;
  }

}
