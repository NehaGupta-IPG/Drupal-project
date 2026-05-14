<?php

declare(strict_types=1);

namespace Drupal\Core\Recipe;

/**
 * Exception thrown when a recipe has configuration that exists already.
 *
 * @internal
 *   This API is experimental.
 */
class RecipePreExistingConfigException extends \RuntimeException {

  /**
   * The first configuration name in the list of mismatches.
   */
  public readonly string $configName;

  /**
   * All configuration names that mismatch active config.
   *
   * @var string[]
   */
  public readonly array $configNames;

  /**
   * Constructs a RecipePreExistingConfigException.
   *
   * @param string|string[] $configName
   *   The mismatching configuration name, or list of names.
   * @param string $message
   *   [optional] The Exception message to throw.
   * @param int $code
   *   [optional] The Exception code.
   * @param null|\Throwable $previous
   *   [optional] The previous throwable used for the exception chaining.
   */
  public function __construct(string|array $configName, string $message = '', int $code = 0, ?\Throwable $previous = NULL) {
    $this->configNames = is_array($configName) ? array_values($configName) : [$configName];
    if ($this->configNames === []) {
      throw new \InvalidArgumentException('At least one configuration name must be provided.');
    }
    $this->configName = $this->configNames[0];
    if ($message === '') {
      if (count($this->configNames) === 1) {
        $message = sprintf("The configuration '%s' exists already and does not match the recipe's configuration", $this->configName);
      }
      else {
        $message = sprintf("The following configuration exists already and does not match the recipe's configuration: %s", implode(', ', $this->configNames));
      }
    }
    parent::__construct($message, $code, $previous);
  }

}
