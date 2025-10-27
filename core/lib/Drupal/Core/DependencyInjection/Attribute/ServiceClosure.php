<?php

declare(strict_types=1);

namespace Drupal\Core\DependencyInjection\Attribute;

/**
 * Attribute used to identify a closure as one that returns a service object.
 */
#[\Attribute(\Attribute::TARGET_FUNCTION)]
readonly class ServiceClosure {}
