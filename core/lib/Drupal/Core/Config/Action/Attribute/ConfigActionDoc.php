<?php

declare(strict_types=1);

namespace Drupal\Core\Config\Action\Attribute;

use Attribute;
use Drupal\Component\Assertion\Inspector;

/**
 * Adds documentation metadata to config actions.
 *
 * Attach alongside #[ConfigAction] or #[ActionMethod] to describe how and
 * where a config action should be used.
 *
 * @internal
 *   This API is experimental.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class ConfigActionDoc {

  /**
   * Allowed values describing what the action applies to.
   */
  private const VALID_APPLIES_TO = [
    'simple_config',
    'config_entity',
  ];

  /**
   * Constructs a ConfigActionDoc attribute.
   *
   * @param string $short
   *   Short, human-readable summary (required).
   * @param string|null $long
   *   Optional longer description.
   * @param array<string> $appliesTo
   *   Machine-readable list describing what the action operates on.
   *   Allowed values: simple_config, config_entity.
   * @param array<string> $configTargets
   *   Wildcard patterns (e.g. block.block.*, core.extension) describing config
   *   names where the action is applicable.
   * @param string|null $pluralLabel
   *   Override to describe the plural form when pluralize=TRUE/implicit.
   * @param array<string> $examples
   *   Optional examples, typically Markdown or YAML snippets.
   * @param string|null $availableSince
   *   Optional version string indicating when the action became available.
   */
  public function __construct(
    private readonly string $short,
    private readonly ?string $long = null,
    private readonly array $appliesTo = [],
    private readonly array $configTargets = [],
    private readonly ?string $pluralLabel = null,
    private readonly array $examples = [],
    private readonly ?string $availableSince = null,
  ) {
    if ($this->short === '') {
      throw new \InvalidArgumentException('ConfigActionDoc requires a non-empty short description.');
    }

    foreach ($this->appliesTo as $value) {
      if (!in_array($value, self::VALID_APPLIES_TO, true)) {
        $allowed = implode(', ', self::VALID_APPLIES_TO);
        throw new \InvalidArgumentException(sprintf('Invalid appliesTo value "%s". Allowed values: %s.', $value, $allowed));
      }
    }

    foreach ($this->configTargets as $target) {
      if ($target === '') {
        throw new \InvalidArgumentException('Config target patterns must be non-empty strings.');
      }
    }

    foreach ($this->examples as $example) {
      if (!is_string($example) || $example === '') {
        throw new \InvalidArgumentException('Examples must be non-empty strings.');
      }
    }
  }

  public function getShort(): string {
    return $this->short;
  }

  public function getLong(): ?string {
    return $this->long;
  }

  /**
   * @return array<string>
   */
  public function getAppliesTo(): array {
    return $this->appliesTo;
  }

  /**
   * @return array<string>
   */
  public function getConfigTargets(): array {
    return $this->configTargets;
  }

  public function getPluralLabel(): ?string {
    return $this->pluralLabel;
  }

  /**
   * @return array<string>
   */
  public function getExamples(): array {
    return $this->examples;
  }

  public function getAvailableSince(): ?string {
    return $this->availableSince;
  }

}
