<?php

namespace Drupal\Core\Template;

use Drupal\Component\HtmlAttribute\HtmlAttributeArray as ComponentAttributeArray;

@trigger_error('\Drupal\Core\Template\AttributeArray is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use \Drupal\Component\HtmlAttribute\HtmlAttributeArray instead. See https://www.drupal.org/node/3070485', E_USER_DEPRECATED);

/**
 * A class that defines a type of Attribute that can be added to as an array.
 *
 * @deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use
 *   \Drupal\Component\HtmlAttribute\HtmlAttributeArray instead.
 *
 * @see https://www.drupal.org/node/3070485
 */
class AttributeArray extends ComponentAttributeArray {
}
