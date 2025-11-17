<?php

namespace Drupal\Core\Form;

/**
 * Represents the Form API's empty option sentinel.
 *
 * This replaces direct uses of the magic string '_none'.
 */
enum SelectEmptyOption: string {
  case None = '_none';
}
