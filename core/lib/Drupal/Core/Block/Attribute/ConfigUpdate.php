<?php

declare(strict_types=1);

namespace Drupal\Core\Block\Attribute;

/**
 * Designates a method as a config schema update for blocks plugins.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
readonly class ConfigUpdate {

  public function __construct(public int $schema) {}

}
