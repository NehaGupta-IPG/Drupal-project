<?php

namespace Drupal\Core\Template;

use Drupal\Component\Utility\Html;

/**
 * Represents an attribute containing JSON formatted data.
 */
class AttributeJson extends AttributeValueBase {

  /**
   * {@inheritdoc}
   */
  const RENDER_EMPTY_ATTRIBUTE = FALSE;

  /**
   * Encode the value as JSON.
   *
   * @return string
   *   The JSON encoded value or empty string on failure.
   */
  public function __toString(): string {
    $string = json_encode($this->value());
    return $string ? Html::escape($string) : '';
  }

}
