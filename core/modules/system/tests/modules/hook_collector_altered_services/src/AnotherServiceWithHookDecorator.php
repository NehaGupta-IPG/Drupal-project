<?php

declare(strict_types=1);

namespace Drupal\hook_collector_altered_services;

use Drupal\hook_collector_hook_attribute\ServiceWithHook;

/**
 * Decorates a 'hook.' service, which cause an exception to be thrown.
 */
class AnotherServiceWithHookDecorator extends ServiceWithHook {}
