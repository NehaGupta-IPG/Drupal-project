<?php

declare(strict_types=1);

namespace Drupal\system\Image;

use Drupal\Core\Image\Image as ImageBase;
use Drupal\Core\Image\ImageFactory;
use Drupal\system\Plugin\ImageToolkit\GDToolkit;

/**
 * Defines the ImageInterface object for use with the GD toolkit.
 */
class GdImageWrapper extends ImageBase {

  /**
   * A GD image object.
   */
  protected ?\GdImage $gdImage = NULL;

  /**
   * Image type represented by a PHP IMAGETYPE_* constant (e.g. IMAGETYPE_JPEG).
   */
  protected int $type;

  /**
   * Image information from a file, available prior to loading the GD object.
   *
   * This contains a copy of the array returned by executing getimagesize()
   * on the image file when the image object is instantiated. It gets reset
   * to NULL as soon as the GD object is loaded.
   *
   * @see \Drupal\system\Plugin\ImageToolkit\GDToolkit::parseFile()
   * @see \Drupal\system\Plugin\ImageToolkit\GDToolkit::setImage()
   * @see http://php.net/manual/function.getimagesize.php
   */
  protected ?array $preLoadInfo = NULL;

  public function __construct(
    ImageFactory $imageFactory,
    protected readonly string $toolkitId,
    ?string $source = NULL,
  ) {
    $this->imageFactory = $imageFactory;
    if ($source) {
      $this->source = $source;
      if ($this->toolkit()->parseFile($this)) {
        $this->fileSize = filesize($this->source);
      }
    }
  }

  public function hasPreLoadInfo(): bool {
    return (bool) $this->preLoadInfo;
  }

  public function setPreLoadInfo(?array $data): static {
    $this->preLoadInfo = $data;
    return $this;
  }

  public function getPreLoadInfo(): ?array {
    return $this->preLoadInfo;
  }

  public function hasGdImageObject(): bool {
    return (bool) $this->gdImage;
  }

  /**
   * Sets a GD image or resets existing one.
   *
   * @param \GdImage|null $gdImage
   *   The GD image object or NULL.
   *
   * @return $this
   *   An instance of the current toolkit object.
   */
  public function setGdImageObject(?\GdImage $gdImage): static {
    $this->preLoadInfo = NULL;
    $this->gdImage = $gdImage;
    return $this;
  }

  /**
   * Retrieves the GD image object.
   *
   * @return \GdImage|null
   *   The GD image object, or NULL if not available.
   */
  public function getGdImageObject(): ?\GdImage {
    if (!$this->gdImage) {
      $this->toolkit()->load($this);
    }
    return $this->gdImage;
  }

  /**
   * Gets the PHP type of the image.
   *
   * @return int
   *   The image type represented by a PHP IMAGETYPE_* constant (e.g.
   *   IMAGETYPE_JPEG).
   */
  public function getType(): int {
    return $this->type;
  }

  /**
   * Sets the PHP type of the image.
   *
   * @param int $type
   *   The image type represented by a PHP IMAGETYPE_* constant (e.g.
   *   IMAGETYPE_JPEG).
   *
   * @return $this
   */
  public function setType(int $type): static {
    if (in_array($type, GDToolkit::supportedTypes())) {
      $this->type = $type;
    }
    return $this;
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
  public function getMimeType() {
    return $this->toolkit()->getMimeType($this);
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
    if ($return = $this->toolkit()->save($this, $destination)) {
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
   *
   * @todo move back to base.
   */
  public function apply($operation, array $arguments = []) {
    return $this->toolkit()->apply($operation, $arguments, $this);
  }

}
