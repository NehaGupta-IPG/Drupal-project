<?php

namespace Drupal\deprecation_plugin_test\Plugin\SubdirDeprecationNew;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\deprecation_plugin_test\Attribute\SubdirDeprecation;
use Drupal\deprecation_plugin_test\Plugin\SubdirDeprecationInterface;

/**
 * Plugin that is not in a deprecated subdir.
 */
#[SubdirDeprecation(
  id: 'new_plugin',
  label: new TranslatableMarkup('New'),
)]
class NewSubdir implements SubdirDeprecationInterface {

}
