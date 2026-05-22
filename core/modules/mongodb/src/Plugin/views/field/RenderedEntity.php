<?php

namespace Drupal\mongodb\Plugin\views\field;

use Drupal\views\Plugin\views\field\RenderedEntity as CoreRenderedEntity;

/**
 * Overriding the views field plugin "rendered_entity".
 */
class RenderedEntity extends CoreRenderedEntity {

  use FieldPluginTrait;

}
