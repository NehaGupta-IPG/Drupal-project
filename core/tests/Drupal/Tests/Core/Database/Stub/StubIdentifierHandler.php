<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Database\Stub;

use Drupal\Core\Database\Identifier\IdentifierHandlerBase;
use Drupal\Core\Database\Identifier\IdentifierType;

/**
 * A stub of the abstract IdentifierHandlerBase class for testing purposes.
 */
class StubIdentifierHandler extends IdentifierHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function getMaxLength(IdentifierType $type): int {
    return 4000;
  }

}
