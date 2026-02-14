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
   * {@inheritdoc}
   */
  public function __toString(): string {
    return Html::escape((string) $this->value);
  }

}
