<?php

declare(strict_types=1);

namespace Drupal\Core\Database\Statement;

use Drupal\Core\Database\DatabaseExceptionWrapper;
use Drupal\Core\Database\FetchModeTrait;

/**
 * Base class for results of a data query language (DQL) statement.
 *
 * @phpstan-type FetchOptions array{'class'?: class-string, 'constructor_args'?: list<mixed>, 'column'?: int}
 */
abstract class ResultBase implements \Iterator {

  use FetchModeTrait;

  /**
   * Holds the default fetch mode.
   */
  // @phpcs:ignore Drupal.Commenting.VariableComment.Missing
  public protected(set) FetchAs $fetchMode;

  /**
   * Holds fetch options.
   *
   * @var FetchOptions
   */
  // @phpcs:ignore Drupal.Commenting.VariableComment.Missing
  public protected(set) array $fetchOptions;

  /**
   * Traces if rows can be fetched from the resultset.
   */
  protected bool $isIterable = FALSE;

  /**
   * The current row, retrieved in the current fetch format.
   */
  protected mixed $resultsetRow = NULL;

  /**
   * The index of the current row.
   *
   * This keeps the index of rows fetched from the underlying statement. It is
   * set to -1 when no rows have been fetched yet.
   */
  protected int $resultsetIndex = -1;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Database\Statement\FetchAs $fetchMode
   *   The fetch mode.
   * @param FetchOptions $fetchOptions
   *   The fetch options.
   * @param bool $isIterable
   *   TRUE if the statement result is iterable, FALSE otherwise.
   */
  public function __construct(
    FetchAs $fetchMode,
    array $fetchOptions,
    bool $isIterable,
  ) {
    $this->markResultsetIterable($isIterable);
    $this->setFetchMode($fetchMode, $fetchOptions);
  }

  /**
   * Returns the number of rows matched by the last SQL statement.
   *
   * @return int
   *   The number of rows matched by the last DELETE, INSERT, or UPDATE
   *   statement executed or throws \Drupal\Core\Database\RowCountException
   *   if the last executed statement was SELECT.
   *
   * @throws \Drupal\Core\Database\RowCountException
   */
  abstract public function rowCount(): ?int;

  /**
   * Sets the default fetch mode for this result set.
   *
   * @param \Drupal\Core\Database\Statement\FetchAs $mode
   *   One of the cases of the FetchAs enum.
   * @param FetchOptions $fetchOptions
   *   An array of fetch options.
   *
   * @return bool
   *   TRUE if successful, FALSE if not.
   */
  abstract public function setFetchMode(FetchAs $mode, array $fetchOptions): bool;

  /**
   * Fetches the next row.
   *
   * @param \Drupal\Core\Database\Statement\FetchAs $mode
   *   One of the cases of the FetchAs enum.
   * @param FetchOptions $fetchOptions
   *   An array of fetch options.
   *
   * @return array|object|int|float|string|bool|null
   *   A result, formatted according to $mode, or FALSE on failure.
   */
  abstract public function fetch(FetchAs $mode, array $fetchOptions): array|object|int|float|string|bool|NULL;

  /**
   * Returns an array containing all of the result set rows.
   *
   * @param \Drupal\Core\Database\Statement\FetchAs $mode
   *   One of the cases of the FetchAs enum.
   * @param FetchOptions $fetchOptions
   *   An array of fetch options.
   *
   * @return array
   *   An array of results.
   */
  public function fetchAll(FetchAs $mode, array $fetchOptions): array {
    $result = [];
    while ($rowAssoc = $this->fetch(FetchAs::Associative, $fetchOptions)) {
      $result[] = $this->assocToFetchMode($rowAssoc, $mode, $fetchOptions);
    }
    return $result;
  }

  /**
   * Returns the entire result set as a single associative array.
   *
   * This method is only useful for two-column result sets. It will return an
   * associative array where the key is one column from the result set and the
   * value is another field. In most cases, the default of the first two columns
   * is appropriate.
   *
   * Note that this method will run the result set to the end.
   *
   * @param int $keyIndex
   *   (Optional) The numeric index of the field to use as the array key.
   *   Defaults to 0.
   * @param int $valueIndex
   *   (optional) The numeric index of the field to use as the array value.
   *   Defaults to 1.
   *
   * @return array
   *   An associative array, or an empty array if there is no result set.
   */
  public function fetchAllKeyed(int $keyIndex = 0, int $valueIndex = 1): array {
    $result = [];
    while ($record = $this->fetch(FetchAs::List, [])) {
      $result[$record[$keyIndex]] = $record[$valueIndex];
    }
    return $result;
  }

