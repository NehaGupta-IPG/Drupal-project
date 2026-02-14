<?php

namespace Drupal\Core\Template;

use Drupal\Component\HtmlAttribute\HtmlAttributeBoolean as ComponentAttributeBoolean;

@trigger_error('\Drupal\Core\Template\AttributeBoolean is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use \Drupal\Component\HtmlAttribute\HtmlAttributeBoolean instead. See https://www.drupal.org/node/3070485', E_USER_DEPRECATED);

/**
 * A class that defines a type of boolean HTML attribute.
 *
 * @deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use
 *   \Drupal\Component\Attribute\AttributeBoolean instead.
 *
 * @see https://www.drupal.org/node/3070485
 */
class AttributeBoolean extends ComponentAttributeBoolean {
}
