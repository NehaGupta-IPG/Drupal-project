<?php

namespace Drupal\Core\Field;

/**
 * Defines display context types for field display configuration.
 *
 * This enum provides type safety for display context parameters used in
 * field display methods like setDisplayConfigurable() and setDisplayOptions().
 */
enum DisplayContext: string {

  /* Display context for entity view/display modes. */
  case View = 'view';

  /* Display context for entity form/edit modes. */
  case Form = 'form';

}
