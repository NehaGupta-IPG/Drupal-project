<?php

declare(strict_types=1);

namespace Drupal\Tests\image\Kernel;

use Drupal\image\Entity\ImageStyle;

/**
 * An image style that puts the width in the derivative URI.
 *
 * This copies how the Canvas module builds one derivative per width from a
 * single image style, so one style and one source have many derivatives.
 */
class ParametrizedWidthImageStyle extends ImageStyle {

  /**
   * The derivative width.
   */
  public int $width;

  /**
   * A callback to run before the derivative is built.
   *
   * @var callable|null
   */
  public $beforeCreateDerivative;

  /**
   * {@inheritdoc}
   */
  public function buildUri($uri) {
    return str_replace('/styles/' . $this->id() . '/', '/styles/' . $this->id() . '--' . $this->width . '/', parent::buildUri($uri));
  }

  /**
   * {@inheritdoc}
   */
  public function createDerivative($original_uri, $derivative_uri) {
    if ($this->beforeCreateDerivative) {
      ($this->beforeCreateDerivative)();
    }
    return parent::createDerivative($original_uri, $derivative_uri);
  }

}
