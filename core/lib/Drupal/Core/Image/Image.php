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
   * Path of the image file.
   *
   * @var string
   */
  protected $source = '';

  /**
   * An image toolkit object.
   */
  protected ImageToolkitInterface $toolkit;

  /**
   * File size in bytes.
   *
   * @var int
   */
  protected $fileSize;

  /**
   * BC layer.
   *
   * @deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Play
   *    whack-a-mole instead.
   *
   * @see https://www.drupal.org/node/3590417
   */
  public function __sleep(): array {
    $ret = get_object_vars($this);
    unset($ret['toolkit']);
    return array_keys($ret);
  }

  /**
   * Constructs a new Image object.
   *
   * @param \Drupal\Core\ImageToolkit\ImageToolkitInterface $toolkit
   *   The image toolkit.
   * @param string|null $source
   *   (optional) The path to an image file, or NULL to construct the object
   *   with no image source.
   */
  public function __construct(
    ImageToolkitInterface $toolkit,
    ?string $source = NULL,
  ) {
    $this->toolkit = $toolkit;
    $this->toolkit->backfillImage = $this;
    if ($source) {
      $this->source = $source;
      // @phpstan-ignore-next-line
      $this->toolkit->setSource($this->source);
      // Defer image file validity check to the toolkit.
      if ($this->toolkit->parseFile($this)) {
        $this->fileSize = filesize($this->source);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isValid() {
    return $this->toolkit()->isValid($this);
  }

  /**
   * {@inheritdoc}
   */
  public function getHeight() {
    return $this->toolkit()->getHeight($this);
  }

  /**
   * {@inheritdoc}
   */
  public function getWidth() {
    return $this->toolkit()->getWidth($this);
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
    return $this->toolkit()->getMimeType($this);
  }

  /**
   * {@inheritdoc}
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
    return $this->toolkit()->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function getToolkit() {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Play whack-a-mole instead. See https://www.drupal.org/node/3590417', E_USER_DEPRECATED);
    if (!$this->toolkit()->isStateful && !$this->toolkit()->isClone) {
      // When accessing this method, for BC stateless toolkits should get a
      // clone of the toolkit singleton, that would have the backfill image
      // available.
      $this->toolkit = clone $this->toolkit;
      $this->toolkit->backfillImage = $this;
    }
    return $this->toolkit;
  }

  /**
   * {@inheritdoc}
   */
  public function toolkit(): ImageToolkitInterface {
    return $this->toolkit;
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
    if ($return = $this->toolkit()->save($destination, $this)) {
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
    return $this->toolkit()->apply($operation, $arguments, $this);
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
