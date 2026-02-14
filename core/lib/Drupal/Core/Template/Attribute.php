<?php

namespace Drupal\Core\Template;

use Drupal\Component\HtmlAttribute\HtmlAttributeCollection;

@trigger_error('\Drupal\Core\Template\Attribute is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use \Drupal\Component\HtmlAttribute\HtmlAttributeCollection instead. See https://www.drupal.org/node/3070485', E_USER_DEPRECATED);

/**
 * Collects, sanitizes, and renders HTML attributes.
 *
 * @deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use
 *   \Drupal\Component\HtmlAttribute\HtmlAttributeCollection instead.
 *
 * @see https://www.drupal.org/node/3070485
 */
class Attribute extends HtmlAttributeCollection {
}
