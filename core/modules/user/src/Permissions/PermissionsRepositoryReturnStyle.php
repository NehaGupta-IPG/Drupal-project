<?php

declare(strict_types=1);

namespace Drupal\user\Permissions;

/**
 * Enumeration of what can be returned from PermissionsRepositoryInterface::getData.
 *
 * @see PermissionsRepositoryInterface::getData for the details.
 */
enum PermissionsRepositoryReturnStyle {

  case SectionArray;
  case SectionKey;

}
