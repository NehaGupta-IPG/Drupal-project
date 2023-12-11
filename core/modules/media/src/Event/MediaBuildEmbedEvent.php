<?php

namespace Drupal\media\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\media\MediaInterface;

/**
 * Event that gets triggered when a media is about to be embedded in ckeditor5.
 */
class MediaBuildEmbedEvent extends Event {

  /**
   * The view mode the embedded media will be rendered with.
   *
   * @var string
   */
  protected string $viewMode;

  /**
   * The media which will be embedded.
   *
   * @var \Drupal\media\MediaInterface
   */
  protected MediaInterface $media;

  /**
   * The build array of the media before it gets rendered for embedding.
   *
   * @var array
   */
  protected array $build;

  /**
   * The <drupal-media> DOM node that gets replaced by the embed.
   *
   * @var \DOMElement
   */
  protected \DOMElement $node;

  /**
   * Construct a MediaBuildEmbedEvent object.
   *
   * @param string $viewMode
   *   The view mode the embedded media will be rendered with.
   * @param \Drupal\media\MediaInterface $media
   *   The media which will be embedded.
   * @param array $build
   *   The build array of the media before it gets rendered for embedding.
   * @param \DOMElement $node
   *   The <drupal-media> DOM node that gets replaced by the embed.
   */
  public function __construct(string $viewMode, MediaInterface $media, array $build, \DOMElement $node) {
    $this->viewMode = $viewMode;
    $this->media = $media;
    $this->build = $build;
    $this->node = $node;
  }

  /**
   * Getter for the view mode.
   */
  public function getViewMode(): string {
    return $this->viewMode;
  }

  /**
   * Gets the media.
   */
  public function getMedia(): MediaInterface {
    return $this->media;
  }

  /**
   * Gets the build.
   */
  public function getBuild(): array {
    return $this->build;
  }

  /**
   * Sets the build.
   */
  public function setBuild(array $build): void {
    $this->build = $build;
  }

  /**
   * Gets the <drupal-media> DOM node.
   */
  public function getNode(): \DOMElement {
    return $this->node;
  }

}
