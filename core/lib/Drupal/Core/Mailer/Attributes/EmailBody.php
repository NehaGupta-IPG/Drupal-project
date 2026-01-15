<?php

declare(strict_types=1);

namespace Drupal\Core\Mailer\Attribute;

/**
 * Marks the method which is responsible for building the email body.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class EmailBody {
}
