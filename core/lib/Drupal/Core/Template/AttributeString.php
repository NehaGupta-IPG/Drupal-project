<?php

namespace Drupal\Core\Template;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Component\Utility\Xss;

/**
 * A class that represents most standard HTML attributes.
 *
 * To use with the Attribute class, set the key to be the attribute name
 * and the value the attribute value.
 * @code
 *  $attributes = new Attribute([]);
 *  $attributes['id'] = 'socks';
 *  $attributes['style'] = 'background-color:white';
 *  echo '<cat ' . $attributes . '>';
 *  // Produces: <cat id="socks" style="background-color:white">.
 * @endcode
 *
 * @see \Drupal\Core\Template\Attribute
 */
class AttributeString extends AttributeValueBase {

  /**
   * Implements the magic __toString() method.
   */
  public function __toString() {
    // Whitelist 'title', 'alt', and all data- attributes.
    // @see Xss::attributes()
    if ($this->value instanceof MarkupInterface) {
      return (string) $this->value;
    }
    elseif (str_starts_with($this->name, 'data-') || in_array($this->name, Xss::getSkipProtocolFilteringAttributes())) {
      return Html::escape((string) $this->value);
    }
    else {
      return Html::escape(UrlHelper::stripDangerousProtocols((string) $this->value));
    }
  }

}
