<?php

namespace Drupal\deprecation_plugin_test\Plugin\SubdirDeprecationLegacy;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\deprecation_plugin_test\Attribute\SubdirDeprecation;
use Drupal\deprecation_plugin_test\Plugin\SubdirDeprecationInterface;

/**
 * Plugin that is in a deprecated subdir.
 */
#[SubdirDeprecation(
  id: 'old_plugin',
  label: new TranslatableMarkup('Old'),
)]
class LegacySubdir implements SubdirDeprecationInterface {

}