  /**
   * Returns the result set as an associative array keyed by the given column.
   *
   * If the given column appears multiple times, later records will overwrite
   * earlier ones.
   *
   * @param string $column
   *   The name of the field on which to index the array.
   * @param \Drupal\Core\Database\Statement\FetchAs $mode
   *   One of the cases of the FetchAs enum. If set to FetchAs::Associative
   *   or FetchAs::List the returned value with be an array of arrays. For any
   *   other value it will be an array of objects. If not specified, defaults to
   *   what is specified by setFetchMode().
   * @param FetchOptions $fetchOptions
   *   An array of fetch options.
   *
   * @return array
   *   An associative array, or an empty array if there is no result set.
   */
  public function fetchAllAssoc(string $column, FetchAs $mode, array $fetchOptions): array {
    $result = [];
    while ($rowAssoc = $this->fetch(FetchAs::Associative, [])) {
      $result[$rowAssoc[$column]] = $this->assocToFetchMode($rowAssoc, $mode, $fetchOptions);
    }
    return $result;
  }

  /**
   * Informs the iterator whether rows can be fetched from the resultset.
   *
   * @param bool $isIterable
   *   TRUE if the statement result is iterable, FALSE otherwise.
   */
  protected function markResultsetIterable(bool $isIterable): void {
    $this->isIterable = $isIterable;
    $this->resultsetRow = NULL;
    if ($isIterable) {
      $this->resultsetIndex = -1;
    }
  }

  /**
   * Sets the current resultset row for the iterator, and increments the key.
   *
   * @param mixed $row
   *   The last row fetched from the client statement.
   */
  protected function setResultsetCurrentRow(mixed $row): void {
    $this->resultsetRow = $row;
    $this->resultsetIndex++;
  }

  /**
   * Informs the iterator that no more rows can be fetched from the resultset.
   */
  protected function markResultsetFetchingComplete(): void {
    $this->markResultsetIterable(FALSE);
  }

  /**
   * Returns the current element.
   *
   * @see https://www.php.net/manual/en/iterator.current.php
   *
   * @internal This method should not be called directly.
   */
  public function current(): mixed {
    return $this->resultsetRow;
  }

  /**
   * Returns the key of the current element.
   *
   * @see https://www.php.net/manual/en/iterator.key.php
   *
   * @internal This method should not be called directly.
   */
  public function key(): mixed {
    return $this->resultsetIndex;
  }

  /**
   * Rewinds back to the first element of the Iterator.
   *
   * This is the first method called when starting a foreach loop. It will not
   * be executed after foreach loops.
   *
   * @see https://www.php.net/manual/en/iterator.rewind.php
   *
   * @internal This method should not be called directly.
   */
  public function rewind(): void {
    // Nothing to do: our DatabaseStatement can't be rewound. Error out when
    // attempted.
    if ($this->resultsetIndex >= 0) {
      $this->markResultsetIterable(FALSE);
      throw new DatabaseExceptionWrapper('Attempted rewinding a StatementInterface object when fetching has already started. Refactor your code to avoid rewinding statement objects.');
    }
  }

  /**
   * Moves the current position to the next element.
   *
   * This method is called after each foreach loop.
   *
   * @see https://www.php.net/manual/en/iterator.next.php
   *
   * @internal This method should not be called directly.
   */
  public function next(): void {
    $this->fetch($this->fetchMode, $this->fetchOptions);
  }

  /**
   * Checks if current position is valid.
   *
   * This method is called after ::rewind() and ::next() to check if the
   * current position is valid.
   *
   * @see https://www.php.net/manual/en/iterator.valid.php
   *
   * @internal This method should not be called directly.
   */
  public function valid(): bool {
    if ($this->isIterable && $this->resultsetIndex === -1) {
      $this->fetch($this->fetchMode, $this->fetchOptions);
    }
    return $this->isIterable;
  }

}
