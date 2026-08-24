<?php

/**
 * @file
 * Fixture for exception message markup detection.
 */

declare(strict_types=1);

namespace Drupal\Tests\Component\Foo;

use Drupal\Component\Render\FormattableMarkup;

$markup = new FormattableMarkup('Markup outside an exception.', []);
$markup->count();

new \Exception($markup);
new \RuntimeException($markup);
