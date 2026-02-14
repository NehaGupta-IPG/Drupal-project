<?php

declare(strict_types=1);

namespace Drupal\Component\HtmlAttribute;

use Drupal\Component\Utility\Html;

/**
 * A class that represents most standard HTML attributes.
 *
 * To use with the HtmlAttributeCollection class, set the key to be the
 * attribute name and the value the attribute value.
 * @code
 *  $attributes = new HtmlAttributeCollection([]);
 *  $attributes['id'] = 'socks';
 *  $attributes['style'] = 'background-color:white';
 *  echo '<cat ' . $attributes . '>';
 *  // Produces: <cat id="socks" style="background-color:white">.
 * @endcode
 *
 * @see \Drupal\Component\HtmlAttribute\HtmlAttributeCollection
 */
class HtmlAttributeScalar extends HtmlAttributeValueBase {

  /**
   * Constructs an HtmlAttributeScalar object.
   *
   * @param string $name
   *   The name of the value.
   * @param string|int|float|null $scalarValue
   *   The value itself.
   */
  public function __construct(
    string $name,
    private string|int|float|NULL $scalarValue,
  ) {
    parent::__construct($name);
  }

  /**
   * Returns the raw value.
   *
   * @return string|int|float|null
   *   The raw value.
   */
  protected function doGetValue(): string|int|float|NULL {
    return $this->scalarValue;
  }

  /**
   * Implements the magic __toString() method.
   */
  public function __toString(): string {
    return Html::escape((string) $this->scalarValue);
  }

}
