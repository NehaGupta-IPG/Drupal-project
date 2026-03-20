<?php

declare(strict_types=1);

namespace Drupal\Tests\dblog\Helper;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Error;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExpectationFailedException;
use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * Helper to read dblog messages in a functional test.
 *
 * This class does intentionally not use dependency injection, to support
 * containers being replaced during a test.
 */
class TestDbLogReader {

  /**
   * Map of dblog ids marked as read.
   *
   * This is ArrayObject, not array, so that it will be shared between cloned
   * instances.
   *
   * @var \ArrayObject<int, true>
   */
  protected \ArrayObject $readIdsMap;

  /**
   * Bookmarked milestones in the test.
   *
   * @var \ArrayObject<int, \Throwable>
   */
  protected \ArrayObject $bookmarks;

  /**
   * A callback to create a select query on 'watchdog' table.
   *
   * @var \Closure(): \Drupal\Core\Database\Query\SelectInterface
   */
  protected \Closure $select;

  public function __construct() {
    $this->readIdsMap = new \ArrayObject();
    $this->bookmarks = new \ArrayObject();
    $this->select = function () {
      return \Drupal::database()->select('watchdog')->orderBy('wid');
    };
  }

  /**
   * Checks if the functionality is available.
   *
   * @return bool
   *   TRUE if available, FALSE if not.
   */
  public function isAvailable(): bool {
    if (!\Drupal::hasContainer()
      || !\Drupal::getContainer()->has('database')
      || !\Drupal::getContainer()->has('module_handler')
      || !\Drupal::moduleHandler()->moduleExists('dblog')
    ) {
      return FALSE;
    }
    try {
      \Drupal::database()->query("SELECT COUNT(*) FROM {watchdog}")->execute();
    }
    catch (\Throwable) {
      // The database is not ready, or the watchdog table does not exist.
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Sets a bookmark.
   *
   * @param string $message
   *   A bookmark message.
   *
   * @return $this
   */
  public function bookmark(string $message): static {
    $query = \Drupal::database()->select('watchdog');
    $query->addExpression('max(wid)', 'latest_id');
    $latest_id = $query->execute()->fetchField();
    $this->bookmarks[$latest_id] = new LatestDbLogBookmark($message);
    return $this;
  }

  /**
   * Asserts no failures, and marks all messages as read.
   *
   * This should be called after Drupal bootstrap, to clear noise that is not
   * relevant to the test.
   *
   * @throws \PHPUnit\Framework\AssertionFailedError
   *   At least one unread failure message was found.
   */
  public function sweep(): void {
    $this->assertNoFailures();
    $this->withNonFailuresOnly()->markAllAsRead();
  }

  /**
   * Asserts and reads a logged exception.
   *
   * @param string|null $channel
   *   Expected logger channel, or NULL to not check.
   * @param class-string<\Throwable>|null $class
   *   Expected exception class, or NULL to not check.
   * @param string|null $message
   *   Expected exception message, or NULL to not check.
   * @param string|null $pattern
   *   A regex pattern for the exception message, or NULL to not check.
   * @param int|null $severity
   *   Expected severity, or NULL to not check.
   *
   * @return array
   *   The log record.
   */
  public function assertReadLoggedException(
    ?string $channel = NULL,
    ?string $class = NULL,
    ?string $message = NULL,
    ?string $pattern = NULL,
    ?int $severity = RfcLogLevel::ERROR,
  ): array {
    $record = $this->assertReadFailure(
      $severity,
      $channel,
      Error::DEFAULT_ERROR_MESSAGE,
      array_filter([
        '%type' => $class,
        '@message' => $message,
      ], fn ($value) => $value !== NULL),
    );
    if ($pattern !== NULL) {
      Assert::assertMatchesRegularExpression($pattern, $record['variables']['@message']);
    }
    return $record;
  }

  /**
   * Asserts and gets the next unread failure message, and marks it as read.
   *
   * @param int|null $severity
   *   Expected severity, or NULL to not check.
   * @param string|null $channel
   *   Expected type/channel, or NULL to not check.
   * @param string|null $message
   *   Expected message (without replacements), or NULL to not check.
   * @param array $variables
   *   Expected values for selected context variables.
   *
   * @return array
   *   The next unread failure message.
   */
  public function assertReadFailure(
    ?int $severity = NULL,
    ?string $channel = NULL,
    ?string $message = NULL,
    array $variables = [],
  ): array {
    return $this->withFailuresOnly()->assertRead($severity, $channel, $message, $variables);
  }

  /**
   * Asserts and gets the next unread matching message, and marks it as read.
   *
   * @param int|null $severity
   *   Expected severity, or NULL to not check.
   * @param string|null $channel
   *   Expected type/channel, or NULL to not check.
   * @param string|null $message
   *   Expected message (without replacements), or NULL to not check.
   * @param array $variables
   *   Expected values for selected context variables.
   *
   * @return array
   *   The next unread message.
   *
   * @throws \PHPUnit\Framework\AssertionFailedError
   *   No matching unread message was found.
   */
  public function assertRead(
    ?int $severity = NULL,
    ?string $channel = NULL,
    ?string $message = NULL,
    array $variables = [],
  ): array {
    $record = $this->tryRead();
    if (!$record) {
      Assert::fail('No dblog record was found that matches the query conditions.');
    }
    $expected_record_parts = array_filter([
      'severity' => $severity,
      'type' => $channel,
      'message' => $message,
    ], fn ($value) => $value !== NULL);
    if ($variables || $expected_record_parts) {
      $expected_record = [
        ...$record,
        ...$expected_record_parts,
        'variables' => [
          ...$record['variables'] ?? [],
          ...$variables,
        ],
      ];
      try {
        Assert::assertSame(
          "\n" . $this->formatRecordAsYaml($expected_record) . "\n",
          "\n" . $this->formatRecordAsYaml($record) . "\n",
        );
      }
      catch (ExpectationFailedException $e) {
        // Prevent failure output for further messages.
        $this->markAllAsRead();
        throw $e;
      }
    }
    return $record;
  }

  /**
   * Gets the next unread matching message, and marks it as read.
   *
   * @return array|null
   *   The next unread message, or NULL if none found.
   */
  public function tryRead(): ?array {
    $record = $this->withUnreadOnly()->fetchRecord();
    if (!$record) {
      return NULL;
    }
    $this->readIdsMap[$record['wid']] = TRUE;
    return $record;
  }

  /**
   * Asserts that no unread failure log message exists.
   *
   * @param bool $mark_all_read
   *   TRUE, to mark all matching failure log messages as read.
   *   This prevents that the same failure is thrown in a test method and in the
   *   tear down method.
   *
   * @return $this
   *   The instance.
   *
   * @throws \PHPUnit\Framework\AssertionFailedError
   *   At least one unread failure message was found.
   */
  public function assertNoFailures(bool $mark_all_read = TRUE): static {
    $this->withFailuresOnly()
      ->assertEmpty($mark_all_read);
    return $this;
  }

  /**
   * Asserts that no unread log message exist.
   *
   * @param bool $mark_all_read
   *   TRUE, to mark all matching log messages as read.
   *
   * @return $this
   *   The instance.
   *
   * @throws \PHPUnit\Framework\AssertionFailedError
   *   At least one matching unread message was found.
   */
  public function assertEmpty(bool $mark_all_read = TRUE): static {
    $instance_with_unread = $this->withUnreadOnly();
    $unread_record = $instance_with_unread->fetchRecord();
    if ($unread_record) {
      $instance_with_unread->markAllAsRead();
      // For a failure message, it is enough to report the first message.
      $this->failWithUnexpectedRecord($unread_record);
    }
    return $this;
  }

  /**
   * Fails with an unexpected log message.
   *
   * @param array $record
   *   An unexpected record from the watchdog table.
   *
   * @return never
   *   This method always fails.
   *
   * @throws \PHPUnit\Framework\AssertionFailedError
   *   This method always fails.
   */
  protected function failWithUnexpectedRecord(array $record): never {
    $bookmark = NULL;
    foreach (array_reverse(array_keys($this->bookmarks->getArrayCopy())) as $bookmark_id) {
      if ($bookmark_id < $record['wid']) {
        $bookmark = $this->bookmarks[$bookmark_id];
        break;
      }
    }
    throw new AssertionFailedError(sprintf(
      "Found an unexpected log message:\n\n%s",
      $this->formatRecordAsYaml($record),
    ), previous: $bookmark);
  }

  /**
   * Formats a dblog record as Yaml.
   *
   * @param array $record
   *   The dblog record.
   *
   * @return string
   *   The formatted record as yaml.
   */
  protected function formatRecordAsYaml(array $record): string {
    $formatted = $this->formatRecordForYamlReport($record);
    return Yaml::encode($formatted);
  }

  /**
   * Formats a dblog record so that it can be exported in Yaml.
   *
   * @param array $record
   *   The dblog record.
   *
   * @return array
   *   The formatted record suitable for yaml.
   */
  protected function formatRecordForYamlReport(array $record): array {
    // Force a new order of keys, and readable values.
    return [
      'severity' => (string) (RfcLogLevel::getLevels()[$record['severity']] ?? $record['severity']),
      'type' => $record['type'],
      'message' => $record['message'],
      'variables' => $this->formatValueForYamlReport($record['variables']),
    ] + $record;
  }

  /**
   * Formats a value so that it can be exported in Yaml.
   *
   * @param mixed $value
   *   The original value.
   *
   * @return array|mixed|\Symfony\Component\Yaml\Tag\TaggedValue
   *   A value suitable for Yaml.
   */
  protected function formatValueForYamlReport(mixed $value) {
    if (is_array($value)) {
      return array_map($this->formatValueForYamlReport(...), $value);
    }
    if (is_object($value)) {
      return match (TRUE) {
        $value instanceof TranslatableMarkup => new TaggedValue('t', (string) $value),
        $value instanceof MarkupInterface => new TaggedValue('markup', (string) $value),
        $value instanceof \stdClass => new TaggedValue('stdClass', $this->formatValueForYamlReport((array) $value)),
        default => new TaggedValue('object', get_class($value)),
      };
    }
    return $value;
  }

  /**
   * Fetches the first record that matches the conditions.
   *
   * @return array|null
   *   The record, or NULL if none found.
   */
  protected function fetchRecord(): ?array {
    $query = ($this->select)()->fields('watchdog');
    $query->range(0, 1);
    $record = $query->execute()->fetchAssoc();
    if (!$record) {
      return NULL;
    }
    $record['variables'] = match ($record['variables']) {
      '', NULL => [],
      default => unserialize($record['variables']),
    };
    return $record;
  }

  /**
   * Marks all matching records as read.
   *
   * @return int
   *   Number of records that were marked as read.
   */
  public function markAllAsRead(): int {
    $ids = $this->fetchIds();
    foreach ($ids as $id) {
      $this->readIdsMap[$id] = TRUE;
    }
    return count($ids);
  }

  /**
   * Fetches ids of matching records.
   *
   * @return list<int>
   *   Ids of matching log records.
   */
  protected function fetchIds(): array {
    $query = ($this->select)()->fields('watchdog', ['wid']);
    return $query->execute()->fetchCol();
  }

  /**
   * Immutable setter. Adds a condition to only return unread log messages.
   *
   * @return static
   *   A new modified instance.
   */
  public function withUnreadOnly(): static {
    $clone = clone $this;
    $clone->select = function () {
      $query = ($this->select)();
      $read_ids = array_keys($this->readIdsMap->getArrayCopy());
      if ($read_ids) {
        $query->condition('wid', $read_ids, 'NOT IN');
      }
      return $query;
    };
    return $clone;
  }

  /**
   * Immutable setter. Adds a condition to only read failure messages.
   *
   * @return static
   *   A new modified instance.
   */
  public function withFailuresOnly(): static {
    return $this->withSeverity(RfcLogLevel::INFO, '<');
  }

  /**
   * Immutable setter. Adds a condition to only read non-failure messages.
   *
   * @return static
   *   A new modified instance.
   */
  public function withNonFailuresOnly(): static {
    return $this->withSeverity(RfcLogLevel::INFO, '>=');
  }

  /**
   * Immutable setter. Adds a condition to filter by log channel ("type").
   *
   * @param string|list<string> $channel
   *   Name of a log channel, or a pattern for LIKE queries, or a list of
   *   channel names for IN queries.
   * @param string|null $operator
   *   The comparison operator for the SELECT query, or NULL to default to '='
   *   for a regular value, and 'IN' for array.
   *
   * @return static
   *   A new modified instance.
   */
  public function withChannel(string|array $channel, ?string $operator = NULL): static {
    return $this->withCondition('type', $channel, $operator);
  }

  /**
   * Immutable setter. Adds a LIKE condition to filter by location (url).
   *
   * The location determines on which page an event was logged.
   *
   * @param string $location
   *   A pattern for a LIKE condition for location (url).
   *
   * @return static
   *   A new modified instance.
   */
  public function withLocationLike(string $location): static {
    return $this->withLocation($location, 'LIKE');
  }

  /**
   * Immutable setter. Adds a condition to filter by location (url).
   *
   * The location determines on which page an event was logged.
   *
   * @param string|list<string> $location
   *   A location (url), or a pattern for LIKE queries, or a list of
   *   location values (urls).
   * @param string|null $operator
   *   The comparison operator for the SELECT query, or NULL to default to '='
   *   for a regular value, and 'IN' for array.
   *
   * @return static
   *   A new modified instance.
   */
  public function withLocation(string|array $location, ?string $operator = NULL): static {
    return $this->withCondition('location', $location, $operator);
  }

  /**
   * Immutable setter. Adds a condition to filter by severity.
   *
   * @param int|list<int> $severity
   *   One of the severity constants in RfcLogLevel, or a list of severity
   *   constants to use with 'IN'.
   * @param string|null $operator
   *   The comparison operator for the SELECT query, or NULL to default to '='
   *   for a regular value, and 'IN' for array.
   *
   * @return static
   *   A new modified instance.
   */
  public function withSeverity(int|array $severity, ?string $operator = NULL): static {
    return $this->withCondition('severity', $severity, $operator);
  }

  /**
   * Immutable setter. Adds a LIKE condition to filter by message.
   *
   * @param string $message
   *   A pattern for a LIKE condition for the message.
   *
   * @return static
   *   A new modified instance.
   */
  public function withMessageLike(string $message): static {
    return $this->withMessage($message, 'LIKE');
  }

  /**
   * Immutable setter. Adds a condition to filter by message.
   *
   * @param string|list<string> $message
   *   The message, or a pattern for LIKE queries, or a list of messages.
   * @param string|null $operator
   *   The comparison operator for the SELECT query, or NULL to default to '='
   *   for a regular value, and 'IN' for array.
   *
   * @return static
   *   A new modified instance.
   */
  public function withMessage(string|array $message, ?string $operator = NULL): static {
    return $this->withCondition('message', $message, $operator);
  }

  /**
   * Immutable setter. Adds a condition to filter log messages.
   *
   * @param string $field
   *   A field in the 'watchdog' table.
   * @param string|int|array $value
   *   A value to filter by.
   * @param string|null $operator
   *   The comparison operator for the SELECT query, or NULL to default to '='
   *   for a regular value, and 'IN' for array.
   *
   * @return static
   *   A new modified instance.
   */
  public function withCondition(string $field, string|int|array $value, ?string $operator = NULL): static {
    $operator ??= is_array($value) ? 'IN' : '=';
    $clone = clone $this;
    $clone->select = fn () => ($this->select)()->condition($field, $value, $operator);
    return $clone;
  }

}
