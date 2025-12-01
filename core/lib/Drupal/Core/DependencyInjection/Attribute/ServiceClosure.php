<?php

declare(strict_types=1);

namespace Drupal\Core\DependencyInjection\Attribute;

use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;

/**
 * Attribute used to identify a class property as a service closure.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
class ServiceClosure extends AutowireServiceClosure {}
