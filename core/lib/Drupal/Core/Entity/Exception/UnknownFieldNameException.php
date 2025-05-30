<?php

declare(strict_types=1);

namespace Drupal\Core\Entity\Exception;

/**
 * Exception thrown trying to access an unknown field on a content entity.
 */
class UnknownFieldNameException extends \InvalidArgumentException {}
