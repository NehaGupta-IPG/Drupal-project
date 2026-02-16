<?php

namespace Drupal\Core\Image;

use Drupal\Core\ImageToolkit\ImageToolkitException;
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
   *
   * @var \Drupal\Core\ImageToolkit\ImageToolkitInterface
   *
   * @deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Play
   *    whack-a-mole instead.
   *
   * @see https://www.drupal.org/node/7654123
   */
  protected $toolkit;

  /**
   * File size in bytes.
   */
  protected int $fileSize;

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
   * BC layer.
   *
   * @deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Play
   *    whack-a-mole instead.
   *
   * @see https://www.drupal.org/node/7654123
   */
  public function __sleep(): array {
    $ret = get_object_vars($this);
    unset($ret['imageFactory'], $ret['toolkit']);
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
   * @param \Drupal\Core\Image\ImageFactory|null $imageFactory
   *   The image factory.
   */
  public function __construct(ImageToolkitInterface $toolkit, $source = NULL, ?ImageFactory $imageFactory = NULL) {
    // @phpstan-ignore property.deprecated
    $this->toolkit = $toolkit;
    // @phpstan-ignore-next-line
    $this->toolkit->backfillImage = $this;
    $this->toolkitId = $toolkit->getPluginId();
    $this->imageFactory = $imageFactory;
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
   * {@inheritdoc}
   */
  public function getToolkit() {
    return $this->toolkit ?? $this->imageFactory->getToolkit($this->getToolkitId());
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
   * BC layer - ensure a toolkit clone is present when needed.
   *
   * @var \Drupal\Core\ImageToolkit\ImageToolkitInterface
   *
   * @deprecated in drupal:42.1.0 and is removed from drupal:43.0.0. Play
   *    whack-a-mole instead.
   *
   * @see https://www.drupal.org/node/7654123
   */
  public function ensureToolkit(bool $storeClone = FALSE): void {
    if (isset($this->toolkit)) {
      return;
    }
    if ($toolkit->isStateful) {
      throw new ImageToolkitException('$toolkit must be set in the constructor for stateful toolkits');
    }
    if (!isset($this->imageFactory)) {
      throw new ImageToolkitException('ImageFactory service is missing from ' . get_class($this));
    }
    $toolkit = $this->imageFactory->getToolkit($this->getToolkitId());
    if ($storeClone) {
      $this->toolkit = clone $toolkit;
      $this->toolkit->backfillImage = $this;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function apply($operation, array $arguments = []) {
    return $this->getToolkit()->apply($operation, $arguments, $this);
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
