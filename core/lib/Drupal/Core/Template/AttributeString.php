<?php

namespace Drupal\Core\Template;

use Drupal\Component\HtmlAttribute\HtmlAttributeScalar as ComponentAttributeString;

@trigger_error('\Drupal\Core\Template\AttributeString is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use \Drupal\Component\HtmlAttribute\HtmlAttributeScalar instead. See https://www.drupal.org/node/3070485', E_USER_DEPRECATED);

/**
 * A class that represents most standard HTML attributes.
 *
 * @deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use
 *   \Drupal\Component\HtmlAttribute\HtmlAttributeScalar instead.
 *
 * @see https://www.drupal.org/node/3070485
 */
class AttributeString extends ComponentAttributeString {
}
