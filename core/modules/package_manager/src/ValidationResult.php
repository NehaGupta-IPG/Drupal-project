<?php

declare(strict_types=1);

namespace Drupal\package_manager;

use Drupal\Component\Assertion\Inspector;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use PhpTuf\ComposerStager\API\Exception\ExceptionInterface;

/**
 * A value object to contain the results of a validation.
 *
 * @property \Drupal\Core\StringTranslation\TranslatableMarkup[] $messages
 * @property int $severity
 *   The severity of the result as an int. Deprecated in drupal:11.5.0 and is
 *   removed from drupal:13.0.0. Use
 *   \Drupal\package_manager\ValidationResult::$severityEnum instead. See
 *   https://www.drupal.org/node/3618729.
 */
final class ValidationResult {

  /**
   * The severity of the result.
   */
  public readonly RequirementSeverity $severityEnum;

  /**
   * Creates a ValidationResult object.
   *
   * @param \Drupal\Core\Extension\Requirement\RequirementSeverity|int $severity
   *   The severity of the result.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup[]|string[] $messages
   *   The result messages.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $summary
   *   A succinct summary of the result.
   * @param bool $assert_translatable
   *   Whether to assert the messages are translatable. Internal use only.
   *
   * @throws \InvalidArgumentException
   *   Thrown if $messages is empty, or if it has 2 or more items but $summary
   *   is NULL.
   */
  private function __construct(
    RequirementSeverity|int $severity,
    private readonly array $messages,
    public readonly ?TranslatableMarkup $summary,
    bool $assert_translatable,
  ) {
    if (is_int($severity)) {
      @trigger_error('Calling ' . __METHOD__ . '() with $severity as an int is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Pass a \Drupal\Core\Extension\Requirement\RequirementSeverity instead. See https://www.drupal.org/node/3618729', E_USER_DEPRECATED);
      $severity = RequirementSeverity::from($severity);
    }
    $this->severityEnum = $severity;
    if ($assert_translatable) {
      assert(Inspector::assertAll(fn ($message) => $message instanceof TranslatableMarkup, $messages));
    }
    if (empty($messages)) {
      throw new \InvalidArgumentException('At least one message is required.');
    }
    if (count($messages) > 1 && !$summary) {
      throw new \InvalidArgumentException('If more than one message is provided, a summary is required.');
    }
  }

  /**
   * Implements magic ::__get() method.
   */
  public function __get(string $name): mixed {
    return match ($name) {
      // The messages must be private so that they cannot be mutated by external
      // code, but we want to allow callers to access them in the same way as
      // $this->summary and $this->severityEnum.
      'messages' => $this->messages,
      'severity' => $this->getDeprecatedSeverity(),
    };
  }

  /**
   * Returns the integer severity of the result.
   *
   * @return int
   *   The severity of the result.
   */
  private function getDeprecatedSeverity(): int {
    @trigger_error('\Drupal\package_manager\ValidationResult::$severity is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Use \Drupal\package_manager\ValidationResult::$severityEnum instead. See https://www.drupal.org/node/3618729', E_USER_DEPRECATED);
    return $this->severityEnum->value;
  }

  /**
   * Creates an error ValidationResult object from a throwable.
   *
   * @param \Throwable $throwable
   *   The throwable.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $summary
   *   The errors summary.
   *
   * @return static
   */
  public static function createErrorFromThrowable(\Throwable $throwable, ?TranslatableMarkup $summary = NULL): static {
    // All Composer Stager exceptions are translatable.
    $is_translatable = $throwable instanceof ExceptionInterface;
    $message = $is_translatable ? $throwable->getTranslatableMessage() : $throwable->getMessage();
    return new static(RequirementSeverity::Error, [$message], $summary, $is_translatable);
  }

  /**
   * Creates an error ValidationResult object.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup[] $messages
   *   The error messages.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $summary
   *   The errors summary.
   *
   * @return static
   */
  public static function createError(array $messages, ?TranslatableMarkup $summary = NULL): static {
    return new static(RequirementSeverity::Error, $messages, $summary, TRUE);
  }

  /**
   * Creates a warning ValidationResult object.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup[] $messages
   *   The error messages.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $summary
   *   The errors summary.
   *
   * @return static
   */
  public static function createWarning(array $messages, ?TranslatableMarkup $summary = NULL): static {
    return new static(RequirementSeverity::Warning, $messages, $summary, TRUE);
  }

  /**
   * Returns the overall severity for a set of validation results.
   *
   * @param \Drupal\package_manager\ValidationResult[] $results
   *   The validation results.
   *
   * @return int
   *   The overall severity of the results. Will be one of the
   *   SystemManager::REQUIREMENT_* constants.
   *
   * @deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Use
   *   \Drupal\package_manager\ValidationResult::getOverallSeverityEnum()
   *   instead.
   *
   * @see https://www.drupal.org/node/3618729
   */
  public static function getOverallSeverity(array $results): int {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Use \Drupal\package_manager\ValidationResult::getOverallSeverityEnum() instead. See https://www.drupal.org/node/3618729', E_USER_DEPRECATED);
    return static::getOverallSeverityEnum($results)->value;
  }

  /**
   * Returns the overall severity for a set of validation results.
   *
   * @param \Drupal\package_manager\ValidationResult[] $results
   *   The validation results.
   *
   * @return \Drupal\Core\Extension\Requirement\RequirementSeverity
   *   The overall severity of the results.
   */
  public static function getOverallSeverityEnum(array $results): RequirementSeverity {
    foreach ($results as $result) {
      if ($result->severityEnum === RequirementSeverity::Error) {
        return RequirementSeverity::Error;
      }
    }
    // If there were no errors, then any remaining results must be warnings.
    return $results ? RequirementSeverity::Warning : RequirementSeverity::OK;
  }

  /**
   * Determines if two validation results are equivalent.
   *
   * @param self $a
   *   A validation result.
   * @param self $b
   *   Another validation result.
   *
   * @return bool
   *   TRUE if the given validation results have the same severity, summary,
   *   and messages (in the same order); otherwise FALSE.
   */
  public static function isEqual(self $a, self $b): bool {
    return (
      $a->severityEnum === $b->severityEnum &&
      strval($a->summary) === strval($b->summary) &&
      array_map('strval', $a->messages) === array_map('strval', $b->messages)
    );
  }

}
