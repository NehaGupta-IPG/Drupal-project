<?php

namespace Drupal\Component\Graph;

/**
 * Exception thrown when a directed graph contains a cycle.
 */
class CyclicGraphException extends \LogicException {

  /**
   * The detected cycle path.
   *
   * The first vertex is repeated as the last vertex to make the cycle explicit.
   *
   * @var array
   */
  protected $cycle;

  /**
   * Constructs a CyclicGraphException.
   *
   * @param array $cycle
   *   The detected cycle path.
   * @param string|null $message
   *   (optional) The exception message.
   * @param int $code
   *   (optional) The exception code.
   * @param \Throwable|null $previous
   *   (optional) The previous exception.
   */
  public function __construct(array $cycle, $message = NULL, $code = 0, ?\Throwable $previous = NULL) {
    $this->cycle = $cycle;
    $message ??= 'The graph contains a cycle: ' . implode(' -> ', $cycle) . '.';
    parent::__construct($message, $code, $previous);
  }

  /**
   * Gets the detected cycle path.
   *
   * @return array
   *   The detected cycle path.
   */
  public function getCycle() {
    return $this->cycle;
  }

}
