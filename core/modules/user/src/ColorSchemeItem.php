<?php

namespace Drupal\user;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\StringItem;

/**
 * Defines a custom field item class for the 'color_scheme' user entity field.
 */
class ColorSchemeItem extends StringItem {

  /**
   * The allowed values for the color scheme.
   */
  public const ALLOWED_VALUES = ['light', 'dark', 'auto'];

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    return self::ALLOWED_VALUES[array_rand(self::ALLOWED_VALUES)];
  }

}
