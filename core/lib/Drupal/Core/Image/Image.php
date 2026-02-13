<?php

namespace Drupal\Core\Image;

use Drupal\Core\ImageToolkit\ImageToolkitInterface;

/**
 * Defines an image object to represent an image file.
 *
 * @see \Drupal\Core\ImageToolkit\ImageToolkitInterface
 * @see \Drupal\image\ImageEffectInterface
 *
 * @ingroup image
 */
class Image implements ImageInterface {

  /**
   * The image factory.
   *
   * @todo make the prop non-nullable.
   */
  protected readonly ?ImageFactory $imageFactory;

  /**
   * The image toolkit id.
   */
  protected readonly string $toolkitId;

  /**
   * Path of the image file.
   */
  protected string $source = '';

  /**
   * An image toolkit object.
   *
   * @deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Play
   *    whack-a-mole instead.
   *
   * @see https://www.drupal.org/node/7654123
   */
  protected ImageToolkitInterface $toolkit;

  /**
   * File size in bytes.
   */
  protected int $fileSize;

  /**
   * Constructs a new Image object.
   *
   * @param \Drupal\Core\ImageToolkit\ImageToolkitInterface $toolkit
   *   The image toolkit.
   * @param string|null $source
   *   (optional) The path to an image file, or NULL to construct the object
   *   with no image source.
   * @param \Drupal\Core\Image\ImageFactory|null $imageFactory
   *   The image factory.
   */
  public function __construct(
    ImageToolkitInterface $toolkit,
    $source = NULL,
    ?ImageFactory $imageFactory = NULL,
  ) {
    if (!$imageFactory) {
      @trigger_error(__METHOD__ . ' not receiving the ImageFactory is deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Play whack-a-mole instead. See https://www.drupal.org/node/7654123', E_USER_DEPRECATED);
    }
    $this->imageFactory = $imageFactory;
    // @phpstan-ignore property.deprecated
    $this->toolkit = $toolkit;
    $this->toolkitId = $toolkit->getPluginId();
    if ($source) {
      $this->source = $source;
      // @phpstan-ignore-next-line
      $this->getToolkit()->setSource($this->source);
      // Defer image file validity check to the toolkit.
      // @phpstan-ignore method.deprecated
      if ($this->getToolkit()->parseFile($this)) {
        $this->fileSize = filesize($this->source);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isValid() {
    // @phpstan-ignore method.deprecated
    return $this->getToolkit()->isValid($this);
  }

  /**
   * {@inheritdoc}
   */
  public function getHeight() {
    // @phpstan-ignore method.deprecated
    return $this->getToolkit()->getHeight($this);
  }

  /**
   * {@inheritdoc}
   */
  public function getWidth() {
    // @phpstan-ignore method.deprecated
    return $this->getToolkit()->getWidth($this);
  }

  /**
   * {@inheritdoc}
   */
  public function getFileSize() {
    return $this->fileSize;
  }

  /**
   * {@inheritdoc}
   */
  public function getMimeType() {
    // @phpstan-ignore method.deprecated
    return $this->getToolkit()->getMimeType($this);
  }

  /**
   * @todo add to interface.
   */
  public function setSource(string $source): static {
    $this->source = $source;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSource() {
    return $this->source;
  }

  /**
   * {@inheritdoc}
   */
  public function getToolkitId() {
    return $this->toolkitId;
  }

  /**
   * Returns the image toolkit singleton to be used for manipulation.
   *
   * @return \Drupal\Core\ImageToolkit\ImageToolkitInterface
   *   The image toolkit.
   *
   * @todo add to the interface.
   */
  public function toolkit(): ImageToolkitInterface {
    return $this->imageFactory->getToolkit($this->toolkitId);
  }

  /**
   * {@inheritdoc}
   */
  public function getToolkit() {
    @trigger_error(__METHOD__ . ' is deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Play whack-a-mole instead. See https://www.drupal.org/node/7654123', E_USER_DEPRECATED);
    // @phpstan-ignore isset.initializedProperty
    if (!isset($this->toolkit)) {
      $this->toolkit = clone $this->toolkit();
      $this->toolkit->backfillImage = $this;
    }
    return $this->toolkit;
  }

  /**
   * Sets the image toolkit used for this image file.
   *
   * @param \Drupal\Core\ImageToolkit\ImageToolkitInterface $toolkit
   *   The image toolkit.
   *
   * @return static
   *   The image.
   *
   * @deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Play
   *    whack-a-mole instead.
   *
   * @see https://www.drupal.org/node/7654123
   */
  public function setToolkit(ImageToolkitInterface $toolkit): static {
    @trigger_error(__METHOD__ . ' is deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Play whack-a-mole instead. See https://www.drupal.org/node/7654123', E_USER_DEPRECATED);
    $this->toolkit = $toolkit;
    $this->toolkit->backfillImage = $this;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function save($destination = NULL) {
    // Return immediately if the image is not valid.
    if (!$this->isValid()) {
      return FALSE;
    }

    $destination = $destination ?: $this->getSource();
    // @phpstan-ignore method.deprecated
    if ($return = $this->getToolkit()->save($this, $destination)) {
      // Clear the cached file size and refresh the image information.
      clearstatcache(TRUE, $destination);
      $this->fileSize = filesize($destination);
      $this->source = $destination;

      if (\Drupal::service('file_system')->chmod($destination)) {
        return $return;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function apply($operation, array $arguments = []) {
    // @phpstan-ignore method.deprecated
    return $this->getToolkit()->apply($this, $operation, $arguments);
  }

  /**
   * {@inheritdoc}
   */
  public function createNew($width, $height, $extension = 'png', $transparent_color = '#ffffff') {
    return $this->apply('create_new', [
      'width' => $width,
      'height' => $height,
      'extension' => $extension,
      'transparent_color' => $transparent_color,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function convert($extension) {
    return $this->apply('convert', ['extension' => $extension]);
  }

  /**
   * {@inheritdoc}
   */
  public function crop($x, $y, $width, $height = NULL) {
    return $this->apply('crop', ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height]);
  }

  /**
   * {@inheritdoc}
   */
  public function desaturate() {
    return $this->apply('desaturate', []);
  }

  /**
   * {@inheritdoc}
   */
  public function resize($width, $height) {
    return $this->apply('resize', ['width' => $width, 'height' => $height]);
  }

  /**
   * {@inheritdoc}
   */
  public function rotate($degrees, $background = NULL) {
    return $this->apply('rotate', ['degrees' => $degrees, 'background' => $background]);
  }

  /**
   * {@inheritdoc}
   */
  public function scaleAndCrop($width, $height) {
    return $this->apply('scale_and_crop', ['width' => $width, 'height' => $height]);
  }

  /**
   * {@inheritdoc}
   */
  public function scale($width, $height = NULL, $upscale = FALSE) {
    return $this->apply('scale', ['width' => $width, 'height' => $height, 'upscale' => $upscale]);
  }

}
