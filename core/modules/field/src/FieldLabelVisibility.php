<?php

namespace Drupal\field;

/**
 * Provides the field label visibility options.
 *
 * @internal
 */
enum FieldLabelVisibility: string {
  case Above = 'above';
  case Inline = 'inline';
  case Hidden = 'hidden';
  case VisuallyHidden = 'visually_hidden';
}
