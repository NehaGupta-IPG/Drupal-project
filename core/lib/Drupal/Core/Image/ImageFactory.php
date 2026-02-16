<?php

namespace Drupal\Core\Image;

use Drupal\Core\ImageToolkit\ImageToolkitInterface;
use Drupal\Core\ImageToolkit\ImageToolkitManager;

/**
 * Provides a factory for image objects.
 */
class ImageFactory {

  /**
   * The image toolkit ID to use for this factory.
   *
   * @var string
   */
  protected $toolkitId;


  /**
   * Constructs a new ImageFactory object.
   *
   * @param \Drupal\Core\ImageToolkit\ImageToolkitManager $toolkitManager
   *   The image toolkit plugin manager.
   */
  public function __construct(
    protected readonly ImageToolkitManager $toolkitManager,
  ) {
  }

  /**
   * Sets the ID of the default image toolkit to use.
   *
   * @param string $toolkit_id
   *   The ID of the image toolkit to use for this image factory.
   *
   * @return $this
   */
  public function setToolkitId($toolkit_id) {
    $this->toolkitId = $toolkit_id;
    return $this;
  }

  /**
   * Gets the ID of the image toolkit currently in use.
   *
   * @return string
   *   The ID of the image toolkit in use by the image factory.
   */
  public function getToolkitId() {
    if ($this->toolkitId === NULL) {
      $this->toolkitId = $this->toolkitManager->getDefaultToolkitId();
    }
    return $this->toolkitId;
  }

  /**
   * Constructs a new Image object.
   *
   * Normally, the toolkit set as default in the admin UI is used by the
   * factory to create new Image objects. This can be overridden through
   * \Drupal\Core\Image\ImageInterface::setToolkitId() so that any new Image
   * object created will use the new toolkit specified. Finally, a single
   * Image object can be created using a specific toolkit, regardless of the
   * current factory settings, by passing its plugin ID in the $toolkit_id
   * argument.
   *
   * @param string|null $source
   *   (optional) The path to an image file, or NULL to construct the object
   *   with no image source.
   * @param string|null $toolkit_id
   *   (optional) The ID of the image toolkit to use for this image, or NULL
   *   to use the current toolkit.
   *
   * @return \Drupal\Core\Image\ImageInterface
   *   An Image object.
   *
   * @see ImageFactory::setToolkitId()
   */
  public function get($source = NULL, $toolkit_id = NULL) {
    $toolkit_id = $toolkit_id ?: $this->getToolkitId();
    $toolkit_definition = $this->toolkitManager->getDefinition($toolkit_id);
    if (isset($toolkit_definition['imageClass'])) {
      $image_class = $toolkit_definition['imageClass'];
      return new $image_class($this, $toolkit_id, $source);
    }
    return new Image($this->getToolkit($toolkit_id), $source, $this);
  }

  /**
   * Returns the image file extensions supported by the toolkit.
   *
   * @param string|null $toolkit_id
   *   (optional) The ID of the image toolkit to use for checking, or NULL
   *   to use the current toolkit.
   *
   * @return array
   *   An array of supported image file extensions (e.g. png/jpeg/gif).
   *
   * @see \Drupal\Core\ImageToolkit\ImageToolkitInterface::getSupportedExtensions()
   */
  public function getSupportedExtensions($toolkit_id = NULL) {
    return $this->getToolkit($toolkit_id)->getSupportedExtensions();
  }

  /**
   * Gets an image toolkit from the manager.
   *
   * It would be the singleton of stateless toolkit, or a new instance od the
   * toolkit for stateful ones. In drupal:43.0.0 only stateless toolkits will
   * be accepted.
   *
   * @param string|null $id
   *   The ID of the image toolkit. The default toolkit will be returned if
   *   NULL.
   *
   * @return \Drupal\Core\ImageToolkit\ImageToolkitInterface
   *   The toolkit.
   */
  public function getToolkit(?string $toolkit_id = NULL): ImageToolkitInterface {
    return $this->toolkitManager->getToolkit($toolkit_id ?? $this->getToolkitId());
  }

}
