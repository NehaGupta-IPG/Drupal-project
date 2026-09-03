<?php

namespace Drupal\Core\Database;

/**
 * Value object carrying information pertaining to a SQL execution.
 */
class SqlExecutionInfo {

  /**
   * Constructor.
   *
   * @param string $sqlString
   *   The SQL statement string.
   * @param list<array|int|float|string|bool|null>|array<string,array|int|float|string|bool|null> $placeholders
   *   (optional) The placeholder values, keyed by placeholder name for a
   *   string containing named placeholders, 0-indexed for a string containing
   *   positional placeholders.
   * @param array<string,mixed> $options
   *   An associative array of options to control how the database operation is
   *   run.
   */
  public function __construct(
    public readonly string $sqlString,
    public readonly array $placeholders = [],
    public readonly array $options = [],
  ) {
  }

  /**
   * Returns a compact single line representation of the SQL execution.
   *
   * This is meant for the exception message, which ends up in logs, in
   * error_log() output and in CLI applications rendering the throwable
   * themselves. Runs of whitespace in the statement are collapsed and the
   * placeholder values are escaped, so that the result stays on one line.
   * The options are not included: they are internal to the database API and
   * are only exposed by the verbose dump.
   *
   * @return string
   *   The SQL statement followed by its placeholder values.
   *
   * @see \Drupal\Core\Database\Utility\SqlDumper::export()
   */
  public function toCompactString(): string {
    $placeholders = [];
    foreach ($this->placeholders as $key => $value) {
      $placeholders[] = $key . '=' . self::formatValue($value);
    }
    return preg_replace('/\s+/', ' ', trim($this->sqlString)) . '; [' . implode(', ', $placeholders) . ']';
  }

  /**
   * Appends the compact representation to an exception message.
   *
   * @param string $message
   *   The exception message.
   *
   * @return string
   *   The message, suffixed with ::toCompactString().
   */
  public function appendToMessage(string $message): string {
    return $message . ': ' . $this->toCompactString();
  }

  /**
   * Removes the compact representation from an exception message.
   *
   * Consumers rendering the full dump of this object use this to avoid
   * repeating the statement that ::appendToMessage() added to the message.
   *
   * @param string $message
   *   The exception message.
   *
   * @return string
   *   The message without the suffix added by ::appendToMessage(). Messages
   *   that do not carry the suffix are returned unchanged.
   */
  public function stripFromMessage(string $message): string {
    $suffix = ': ' . $this->toCompactString();
    return str_ends_with($message, $suffix) ? substr($message, 0, -strlen($suffix)) : $message;
  }

  /**
   * Formats a single placeholder value for ::toCompactString().
   *
   * @param array|int|float|string|bool|null $value
   *   The placeholder value.
   *
   * @return string
   *   The value as a single line string.
   */
  private static function formatValue(array|int|float|string|bool|null $value): string {
    return match (TRUE) {
      $value === NULL => 'NULL',
      is_bool($value) => $value ? 'TRUE' : 'FALSE',
      is_array($value) => '[' . implode(', ', array_map(self::formatValue(...), $value)) . ']',
      is_string($value) => "'" . strtr($value, ["\n" => '\n', "\r" => '\r']) . "'",
      default => (string) $value,
    };
  }

}
